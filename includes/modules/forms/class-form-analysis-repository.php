<?php
defined('ABSPATH') || exit;

/** Persists manual jobs and one idempotent analysis result per source record. */
class WP_AIGent_Form_Analysis_Repository {

    public function create_job(array $data): int {
        global $wpdb;
        $lock_name = substr($wpdb->prefix . 'aigent_form_analysis_job', 0, 64);
        $locked = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 3)', $lock_name));
        if ($locked !== 1) return 0;
        try {
            $stale_before = gmdate('Y-m-d H:i:s', time() - 30 * MINUTE_IN_SECONDS);
            $now = current_time('mysql', true);
            $wpdb->query($wpdb->prepare(
                'UPDATE ' . WP_AIGent_Form_Analysis_Schema::job_table() . " SET status = 'failed', error_message = %s, completed_at_gmt = %s, updated_at_gmt = %s WHERE status IN ('pending','running') AND updated_at_gmt < %s",
                __('The analysis job expired before it completed.', 'wp-aigent'), $now, $now, $stale_before
            ));
            $active = (int) $wpdb->get_var(
                'SELECT id FROM ' . WP_AIGent_Form_Analysis_Schema::job_table() . " WHERE status IN ('pending','running') ORDER BY id DESC LIMIT 1"
            );
            if ($active) return 0;
            $stored_filters = $data['filters'];
            unset($stored_filters['search']);
            $wpdb->insert(WP_AIGent_Form_Analysis_Schema::job_table(), [
                'status' => 'pending',
                'analysis_mode' => $data['analysis_mode'],
                'filter_json' => wp_json_encode($stored_filters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'provider_id' => absint($data['provider_id']),
                'model' => (string) $data['model'],
                'reasoning_effort' => (string) $data['reasoning_effort'],
                'output_tokens' => absint($data['output_tokens']),
                'total_count' => 0,
                'created_by' => get_current_user_id(),
                'created_at_gmt' => $now,
                'updated_at_gmt' => $now,
            ], ['%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s']);
            return (int) $wpdb->insert_id;
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        }
    }

    public function add_job_items(int $job_id, array $submission_ids): int {
        global $wpdb;
        $submission_ids = array_values(array_unique(array_filter(array_map('absint', $submission_ids))));
        if (!$submission_ids) return 0;
        $now = current_time('mysql', true);
        $values = [];
        $placeholders = [];
        foreach ($submission_ids as $submission_id) {
            array_push($values, $job_id, $submission_id, $now, $now);
            $placeholders[] = '(%d,%d,%s,%s)';
        }
        $inserted = $wpdb->query($wpdb->prepare(
            'INSERT IGNORE INTO ' . WP_AIGent_Form_Analysis_Schema::job_item_table() . ' (job_id,submission_id,created_at_gmt,updated_at_gmt) VALUES ' . implode(',', $placeholders),
            $values
        ));
        return max(0, (int) $inserted);
    }

