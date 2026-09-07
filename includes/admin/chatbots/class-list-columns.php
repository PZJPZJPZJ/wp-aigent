<?php
defined('ABSPATH') || exit;

class AI_Chatbot_Admin_Columns {

    public function __construct() {
        add_filter('manage_ai_chatbot_posts_columns', [$this, 'chatbot_columns']);
        add_action('manage_ai_chatbot_posts_custom_column', [$this, 'chatbot_column_data'], 10, 2);
    }

    public function chatbot_columns(array $columns): array {
        $columns['platform'] = __('AI Provider', 'wp-aigent');
        $columns['model']    = __('Model', 'wp-aigent');
        return $columns;
    }

    public function chatbot_column_data(string $column, int $post_id): void {
        $config = AI_Chatbot_CPT_Chatbot::get_meta($post_id);
        switch ($column) {
            case 'platform':
                $provider_id = (int) ($config['chatbot_primary_api_provider_id'] ?? 0);
                $provider = $provider_id ? get_post($provider_id) : null;
                echo $provider
                    ? esc_html($provider->post_title)
                    : '<span style="color:#d63638;">' . esc_html__('Not configured', 'wp-aigent') . '</span>';
                break;
            case 'model':
                echo esc_html($config['chatbot_primary_api_model'] ?? '—');
                break;
        }
    }
}
