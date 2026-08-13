<?php
defined('ABSPATH') || exit;

/** Stable read contract for an external form-submission source. */
interface WP_AIGent_Form_Submission_Source {

    public function is_available(): bool;

    /** Scan source rows in stable ID order. Cursor is exclusive. */
    public function scan(array $filters, array $cursor = [], int $limit = 500, string $order = 'asc'): array;

    public function read(int $submission_id): ?array;

    public function existing_ids(array $submission_ids): array;

    public function filter_options(string $from_gmt, string $to_gmt): array;

    public function detail_url(int $submission_id): string;
}