    public function finalize_job_snapshot(int $job_id): void {
        global $wpdb;
        $job_items = WP_AIGent_Form_Analysis_Schema::job_item_table();
        $analyses = WP_AIGent_Form_Analysis_Schema::analysis_table();
        $job = $this->get_job($job_id);
        if (!$job) return;
        if (($job['analysis_mode'] ?? 'update') === 'update') {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$job_items} i INNER JOIN {$analyses} a ON a.submission_id = i.submission_id
                 SET i.status = 'skipped', i.updated_at_gmt = %s WHERE i.job_id = %d AND a.status = 'succeeded'",
                current_time('mysql', true), $job_id
            ));
        }
        $now = current_time('mysql', true);
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$analyses} (submission_id,status,created_at_gmt,updated_at_gmt)
             SELECT submission_id,'pending',%s,%s FROM {$job_items} WHERE job_id = %d AND status = 'pending'",
            $now, $now, $job_id
        ));
        $stale_before = gmdate('Y-m-d H:i:s', time() - 15 * MINUTE_IN_SECONDS);
        $wpdb->query($wpdb->prepare(
            "UPDATE {$analyses} a INNER JOIN {$job_items} i ON i.submission_id = a.submission_id
             SET a.status = 'pending', a.claim_token = '', a.error_code = '', a.error_message = '', a.updated_at_gmt = %s
             WHERE i.job_id = %d AND i.status = 'pending'
               AND (a.status IN ('pending','failed','succeeded') OR (a.status = 'running' AND a.updated_at_gmt < %s))",
            $now, $job_id, $stale_before
        ));
        $counts = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS total_count, SUM(status = 'skipped') AS skipped_count FROM {$job_items} WHERE job_id = %d",
            $job_id
        ), ARRAY_A) ?: [];
        $skipped = absint($counts['skipped_count'] ?? 0);
        $wpdb->update(WP_AIGent_Form_Analysis_Schema::job_table(), [
            'total_count' => absint($counts['total_count'] ?? 0),
            'inspected_count' => $skipped,
            'skipped_count' => $skipped,
            'updated_at_gmt' => current_time('mysql', true),
        ], ['id' => $job_id], ['%d', '%d', '%d', '%s'], ['%d']);
    }

    public function cancel_job(int $job_id, string $message): void {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            'DELETE FROM ' . WP_AIGent_Form_Analysis_Schema::job_item_table() . ' WHERE job_id = %d',
            $job_id
        ));
        $now = current_time('mysql', true);
        $wpdb->update(WP_AIGent_Form_Analysis_Schema::job_table(), [
            'status' => 'failed', 'error_message' => $message, 'completed_at_gmt' => $now, 'updated_at_gmt' => $now,
        ], ['id' => $job_id], ['%s', '%s', '%s', '%s'], ['%d']);
    }

    public function delete_empty_job(int $job_id): void {
        global $wpdb;
        $wpdb->delete(WP_AIGent_Form_Analysis_Schema::job_item_table(), ['job_id' => $job_id], ['%d']);
        $wpdb->delete(WP_AIGent_Form_Analysis_Schema::job_table(), ['id' => $job_id], ['%d']);
    }

    public function claim_next_job_item(int $job_id): ?array {
        global $wpdb;
        $table = WP_AIGent_Form_Analysis_Schema::job_item_table();
        $now = current_time('mysql', true);
        $stale_before = gmdate('Y-m-d H:i:s', time() - 15 * MINUTE_IN_SECONDS);
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = 'pending', updated_at_gmt = %s WHERE job_id = %d AND status = 'running' AND updated_at_gmt < %s",
            $now, $job_id, $stale_before
        ));
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table} WHERE job_id = %d AND status = 'pending' ORDER BY id ASC LIMIT 1",
                $job_id
            ), ARRAY_A);
            if (!is_array($row)) return null;
            $claimed = $wpdb->update($table, ['status' => 'running', 'updated_at_gmt' => $now], [
                'id' => absint($row['id']), 'job_id' => $job_id, 'status' => 'pending',
            ], ['%s', '%s'], ['%d', '%d', '%s']);
            if ($claimed === 1) {
                $row['status'] = 'running';
                return $row;
            }
        }
        return null;
    }

    public function has_running_job_items(int $job_id): bool {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT 1 FROM " . WP_AIGent_Form_Analysis_Schema::job_item_table() . " WHERE job_id = %d AND status = 'running' LIMIT 1",
            $job_id
        ));
    }

    public function get_job(int $job_id): ?array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . WP_AIGent_Form_Analysis_Schema::job_table() . ' WHERE id = %d',
            $job_id
        ), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function start_job(int $job_id): void {
        global $wpdb;
        $now = current_time('mysql', true);
        $wpdb->query($wpdb->prepare(
            'UPDATE ' . WP_AIGent_Form_Analysis_Schema::job_table() . " SET status = 'running', started_at_gmt = COALESCE(started_at_gmt, %s), updated_at_gmt = %s WHERE id = %d AND status IN ('pending','running')",
            $now, $now, $job_id
        ));
    }

    public function advance_job(int $job_id, int $cursor_id, string $outcome): void {
        global $wpdb;
        $allowed = ['succeeded_count', 'failed_count', 'skipped_count'];
        $counter = in_array($outcome, $allowed, true) ? $outcome : 'skipped_count';
        $item_status = $counter === 'succeeded_count' ? 'succeeded' : ($counter === 'failed_count' ? 'failed' : 'skipped');
        $wpdb->update(WP_AIGent_Form_Analysis_Schema::job_item_table(), [
            'status' => $item_status, 'updated_at_gmt' => current_time('mysql', true),
        ], ['id' => $cursor_id, 'job_id' => $job_id], ['%s', '%s'], ['%d', '%d']);
        $wpdb->query($wpdb->prepare(
            'UPDATE ' . WP_AIGent_Form_Analysis_Schema::job_table() . " SET cursor_id = GREATEST(cursor_id, %d), inspected_count = inspected_count + 1, {$counter} = {$counter} + 1, updated_at_gmt = %s WHERE id = %d",
            $cursor_id, current_time('mysql', true), $job_id
        ));
    }

    public function release_job_item(int $job_id, int $item_id): void {
        global $wpdb;
        $wpdb->update(WP_AIGent_Form_Analysis_Schema::job_item_table(), [
            'status' => 'pending', 'updated_at_gmt' => current_time('mysql', true),
        ], ['id' => $item_id, 'job_id' => $job_id, 'status' => 'running'], ['%s', '%s'], ['%d', '%d', '%s']);
    }

    public function analyses_by_submission_ids(array $submission_ids): array {
        global $wpdb;
        $submission_ids = array_values(array_unique(array_filter(array_map('absint', $submission_ids))));
        if (!$submission_ids) return [];
        $placeholders = implode(',', array_fill(0, count($submission_ids), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . WP_AIGent_Form_Analysis_Schema::analysis_table() . " WHERE submission_id IN ({$placeholders})",
            $submission_ids
        ), ARRAY_A) ?: [];
        $indexed = [];
        foreach ($rows as $row) $indexed[absint($row['submission_id'])] = $row;
        return $indexed;
    }

    public function orphan_candidates(int $after_id, int $limit = 500): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT id, submission_id FROM ' . WP_AIGent_Form_Analysis_Schema::analysis_table() . ' WHERE id > %d ORDER BY id ASC LIMIT %d',
            $after_id, min(1000, max(1, $limit))
        ), ARRAY_A) ?: [];
    }

    public function delete_by_submission_ids(array $submission_ids): int {
        global $wpdb;
        $submission_ids = array_values(array_unique(array_filter(array_map('absint', $submission_ids))));
        if (!$submission_ids) return 0;
        $placeholders = implode(',', array_fill(0, count($submission_ids), '%d'));
        return (int) $wpdb->query($wpdb->prepare(
            'DELETE FROM ' . WP_AIGent_Form_Analysis_Schema::analysis_table() . " WHERE submission_id IN ({$placeholders})",
            $submission_ids
        ));
    }

    public function complete_job(int $job_id): void {
        global $wpdb;
        $now = current_time('mysql', true);
        $wpdb->update(WP_AIGent_Form_Analysis_Schema::job_table(),
            ['status' => 'succeeded', 'completed_at_gmt' => $now, 'updated_at_gmt' => $now],
            ['id' => $job_id], ['%s', '%s', '%s'], ['%d']);
    }

    public function fail_job(int $job_id, string $message): void {
        global $wpdb;
        $now = current_time('mysql', true);
        $wpdb->query($wpdb->prepare(
            "UPDATE " . WP_AIGent_Form_Analysis_Schema::job_item_table() . " SET status = 'pending', updated_at_gmt = %s WHERE job_id = %d AND status = 'running'",
            $now, $job_id
        ));
        $wpdb->update(WP_AIGent_Form_Analysis_Schema::job_table(),
            ['status' => 'failed', 'error_message' => $message, 'completed_at_gmt' => $now, 'updated_at_gmt' => $now],
            ['id' => $job_id], ['%s', '%s', '%s', '%s'], ['%d']);
    }

    /** Atomically reserves a row according to the job mode without stealing an active claim. */
    public function claim(int $submission_id, int $provider_id, string $model, string $analysis_mode): string {
        global $wpdb;
        $table = WP_AIGent_Form_Analysis_Schema::analysis_table();
        $now = current_time('mysql', true);
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table}
             (submission_id, status, created_at_gmt, updated_at_gmt)
             VALUES (%d, 'pending', %s, %s)",
            $submission_id, $now, $now
        ));

        $token = wp_generate_uuid4();
        $stale_before = gmdate('Y-m-d H:i:s', time() - 15 * MINUTE_IN_SECONDS);
        $claimable_statuses = $analysis_mode === 'overwrite' ? "'pending','failed','succeeded'" : "'pending','failed'";
        $clear_result = $analysis_mode === 'overwrite'
            ? ", requirements_summary = NULL, is_spam = 0, spam_reason = NULL, intent_level = '', intent_summary = NULL, token_usage = NULL, duration_ms = 0, completed_at_gmt = NULL"
            : '';
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET status = 'running', claim_token = %s, attempt_count = attempt_count + 1,
                 provider_id = %d, model = %s,
                 error_code = '', error_message = '', started_at_gmt = %s, updated_at_gmt = %s {$clear_result}
             WHERE submission_id = %d
               AND (status IN ({$claimable_statuses})
                    OR (status = 'running' AND updated_at_gmt < %s))",
            $token, $provider_id, $model, $now, $now, $submission_id, $stale_before
        ));
        return $updated === 1 ? $token : '';
    }

    public function save_success(int $submission_id, string $claim_token, array $analysis, array $usage, int $duration_ms): bool {
        global $wpdb;
        $now = current_time('mysql', true);
        $updated = $wpdb->update(WP_AIGent_Form_Analysis_Schema::analysis_table(), [
            'status' => 'succeeded', 'claim_token' => '',
            'requirements_summary' => $analysis['requirements_summary'],
            'is_spam' => !empty($analysis['spam']['is_spam']) ? 1 : 0,
            'spam_reason' => $analysis['spam']['reason'],
            'intent_level' => $analysis['intent']['level'],
            'intent_summary' => $analysis['intent']['summary'],
            'token_usage' => wp_json_encode($usage), 'duration_ms' => max(0, $duration_ms),
            'error_code' => '', 'error_message' => '', 'completed_at_gmt' => $now, 'updated_at_gmt' => $now,
        ], [
            'submission_id' => $submission_id, 'claim_token' => $claim_token,
        ], ['%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s'], ['%d', '%s']);
        return $updated === 1;
    }

    public function save_failure(int $submission_id, string $claim_token, string $code, string $message, int $duration_ms = 0): void {
        global $wpdb;
        $wpdb->update(WP_AIGent_Form_Analysis_Schema::analysis_table(), [
            'status' => 'failed', 'claim_token' => '', 'duration_ms' => max(0, $duration_ms),
            'error_code' => sanitize_key($code), 'error_message' => $message, 'updated_at_gmt' => current_time('mysql', true),
        ], [
            'submission_id' => $submission_id, 'claim_token' => $claim_token,
        ], ['%s', '%s', '%d', '%s', '%s', '%s'], ['%d', '%s']);
    }
}
