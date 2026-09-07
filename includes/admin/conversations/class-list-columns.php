<?php
defined('ABSPATH') || exit;

class WP_AIGent_Conversation_Admin_Columns {

    public function __construct() {
        add_filter('manage_ai_conversation_posts_columns', [$this, 'conversation_columns']);
        add_action('manage_ai_conversation_posts_custom_column', [$this, 'conversation_column_data'], 10, 2);
    }

    public function conversation_columns(array $columns): array {
        $columns['title']        = __('Conversation ID', 'wp-aigent');
        $columns['chatbot']      = __('Chatbot', 'wp-aigent');
        $columns['visitor_session'] = __('Visitor ID', 'wp-aigent');
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
            case 'visitor_session':
                $visitor_id = (string) get_post_meta($post_id, WP_AIGent_Visitor_Identity::CONVERSATION_META_KEY, true);
                if ($visitor_id === '') {
                    echo '—';
                    break;
                }
                echo '<a href="' . esc_url(AI_Chatbot_CPT_Conversation::list_filter_url($visitor_id)) . '"><code>' . esc_html($visitor_id) . '</code></a>';
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
