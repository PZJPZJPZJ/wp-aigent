<?php
defined('ABSPATH') || exit;

/** Owns the database schema for manual Elementor form analysis. */
class WP_AIGent_Form_Analysis_Schema {

    public const SCHEMA_VERSION = '4';
    private const VERSION_OPTION = 'wp_aigent_form_analysis_schema_version';

    public static function analysis_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'aigent_form_analyses';
    }

    public static function job_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'aigent_form_analysis_jobs';
    }

    public static function job_item_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'aigent_form_analysis_job_items';
    }

    public static function maybe_install(): void {
        if (get_option(self::VERSION_OPTION) !== self::SCHEMA_VERSION) self::install();
    }

    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $previous_version = (string) get_option(self::VERSION_OPTION, '');
        $charset = $wpdb->get_charset_collate();
        $analysis_table = self::analysis_table();
        $job_table = self::job_table();
        $job_item_table = self::job_item_table();

        if (self::table_exists($analysis_table) && self::column_exists($analysis_table, 'source_type')) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$analysis_table} WHERE source_type <> %s", 'elementor_submission'));
        }
        if (self::table_exists($analysis_table) && self::column_exists($analysis_table, 'source_record_id') && !self::column_exists($analysis_table, 'submission_id')) {
            $wpdb->query("ALTER TABLE {$analysis_table} CHANGE source_record_id submission_id bigint(20) unsigned NOT NULL");
        }

        dbDelta("CREATE TABLE {$analysis_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            submission_id bigint(20) unsigned NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            attempt_count int(10) unsigned NOT NULL DEFAULT 0,
            claim_token char(36) NOT NULL DEFAULT '',
            requirements_summary longtext NULL,
            is_spam tinyint(1) unsigned NOT NULL DEFAULT 0,
            spam_reason text NULL,
            intent_level varchar(20) NOT NULL DEFAULT '',
            intent_summary text NULL,
            provider_id bigint(20) unsigned NOT NULL DEFAULT 0,
            model varchar(191) NOT NULL DEFAULT '',
            token_usage text NULL,
            duration_ms int(10) unsigned NOT NULL DEFAULT 0,
            error_code varchar(80) NOT NULL DEFAULT '',
            error_message text NULL,
            started_at_gmt datetime NULL,
            completed_at_gmt datetime NULL,
            created_at_gmt datetime NOT NULL,
            updated_at_gmt datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY submission (submission_id),
            KEY status (status),
            KEY intent (intent_level),
            KEY spam (is_spam)
        ) {$charset};");

        dbDelta("CREATE TABLE {$job_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            status varchar(20) NOT NULL DEFAULT 'pending',
            analysis_mode varchar(20) NOT NULL DEFAULT 'update',
            filter_json longtext NULL,
            provider_id bigint(20) unsigned NOT NULL DEFAULT 0,
            model varchar(191) NOT NULL DEFAULT '',
            reasoning_effort varchar(20) NOT NULL DEFAULT 'off',
            output_tokens int(10) unsigned NOT NULL DEFAULT 1600,
            total_count int(10) unsigned NOT NULL DEFAULT 0,
            cursor_id bigint(20) unsigned NOT NULL DEFAULT 0,
            inspected_count int(10) unsigned NOT NULL DEFAULT 0,
            succeeded_count int(10) unsigned NOT NULL DEFAULT 0,
            failed_count int(10) unsigned NOT NULL DEFAULT 0,
            skipped_count int(10) unsigned NOT NULL DEFAULT 0,
            error_message text NULL,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            started_at_gmt datetime NULL,
            completed_at_gmt datetime NULL,
            created_at_gmt datetime NOT NULL,
            updated_at_gmt datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY created_at (created_at_gmt)
        ) {$charset};");

        dbDelta("CREATE TABLE {$job_item_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id bigint(20) unsigned NOT NULL,
            submission_id bigint(20) unsigned NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at_gmt datetime NOT NULL,
            updated_at_gmt datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY job_submission (job_id,submission_id),
            KEY job_status (job_id,status),
            KEY submission (submission_id)
        ) {$charset};");

        if ($previous_version !== self::SCHEMA_VERSION) {
            $wpdb->query("DELETE FROM {$job_item_table}");
            $wpdb->query("DELETE FROM {$job_table}");
            $wpdb->query($wpdb->prepare(
                "UPDATE {$analysis_table} SET status = 'failed', claim_token = '', error_code = %s, error_message = %s, updated_at_gmt = %s WHERE status = 'running'",
                'schema_upgraded', __('The previous analysis job was stopped by the submissions data upgrade.', 'wp-aigent'), current_time('mysql', true)
            ));
        }

        self::drop_index($analysis_table, 'source_record');
        self::drop_index($analysis_table, 'source_created');
        foreach (['source_type', 'source_created_at_gmt', 'source_fingerprint', 'normalized_source', 'normalized_contact', 'source_assessment', 'result_json', 'analysis_version'] as $column) self::drop_column($analysis_table, $column);
        foreach (['date_from_gmt', 'date_to_gmt'] as $column) self::drop_column($job_table, $column);
        if (self::column_exists($analysis_table, 'submission_id')
            && self::column_exists($analysis_table, 'intent_level')
            && !self::column_exists($analysis_table, 'source_record_id')
            && self::table_exists($job_item_table)) {
            update_option(self::VERSION_OPTION, self::SCHEMA_VERSION, false);
        }
    }

    private static function table_exists(string $table): bool {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
    }

    private static function column_exists(string $table, string $column): bool {
        global $wpdb;
        return (bool) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
    }

    private static function drop_column(string $table, string $column): void {
        global $wpdb;
        if (self::column_exists($table, $column)) $wpdb->query("ALTER TABLE {$table} DROP COLUMN `{$column}`");
    }

    private static function drop_index(string $table, string $index): void {
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare("SHOW INDEX FROM {$table} WHERE Key_name = %s", $index));
        if ($exists) $wpdb->query("ALTER TABLE {$table} DROP INDEX `{$index}`");
    }

}
