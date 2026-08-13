<?php
defined('ABSPATH') || exit;

/** Owns the database schema for manual Elementor form analysis. */
class WP_AIGent_Form_Analysis_Schema {

    public const SCHEMA_VERSION = '1';
    private const VERSION_OPTION = 'wp_aigent_form_analysis_schema_version';

    public static function analysis_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'aigent_form_analyses';
    }

    public static function job_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'aigent_form_analysis_jobs';
    }

    public static function maybe_install(): void {
        if (get_option(self::VERSION_OPTION) !== self::SCHEMA_VERSION) self::install();
    }

    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $analysis_table = self::analysis_table();
        $job_table = self::job_table();

        dbDelta("CREATE TABLE {$analysis_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_type varchar(40) NOT NULL,
            source_record_id bigint(20) unsigned NOT NULL,
            source_created_at_gmt datetime NOT NULL,
            source_fingerprint char(64) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            attempt_count int(10) unsigned NOT NULL DEFAULT 0,
            claim_token char(36) NOT NULL DEFAULT '',
            provider_id bigint(20) unsigned NOT NULL DEFAULT 0,
            model varchar(191) NOT NULL DEFAULT '',
            normalized_source longtext NULL,
            normalized_contact longtext NULL,
            requirements_summary longtext NULL,
            result_json longtext NULL,
            token_usage text NULL,
            duration_ms int(10) unsigned NOT NULL DEFAULT 0,
            error_code varchar(80) NOT NULL DEFAULT '',
            error_message text NULL,
            started_at_gmt datetime NULL,
            completed_at_gmt datetime NULL,
            created_at_gmt datetime NOT NULL,
            updated_at_gmt datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY source_record (source_type,source_record_id),
            KEY status (status),
            KEY source_created (source_created_at_gmt)
        ) {$charset};");

        dbDelta("CREATE TABLE {$job_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            status varchar(20) NOT NULL DEFAULT 'pending',
            date_from_gmt datetime NOT NULL,
            date_to_gmt datetime NOT NULL,
            provider_id bigint(20) unsigned NOT NULL DEFAULT 0,
            model varchar(191) NOT NULL DEFAULT '',
            reasoning_effort varchar(20) NOT NULL DEFAULT 'off',
            output_tokens int(10) unsigned NOT NULL DEFAULT 1600,
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
        update_option(self::VERSION_OPTION, self::SCHEMA_VERSION, false);
    }
}
