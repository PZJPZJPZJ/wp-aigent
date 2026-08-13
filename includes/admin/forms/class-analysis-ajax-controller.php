<?php
defined('ABSPATH') || exit;

/** Thin AJAX controller for explicitly started analysis jobs. */
class WP_AIGent_Form_Analysis_Ajax_Controller {

    private WP_AIGent_Form_Analysis_Service $service;

    public function __construct(WP_AIGent_Form_Analysis_Service $service) {
        $this->service = $service;
        add_action('wp_ajax_wp_aigent_form_analysis_create', [$this, 'create_job']);
        add_action('wp_ajax_wp_aigent_form_analysis_batch', [$this, 'run_batch']);
    }

    public function create_job(): void {
        $this->authorize();
        $result = $this->service->create_job([
            'date_from' => sanitize_text_field(wp_unslash($_POST['date_from'] ?? '')),
            'date_to' => sanitize_text_field(wp_unslash($_POST['date_to'] ?? '')),
            'analysis_mode' => sanitize_key(wp_unslash($_POST['analysis_mode'] ?? 'update')),
            'search' => sanitize_text_field(wp_unslash($_POST['search'] ?? '')),
            'form' => sanitize_text_field(wp_unslash($_POST['form'] ?? '')),
            'page_url' => esc_url_raw(wp_unslash($_POST['page_url'] ?? '')),
            'spam' => sanitize_key(wp_unslash($_POST['is_spam'] ?? '')),
            'intent' => sanitize_key(wp_unslash($_POST['intent'] ?? '')),
            'analysis_status' => sanitize_key(wp_unslash($_POST['analysis_status'] ?? '')),
        ]);
        if (!empty($result['error'])) wp_send_json_error(['message' => $result['error']], 400);
        wp_send_json_success($result);
    }

    public function run_batch(): void {
        $this->authorize();
        $result = $this->service->run_batch(absint($_POST['job_id'] ?? 0));
        if (!empty($result['error'])) wp_send_json_error(['message' => $result['error']], 400);
        wp_send_json_success($result);
    }

    private function authorize(): void {
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => __('You are not allowed to analyze form submissions.', 'wp-aigent')], 403);
        check_ajax_referer('wp_aigent_form_analysis', 'nonce');
    }
}
