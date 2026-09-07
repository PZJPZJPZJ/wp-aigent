<?php
defined('ABSPATH') || exit;

class WP_AIGent_Conversation_Notification_Ajax {

    /**
     * AJAX handler for manually triggering notification on a conversation.
     */
    public static function trigger_notify(): void {
        if (!current_user_can('manage_options')) {
            wp_die(-1);
        }

        if (!check_ajax_referer('ai_chatbot_trigger_notify', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed.']);
        }

        $post_id = (int) ($_POST['post_id'] ?? 0);
        if (!$post_id || get_post_type($post_id) !== 'ai_conversation') {
            wp_send_json_error(['message' => 'Invalid conversation.']);
        }

        $notifier = new AI_Chatbot_Notifier();
        $result = $notifier->force_notify($post_id);

        if ($result) {
            wp_send_json_success(['message' => 'Notification sent successfully.']);
        } else {
            wp_send_json_error(['message' => 'No notification channel configured (webhook or email), or sending failed.']);
        }
    }
}
