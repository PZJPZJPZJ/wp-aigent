<?php
defined('ABSPATH') || exit;

/** Read-only adapter for Elementor Pro submission tables. */
class WP_AIGent_Elementor_Submission_Adapter implements WP_AIGent_Form_Submission_Source {

    private string $submission_table;
    private string $value_table;
    private ?bool $available = null;

    public function __construct() {
        global $wpdb;
        $this->submission_table = $wpdb->prefix . 'e_submissions';
        $this->value_table = $wpdb->prefix . 'e_submissions_values';
    }

    public function is_available(): bool {
        global $wpdb;
        if ($this->available !== null) return $this->available;
        $submission = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($this->submission_table)));
        $values = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($this->value_table)));
        $this->available = $submission === $this->submission_table && $values === $this->value_table;
        return $this->available;
    }

    /** Scan Elementor rows without updating read flags, status, or any source value. */
    public function scan(array $filters, array $cursor = [], int $limit = 500, string $order = 'asc'): array {
        global $wpdb;
        if (!$this->is_available()) return [];
        $limit = min(1000, max(1, $limit));
        $order = strtolower($order) === 'desc' ? 'DESC' : 'ASC';
        $where = ['type = %s', 'created_at_gmt >= %s', 'created_at_gmt <= %s'];
        $values = ['submission', (string) $filters['date_from_gmt'], (string) $filters['date_to_gmt']];
        $order_by = ($filters['orderby'] ?? 'submission_date') === 'id' ? 'id' : 'created_at_gmt';
        $cursor_id = absint($cursor['id'] ?? 0);
        $cursor_date = (string) ($cursor['created_at_gmt'] ?? '');
        if ($cursor_id && $order_by === 'id') {
            $where[] = $order === 'ASC' ? 'id > %d' : 'id < %d';
            $values[] = $cursor_id;
        } elseif ($cursor_id && $cursor_date !== '') {
            $operator = $order === 'ASC' ? '>' : '<';
            $where[] = "(created_at_gmt {$operator} %s OR (created_at_gmt = %s AND id {$operator} %d))";
            array_push($values, $cursor_date, $cursor_date, $cursor_id);
        }
        if (!empty($filters['form'])) {
            [$post_id, $element_id] = array_pad(explode(':', (string) $filters['form'], 2), 2, '');
            if (absint($post_id) && $element_id !== '') {
                $where[] = 'post_id = %d AND element_id = %s';
                $values[] = absint($post_id);
                $values[] = $element_id;
            }
        }
        if (!empty($filters['page_url'])) {
            $where[] = 'referer = %s';
            $values[] = (string) $filters['page_url'];
        }
        $values[] = $limit;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, post_id, referer, referer_title, element_id, form_name, campaign_id, created_at, created_at_gmt
             FROM {$this->submission_table} WHERE " . implode(' AND ', $where) . " ORDER BY {$order_by} {$order}, id {$order} LIMIT %d",
            $values
        ), ARRAY_A) ?: [];
        return $this->hydrate($rows);
    }

    public function read(int $submission_id): ?array {
        global $wpdb;
        if (!$submission_id || !$this->is_available()) return null;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, post_id, referer, referer_title, element_id, form_name, campaign_id, created_at, created_at_gmt
             FROM {$this->submission_table} WHERE type = %s AND id = %d",
            'submission', $submission_id
        ), ARRAY_A);
        $items = $row ? $this->hydrate([$row]) : [];
        return $items[0] ?? null;
    }

    public function existing_ids(array $submission_ids): array {
        global $wpdb;
        $submission_ids = array_values(array_unique(array_filter(array_map('absint', $submission_ids))));
        if (!$submission_ids || !$this->is_available()) return [];
        $placeholders = implode(',', array_fill(0, count($submission_ids), '%d'));
        return array_map('absint', $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$this->submission_table} WHERE type = 'submission' AND id IN ({$placeholders})",
            $submission_ids
        )) ?: []);
    }

    public function filter_options(string $from_gmt, string $to_gmt): array {
        global $wpdb;
        if (!$this->is_available()) return ['forms' => [], 'pages' => []];
        $form_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT post_id, element_id, form_name FROM {$this->submission_table}
             WHERE type = %s AND created_at_gmt >= %s AND created_at_gmt <= %s
             GROUP BY post_id, element_id, form_name ORDER BY form_name ASC LIMIT 1000",
            'submission', $from_gmt, $to_gmt
        ), ARRAY_A) ?: [];
        $page_rows = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT referer FROM {$this->submission_table}
             WHERE type = %s AND created_at_gmt >= %s AND created_at_gmt <= %s AND referer <> ''
             ORDER BY referer ASC LIMIT 1000",
            'submission', $from_gmt, $to_gmt
        )) ?: [];
        $forms = [];
        $pages = [];
        foreach ($form_rows as $row) {
            $form_key = absint($row['post_id']) . ':' . sanitize_text_field((string) $row['element_id']);
            if ($form_key !== '0:') $forms[$form_key] = sanitize_text_field((string) ($row['form_name'] ?: $row['element_id']));
        }
        foreach ($page_rows as $page_url) {
            $url = esc_url_raw((string) $page_url);
            if ($url !== '') $pages[$url] = $url;
        }
        asort($forms, SORT_NATURAL | SORT_FLAG_CASE);
        ksort($pages, SORT_NATURAL | SORT_FLAG_CASE);
        return ['forms' => $forms, 'pages' => $pages];
    }

    public function detail_url(int $submission_id): string {
        $url = admin_url('admin.php?page=e-form-submissions#/' . absint($submission_id));
        return (string) apply_filters('wp_aigent_elementor_submission_detail_url', $url, $submission_id);
    }

    private function hydrate(array $rows): array {
        global $wpdb;
        if (!$rows) return [];
        $ids = array_map('absint', wp_list_pluck($rows, 'id'));
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $value_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT submission_id, `key`, value FROM {$this->value_table}
             WHERE submission_id IN ({$placeholders}) ORDER BY id ASC",
            ...$ids
        ), ARRAY_A) ?: [];
        $values_by_submission = [];
        foreach ($value_rows as $value_row) {
            $submission_id = absint($value_row['submission_id'] ?? 0);
            $field_key = sanitize_text_field((string) ($value_row['key'] ?? ''));
            if ($submission_id && $field_key !== '') $values_by_submission[$submission_id][$field_key] = (string) ($value_row['value'] ?? '');
        }

        return array_map(function (array $row) use ($values_by_submission): array {
            $id = absint($row['id'] ?? 0);
            $field_definitions = $this->field_definitions(absint($row['post_id'] ?? 0), (string) ($row['element_id'] ?? ''));
            $fields = [];
            $email = '';
            foreach ($values_by_submission[$id] ?? [] as $field_key => $value) {
                $definition = $field_definitions[$field_key] ?? [];
                $semantic = implode(' ', [$field_key, (string) ($definition['label'] ?? ''), (string) ($definition['type'] ?? '')]);
                if ($email === '' && ((string) ($definition['type'] ?? '') === 'email' || preg_match('/e-?mail|邮箱|邮件/iu', $semantic))) {
                    $email = sanitize_email($value);
                }
                $fields[] = [
                    'key' => $field_key,
                    'label' => (string) ($definition['label'] ?? ''),
                    'type' => (string) ($definition['type'] ?? ''),
                    'value' => $value,
                ];
            }
            if ($email === '') {
                foreach ($values_by_submission[$id] ?? [] as $value) {
                    $candidate = sanitize_email(trim($value));
                    if ($candidate !== '' && is_email($candidate)) {
                        $email = $candidate;
                        break;
                    }
                }
            }
            return [
                'source_type' => 'elementor_submission',
                'source_record_id' => $id,
                'source_created_at_gmt' => $this->created_at_gmt($row),
                'form_name' => (string) ($row['form_name'] ?? ''),
                'form_id' => (string) ($row['element_id'] ?? ''),
                'page_id' => absint($row['post_id'] ?? 0),
                'page_url' => esc_url_raw((string) ($row['referer'] ?? '')),
                'page_title' => (string) ($row['referer_title'] ?? ''),
                'campaign_id' => (string) ($row['campaign_id'] ?? ''),
                'email' => $email,
                'detail_url' => $this->detail_url($id),
                'fields' => $fields,
            ];
        }, $rows);
    }

    /** Read Elementor page JSON locally to resolve opaque field IDs to labels. */
    private function field_definitions(int $post_id, string $element_id): array {
        static $cache = [];
        $cache_key = $post_id . ':' . $element_id;
        if (isset($cache[$cache_key])) return $cache[$cache_key];

        $document = json_decode((string) get_post_meta($post_id, '_elementor_data', true), true);
        $element = is_array($document) ? $this->find_element($document, $element_id) : null;
        $definitions = [];
        foreach (($element['settings']['form_fields'] ?? []) as $field) {
            if (!is_array($field)) continue;
            $key = sanitize_text_field((string) ($field['custom_id'] ?? ''));
            if ($key === '') continue;
            $definitions[$key] = [
                'label' => sanitize_text_field((string) ($field['field_label'] ?? '')),
                'type' => sanitize_key((string) ($field['field_type'] ?? '')),
            ];
        }
        $cache[$cache_key] = $definitions;
        return $definitions;
    }

    private function created_at_gmt(array $row): string {
        $gmt = (string) ($row['created_at_gmt'] ?? '');
        if ($gmt !== '' && $gmt !== '0000-00-00 00:00:00') return $gmt;
        $local = (string) ($row['created_at'] ?? '');
        return $local !== '' && $local !== '0000-00-00 00:00:00' ? get_gmt_from_date($local, 'Y-m-d H:i:s') : '';
    }

    private function find_element(array $elements, string $element_id): ?array {
        foreach ($elements as $element) {
            if (!is_array($element)) continue;
            if ((string) ($element['id'] ?? '') === $element_id) return $element;
            if (!empty($element['elements']) && is_array($element['elements'])) {
                $found = $this->find_element($element['elements'], $element_id);
                if ($found) return $found;
            }
        }
        return null;
    }
}
