<?php
defined('ABSPATH') || exit;

/** Combines read-only source rows with AIgent-owned analysis data. */
class WP_AIGent_Form_Submissions_Query {

    private const SCAN_LIMIT = 500;
    private const CLEANUP_CURSOR_OPTION = 'wp_aigent_form_orphan_cleanup_cursor';

    private WP_AIGent_Form_Submission_Source $source;
    private WP_AIGent_Form_Analysis_Repository $repository;

    public function __construct(WP_AIGent_Form_Submission_Source $source, WP_AIGent_Form_Analysis_Repository $repository) {
        $this->source = $source;
        $this->repository = $repository;
    }

    public function query(array $filters, int $page, int $per_page, string $orderby = 'submission_date', string $order = 'desc'): array {
        $page = max(1, $page);
        $per_page = min(100, max(1, $per_page));
        $offset = ($page - 1) * $per_page;
        $matched = 0;
        $items = [];
        $cursor = [];
        $filters['orderby'] = in_array($orderby, ['id', 'submission_date'], true) ? $orderby : 'submission_date';
        $order = strtolower($order) === 'asc' ? 'asc' : 'desc';

        do {
            $rows = $this->source->scan($filters, $cursor, self::SCAN_LIMIT, $order);
            if (!$rows) break;
            $analyses = $this->repository->analyses_by_submission_ids(wp_list_pluck($rows, 'source_record_id'));
            foreach ($rows as $row) {
                $analysis = $analyses[absint($row['source_record_id'])] ?? null;
                if (!$this->matches($row, $analysis, $filters)) continue;
                if ($matched >= $offset && count($items) < $per_page) $items[] = $this->merge($row, $analysis);
                $matched++;
            }
            $last = end($rows);
            $cursor = ['id' => absint($last['source_record_id'] ?? 0), 'created_at_gmt' => (string) ($last['source_created_at_gmt'] ?? '')];
        } while (count($rows) === self::SCAN_LIMIT);

        return ['items' => $items, 'total' => $matched];
    }

    /** Snapshot every matching source ID without an unlimited in-memory collection. */
    public function snapshot(int $job_id, array $filters): int {
        $total = 0;
        $cursor = [];
        $filters['orderby'] = 'id';
        do {
            $rows = $this->source->scan($filters, $cursor, self::SCAN_LIMIT, 'asc');
            if (!$rows) break;
            $analyses = $this->repository->analyses_by_submission_ids(wp_list_pluck($rows, 'source_record_id'));
            $ids = [];
            foreach ($rows as $row) {
                $analysis = $analyses[absint($row['source_record_id'])] ?? null;
                if ($this->matches($row, $analysis, $filters)) $ids[] = absint($row['source_record_id']);
            }
            $total += $this->repository->add_job_items($job_id, $ids);
            $last = end($rows);
            $cursor = ['id' => absint($last['source_record_id'] ?? 0), 'created_at_gmt' => (string) ($last['source_created_at_gmt'] ?? '')];
        } while (count($rows) === self::SCAN_LIMIT);
        $this->repository->finalize_job_snapshot($job_id);
        return $total;
    }

    public function filter_options(string $from_gmt, string $to_gmt): array {
        return $this->source->filter_options($from_gmt, $to_gmt);
    }

    public function is_available(): bool {
        return $this->source->is_available();
    }

    public function cleanup_orphans(int $limit = 500): int {
        if (!$this->source->is_available()) return 0;
        $cursor = absint(get_option(self::CLEANUP_CURSOR_OPTION, 0));
        $candidates = $this->repository->orphan_candidates($cursor, $limit);
        if (!$candidates && $cursor) {
            update_option(self::CLEANUP_CURSOR_OPTION, 0, false);
            return 0;
        }
        if (!$candidates) return 0;
        $submission_ids = array_map('absint', wp_list_pluck($candidates, 'submission_id'));
        $existing = array_fill_keys($this->source->existing_ids($submission_ids), true);
        $missing = array_values(array_filter($submission_ids, static fn(int $id): bool => empty($existing[$id])));
        update_option(self::CLEANUP_CURSOR_OPTION, absint(end($candidates)['id'] ?? 0), false);
        return $this->repository->delete_by_submission_ids($missing);
    }

    private function matches(array $row, ?array $analysis, array $filters): bool {
        $status = $analysis ? sanitize_key((string) $analysis['status']) : 'unanalyzed';
        if (!empty($filters['analysis_status']) && $filters['analysis_status'] !== $status) return false;
        if (!empty($filters['spam'])) {
            if (!$analysis || $status !== 'succeeded') return false;
            if ($filters['spam'] === 'yes' && empty($analysis['is_spam'])) return false;
            if ($filters['spam'] === 'no' && !empty($analysis['is_spam'])) return false;
        }
        if (!empty($filters['intent']) && (!$analysis || (string) $analysis['intent_level'] !== $filters['intent'])) return false;
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        if ($search !== '') {
            $haystack = implode(' ', [
                (string) ($row['source_record_id'] ?? ''), '#' . (string) ($row['source_record_id'] ?? ''), (string) ($row['email'] ?? ''),
                (string) ($row['form_name'] ?? ''), (string) ($row['page_url'] ?? ''),
                (string) ($analysis['requirements_summary'] ?? ''), (string) ($analysis['intent_level'] ?? ''),
                (string) ($analysis['intent_summary'] ?? ''), (string) ($analysis['spam_reason'] ?? ''),
                $analysis ? (!empty($analysis['is_spam']) ? 'spam yes' : 'not spam no') : '',
                $status, (string) ($analysis['error_message'] ?? ''),
                (string) ($row['source_created_at_gmt'] ?? ''), get_date_from_gmt((string) ($row['source_created_at_gmt'] ?? ''), 'Y-m-d H:i'),
            ]);
            if (!str_contains(mb_strtolower($haystack), $search)) return false;
        }
        return true;
    }

    private function merge(array $row, ?array $analysis): array {
        return array_merge($row, [
            'requirements_summary' => (string) ($analysis['requirements_summary'] ?? ''),
            'is_spam' => $analysis && ($analysis['status'] ?? '') === 'succeeded' ? (int) $analysis['is_spam'] : null,
            'spam_reason' => (string) ($analysis['spam_reason'] ?? ''),
            'intent_level' => (string) ($analysis['intent_level'] ?? ''),
            'intent_summary' => (string) ($analysis['intent_summary'] ?? ''),
            'analysis_status' => $analysis ? (string) $analysis['status'] : 'unanalyzed',
            'error_message' => (string) ($analysis['error_message'] ?? ''),
        ]);
    }
}
