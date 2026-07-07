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

        // Use a deterministic visitor session so preview goes through the same code path as frontend
        $hash = md5('admin_preview_' . $chatbot_id);
        $visitor_id = substr($hash, 0, 8) . '-' . substr($hash, 8, 4) . '-4' . substr($hash, 12, 3) . '-a' . substr($hash, 15, 3) . '-' . substr($hash, 18, 12);
        $session_id = 'sess_' . md5($visitor_id . '_' . $chatbot_id);
        $session_token = hash_hmac('sha256', $session_id, AI_CHAT_SESSION_SECRET);

        // Simulate a chat via REST
        $request = new WP_REST_Request('POST', '/ai-chat/v1/chat');
        $request->set_body_params([
            'chatbot_id'    => $chatbot_id,
            'message'       => $message,
            'session_id'    => $session_id,
            'session_token' => $session_token,
            'visitor_id'    => $visitor_id,
            'metadata'      => ['page' => admin_url(), 'referrer' => '', 'language' => 'en'],
        ]);

        $response = AI_Chatbot_Chat_API::handle_chat($request);
        $data = $response->get_data();

        wp_send_json($data);
    }

    /**
     * AJAX handler for fetching available models from the API.
     */
    public static function fetch_models(): void {
        if (!current_user_can('manage_options')) {
            wp_die(-1);
        }

        if (!check_ajax_referer('ai_chatbot_fetch_models', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed.']);
        }

        $chatbot_id = (int) ($_POST['chatbot_id'] ?? 0);
        $platform = sanitize_text_field($_POST['platform'] ?? 'openai');
        $base_url = sanitize_text_field($_POST['api_base_url'] ?? '');
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');

        // Load stored config if chatbot_id provided (decrypts key server-side)
        if ($chatbot_id && get_post_type($chatbot_id) === 'ai_chatbot') {
            $config = AI_Chatbot_CPT_Chatbot::get_meta($chatbot_id);
            $platform = $config['chatbot_platform'] ?? $platform;
            $base_url = $config['chatbot_api_base_url'] ?? $base_url;
            $api_key = $config['chatbot_api_key'] ?? $api_key;
        }

        if (empty($base_url) || empty($api_key)) {
            wp_send_json_error(['message' => 'API Base URL and API Key are required.']);
        }

        $client = new AI_Chatbot_AI_Client([
            'chatbot_platform'     => $platform,
            'chatbot_api_base_url' => $base_url,
            'chatbot_api_key'      => $api_key,
        ]);

        $models = $client->list_models();

        if (!empty($models)) {
            if ($chatbot_id) {
                update_post_meta($chatbot_id, 'chatbot_model_list', $models);
            }
            wp_send_json_success(['models' => $models]);
        } else {
            wp_send_json_error(['message' => 'No models found or API unreachable.']);
        }
    }

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
