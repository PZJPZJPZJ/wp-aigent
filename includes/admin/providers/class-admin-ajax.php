<?php
defined('ABSPATH') || exit;

class WP_AIGent_Provider_Admin_Ajax {

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

        $provider_id = (int) ($_POST['provider_id'] ?? 0);
        if (!$provider_id || get_post_type($provider_id) !== 'ai_provider') {
            wp_send_json_error(['message' => 'Save a valid AI Provider before fetching models.']);
        }

        $config = AI_Chatbot_CPT_Provider::get_connection_config($provider_id, false);
        if (empty($config)) {
            wp_send_json_error(['message' => 'This provider needs an API Base URL and API Key.']);
        }

        $client = new AI_Chatbot_AI_Client($config);
        $models = $client->list_models();

        if (!empty($models)) {
            $models = array_slice(array_values(array_unique(array_filter(array_map(
                'sanitize_text_field',
                $models
            )))), 0, 500);
            update_post_meta($provider_id, 'api_provider_model_list', $models);
            wp_send_json_success(['models' => $models]);
        } else {
            wp_send_json_error(['message' => 'No models found or API unreachable.']);
        }
    }

    /**
     * AJAX handler for manually maintaining a provider's model list.
     */
    public static function save_provider_models(): void {
        if (!current_user_can('manage_options')) {
            wp_die(-1);
        }

        if (!check_ajax_referer('ai_provider_manage_models', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed.']);
        }

        $provider_id = (int) ($_POST['provider_id'] ?? 0);
        if (!$provider_id || get_post_type($provider_id) !== 'ai_provider' || !current_user_can('edit_post', $provider_id)) {
            wp_send_json_error(['message' => 'Invalid AI Provider.']);
        }

        $raw_models = isset($_POST['models']) && is_array($_POST['models']) ? $_POST['models'] : [];
        $models = [];
        foreach ($raw_models as $model) {
            $model = sanitize_text_field(wp_unslash($model));
            if ($model !== '') {
                $models[] = $model;
            }
        }

        $models = array_slice(array_values(array_unique($models)), 0, 500);
        update_post_meta($provider_id, 'api_provider_model_list', $models);
        wp_send_json_success(['models' => $models]);
    }
}
