<?php
defined('ABSPATH') || exit;

/** Read-only adapter for Elementor Pro submission tables. */
class WP_AIGent_Elementor_Submission_Adapter implements WP_AIGent_Form_Submission_Source {

    private string $submission_table;
    private string $value_table;

    public function __construct() {
        global $wpdb;
        $this->submission_table = $wpdb->prefix . 'e_submissions';
        $this->value_table = $wpdb->prefix . 'e_submissions_values';
    }

    public function is_available(): bool {
        global $wpdb;
        $submission = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($this->submission_table)));
        $values = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($this->value_table)));
        return $submission === $this->submission_table && $values === $this->value_table;
    }

    /** Read a cursor page without changing Elementor rows, read flags, or status. */
    public function read_after(int $cursor_id, string $from_gmt, string $to_gmt, int $limit = 20): array {
        global $wpdb;
        if (!$this->is_available()) return [];

        $limit = min(100, max(1, $limit));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, post_id, referer, referer_title, element_id, form_name, campaign_id, created_at_gmt
             FROM {$this->submission_table}
             WHERE type = %s AND id > %d AND created_at_gmt >= %s AND created_at_gmt <= %s
             ORDER BY id ASC LIMIT %d",
            'submission', $cursor_id, $from_gmt, $to_gmt, $limit
        ), ARRAY_A) ?: [];
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
            foreach ($values_by_submission[$id] ?? [] as $field_key => $value) {
                $definition = $field_definitions[$field_key] ?? [];
                $fields[] = [
                    'key' => $field_key,
                    'label' => (string) ($definition['label'] ?? ''),
                    'type' => (string) ($definition['type'] ?? ''),
                    'value' => $value,
                ];
            }
            return [
                'source_type' => 'elementor_submission',
                'source_record_id' => $id,
                'source_created_at_gmt' => (string) ($row['created_at_gmt'] ?? ''),
                'form_name' => (string) ($row['form_name'] ?? ''),
                'form_id' => (string) ($row['element_id'] ?? ''),
                'page_id' => absint($row['post_id'] ?? 0),
                'page_url' => esc_url_raw((string) ($row['referer'] ?? '')),
                'page_title' => (string) ($row['referer_title'] ?? ''),
                'campaign_id' => (string) ($row['campaign_id'] ?? ''),
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
