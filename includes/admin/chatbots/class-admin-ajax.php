<?php
defined('ABSPATH') || exit;

class AI_Chatbot_Admin_Ajax {

    /**
     * AJAX handler for chatbot preview.
     */
    public static function preview(): void {
        if (!current_user_can('manage_options')) {
            wp_die(-1);
        }

        if (!check_ajax_referer('ai_chatbot_preview', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed.']);
        }

        $chatbot_id = (int) ($_POST['chatbot_id'] ?? 0);
        if (!$chatbot_id || get_post_type($chatbot_id) !== 'ai_chatbot') {
            wp_send_json_error(['message' => 'Invalid chatbot.']);
        }

        $message = sanitize_text_field($_POST['message'] ?? 'Hello');
        if ($message === '' || mb_strlen($message) > (int) WP_AIGent_Security_Settings::get('max_message_length')) {
            wp_send_json_error(['message' => __('Invalid preview message.', 'wp-aigent')], 400);
        }

        // Admin preview is already protected by capability and nonce checks, so it
        // calls the application service with a server-owned preview identity.
        $visitor_id = WP_AIGent_Visitor_Identity::preview_id(get_current_user_id(), $chatbot_id);
        $service = new AI_Chatbot_Chat_Service();
        $result = $service->send(
            $chatbot_id,
            $message,
            $visitor_id,
            ['page' => admin_url(), 'referrer' => '', 'language' => 'en'],
            WP_AIGent_Bootstrap::get_client_ip()
        );

        wp_send_json($result['body'], (int) $result['status']);
    }

}
