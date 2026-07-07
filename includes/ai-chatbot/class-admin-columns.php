<?php
defined('ABSPATH') || exit;

class AI_Chatbot_Admin_Columns {

    public function __construct() {
        // Chatbot columns
        add_filter('manage_ai_chatbot_posts_columns', [$this, 'chatbot_columns']);
        add_action('manage_ai_chatbot_posts_custom_column', [$this, 'chatbot_column_data'], 10, 2);

        // Conversation columns
        add_filter('manage_ai_conversation_posts_columns', [$this, 'conversation_columns']);
        add_action('manage_ai_conversation_posts_custom_column', [$this, 'conversation_column_data'], 10, 2);
    }

    public function chatbot_columns(array $columns): array {
        $columns['platform'] = __('Platform', 'wp-aigent');
        $columns['model']    = __('Model', 'wp-aigent');
        $columns['layout']   = __('Layout', 'wp-aigent');
        return $columns;
    }

    public function chatbot_column_data(string $column, int $post_id): void {
        $config = AI_Chatbot_CPT_Chatbot::get_meta($post_id);
        switch ($column) {
            case 'platform':
                echo esc_html($config['chatbot_platform'] ?? '—');
                break;
            case 'model':
                echo esc_html($config['chatbot_model'] ?? '—');
                break;
            case 'layout':
                echo esc_html($config['chatbot_layout_mode'] ?? 'inline');
                break;
        }
    }

    public function conversation_columns(array $columns): array {
        $columns['chatbot']      = __('Chatbot', 'wp-aigent');
        $columns['lead_score']   = __('Lead Score', 'wp-aigent');
        $columns['notification'] = __('Notification', 'wp-aigent');
        $columns['messages']     = __('Messages', 'wp-aigent');
        return $columns;
    }

    public function conversation_column_data(string $column, int $post_id): void {
        switch ($column) {
            case 'chatbot':
                $bid = get_post_meta($post_id, 'conversation_chatbot_id', true);
                $bot = $bid ? get_post($bid) : null;
                if ($bot) {
                    $edit_url = admin_url('post.php?post=' . (int) $bid . '&action=edit');
                    echo '<a href="' . esc_url($edit_url) . '">' . esc_html($bot->post_title) . '</a>';
                } else {
                    echo '—';
                }
                break;
            case 'lead_score':
                $lead = get_post_meta($post_id, 'conversation_lead_data', true);
                echo $lead ? esc_html($lead['lead_score'] ?? '—') : '—';
                break;
            case 'notification':
                $log = get_post_meta($post_id, 'conversation_notification_log', true);
                $count = is_array($log) ? count($log) : 0;
                echo $count > 0
                    ? '<span style="color:#46b450;">✓ ' . esc_html($count) . '</span>'
                    : '<span style="color:#ccc;">—</span>';
                break;
            case 'messages':
                echo (int) get_post_meta($post_id, 'conversation_message_count', true);
                break;
        }
    }
}
