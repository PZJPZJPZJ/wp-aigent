<?php
defined('ABSPATH') || exit;

/** Persists manual jobs and one idempotent analysis result per source record. */
class WP_AIGent_Form_Analysis_Repository {

    public function create_job(array $data): int {
        global $wpdb;
        $now = current_time('mysql', true);
        $wpdb->insert(WP_AIGent_Form_Analysis_Schema::job_table(), [
            'status' => 'pending',
            'date_from_gmt' => $data['date_from_gmt'],
            'date_to_gmt' => $data['date_to_gmt'],
            'provider_id' => absint($data['provider_id']),
            'model' => (string) $data['model'],
            'reasoning_effort' => (string) $data['reasoning_effort'],
            'output_tokens' => absint($data['output_tokens']),
            'created_by' => get_current_user_id(),
            'created_at_gmt' => $now,
            'updated_at_gmt' => $now,
        ], ['%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s']);
        return (int) $wpdb->insert_id;
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
        $wpdb->query($wpdb->prepare(
            'UPDATE ' . WP_AIGent_Form_Analysis_Schema::job_table() . " SET cursor_id = GREATEST(cursor_id, %d), inspected_count = inspected_count + 1, {$counter} = {$counter} + 1, updated_at_gmt = %s WHERE id = %d",
            $cursor_id, current_time('mysql', true), $job_id
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
        $wpdb->update(WP_AIGent_Form_Analysis_Schema::job_table(),
            ['status' => 'failed', 'error_message' => $message, 'completed_at_gmt' => $now, 'updated_at_gmt' => $now],
            ['id' => $job_id], ['%s', '%s', '%s', '%s'], ['%d']);
    }

    /** Atomically reserves a row unless it succeeded or is actively running. */
    public function claim(array $submission, int $provider_id, string $model): string {
        global $wpdb;
        $table = WP_AIGent_Form_Analysis_Schema::analysis_table();
        $now = current_time('mysql', true);
        $fingerprint = hash('sha256', wp_json_encode($submission));
        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table}
             (source_type, source_record_id, source_created_at_gmt, source_fingerprint, status, created_at_gmt, updated_at_gmt)
             VALUES (%s, %d, %s, %s, 'pending', %s, %s)",
            $submission['source_type'], $submission['source_record_id'], $submission['source_created_at_gmt'], $fingerprint, $now, $now
        ));

        $token = wp_generate_uuid4();
        $stale_before = gmdate('Y-m-d H:i:s', time() - 15 * MINUTE_IN_SECONDS);
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET status = 'running', claim_token = %s, attempt_count = attempt_count + 1,
                 provider_id = %d, model = %s, source_fingerprint = %s,
                 error_code = '', error_message = '', started_at_gmt = %s, updated_at_gmt = %s
             WHERE source_type = %s AND source_record_id = %d
               AND (status IN ('pending','failed') OR (status = 'running' AND updated_at_gmt < %s))",
            $token, $provider_id, $model, $fingerprint, $now, $now,
            $submission['source_type'], $submission['source_record_id'], $stale_before
        ));
        return $updated === 1 ? $token : '';
    }

    public function save_success(array $submission, string $claim_token, array $analysis, array $usage, int $duration_ms): bool {
        global $wpdb;
        $now = current_time('mysql', true);
        $updated = $wpdb->update(WP_AIGent_Form_Analysis_Schema::analysis_table(), [
            'status' => 'succeeded', 'claim_token' => '',
            'normalized_source' => wp_json_encode($analysis['source'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'normalized_contact' => wp_json_encode($analysis['contact'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'requirements_summary' => $analysis['requirements_summary'],
            'result_json' => wp_json_encode($analysis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'token_usage' => wp_json_encode($usage), 'duration_ms' => max(0, $duration_ms),
            'error_code' => '', 'error_message' => '', 'completed_at_gmt' => $now, 'updated_at_gmt' => $now,
        ], [
            'source_type' => $submission['source_type'], 'source_record_id' => $submission['source_record_id'], 'claim_token' => $claim_token,
        ], ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s'], ['%s', '%d', '%s']);
        return $updated === 1;
    }

    public function save_failure(array $submission, string $claim_token, string $code, string $message, int $duration_ms = 0): void {
        global $wpdb;
        $wpdb->update(WP_AIGent_Form_Analysis_Schema::analysis_table(), [
            'status' => 'failed', 'claim_token' => '', 'duration_ms' => max(0, $duration_ms),
            'error_code' => sanitize_key($code), 'error_message' => $message, 'updated_at_gmt' => current_time('mysql', true),
        ], [
            'source_type' => $submission['source_type'], 'source_record_id' => $submission['source_record_id'], 'claim_token' => $claim_token,
        ], ['%s', '%s', '%d', '%s', '%s'], ['%s', '%d', '%s']);
    }

    public function latest_results(int $limit = 20): array {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . WP_AIGent_Form_Analysis_Schema::analysis_table() . " WHERE status = 'succeeded' ORDER BY completed_at_gmt DESC LIMIT %d",
            min(100, max(1, $limit))
        ), ARRAY_A) ?: [];
    }
}
