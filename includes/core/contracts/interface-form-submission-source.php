<?php
defined('ABSPATH') || exit;

/** Stable read contract for an external form-submission source. */
interface WP_AIGent_Form_Submission_Source {

    public function is_available(): bool;

    public function read_after(int $cursor_id, string $from_gmt, string $to_gmt, int $limit = 20): array;
}
