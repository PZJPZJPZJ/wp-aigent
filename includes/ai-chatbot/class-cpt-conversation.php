<?php
defined('ABSPATH') || exit;

class AI_Chatbot_CPT_Conversation {

    public static function register(): void {
        register_post_type('ai_conversation', [
            'labels' => [
                'name'               => __('Conversations', 'wp-aigent'),
                'singular_name'      => __('Conversation', 'wp-aigent'),
                'edit_item'          => __('View Conversation', 'wp-aigent'),
                'all_items'          => __('Conversations', 'wp-aigent'),
            ],
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=ai_chatbot',
            'supports'           => ['title'],
            'capability_type'    => 'post',
            'capabilities'       => [
                'create_posts' => 'do_not_allow',
            ],
            'map_meta_cap'       => true,
        ]);

        add_action('add_meta_boxes_ai_conversation', [self::class, 'add_meta_boxes']);
        add_action('save_post_ai_conversation', [self::class, 'prevent_manual_edit'], 10, 3);
        add_action('admin_head-post.php', [self::class, 'hide_submit_meta_box']);
        add_action('admin_head-post-new.php', [self::class, 'hide_submit_meta_box']);

        // Clear pending inactivity cron when a conversation is deleted or trashed
        add_action('before_delete_post', [self::class, 'cleanup_inactivity_cron']);
        add_action('wp_trash_post', [self::class, 'cleanup_inactivity_cron']);
    }

    public static function hide_submit_meta_box(): void {
        $screen = get_current_screen();
        if ($screen && $screen->post_type === 'ai_conversation') {
            remove_meta_box('submitdiv', 'ai_conversation', 'side');
        }
    }

    public static function add_meta_boxes(): void {
        add_meta_box(
            'ai_conversation_details',
            __('Conversation Details', 'wp-aigent'),
            [self::class, 'render_meta_box'],
            'ai_conversation',
            'normal',
            'high'
        );
    }

    public static function render_meta_box($post): void {
        $session_id   = get_post_meta($post->ID, 'conversation_session_id', true);
        $chatbot_id   = (int) get_post_meta($post->ID, 'conversation_chatbot_id', true);
        $msg_count    = (int) get_post_meta($post->ID, 'conversation_message_count', true);
        $started_at   = get_post_meta($post->ID, 'conversation_started_at', true);
        $lead_data    = get_post_meta($post->ID, 'conversation_lead_data', true);

        $ip       = get_post_meta($post->ID, 'conversation_visitor_ip', true);
        $ua       = get_post_meta($post->ID, 'conversation_visitor_ua', true);
        $page_url = get_post_meta($post->ID, 'conversation_visitor_page_url', true);

        $bot      = $chatbot_id ? get_post($chatbot_id) : null;
        $bot_name = $bot ? $bot->post_title : '—';

        // Build messages array from exchanges
        $messages = [];
        $exchanges = get_post_meta($post->ID, 'conversation_exchange');
        if (!empty($exchanges)) {
            foreach ($exchanges as $ex) {
                $msg_time = $ex['time'] ?? '';
                $messages[] = ['role' => 'user', 'content' => $ex['user'], 'time' => $msg_time];
                if (!empty($ex['error'])) {
                    $messages[] = [
                        'role'  => 'assistant',
                        'error' => $ex['error'],
                        'model' => $ex['model'] ?? '',
                        'time'  => $msg_time,
                    ];
                } else {
                    $messages[] = [
                        'role'        => 'assistant',
                        'content'     => $ex['assistant'],
                        'token_usage' => $ex['token_usage'] ?? [],
                        'model'       => $ex['model'] ?? '',
                        'effort'      => $ex['effort'] ?? '',
                        'time'        => $msg_time,
                    ];
                }
            }
        }

        include WP_AIGENT_PATH . 'templates/ai-chatbot/admin-conversation-meta-box.php';
    }

    public static function prevent_manual_edit(int $post_id, $post, bool $update): void {
        // Conversations are system-managed only
    }

    /**
     * Clear pending inactivity WP Cron when a conversation is permanently deleted or trashed.
     */
    public static function cleanup_inactivity_cron(int $post_id): void {
        if (get_post_type($post_id) !== 'ai_conversation') {
            return;
        }
        wp_clear_scheduled_hook('ai_chatbot_inactivity_notify', [$post_id]);
    }

    public static function create(string $session_id, int $chatbot_id, array $visitor_data): int {
        $title = $session_id;
        $id = wp_insert_post([
            'post_title'  => $title,
            'post_type'   => 'ai_conversation',
            'post_status' => 'publish',
            'meta_input'  => [
                'conversation_session_id'    => $session_id,
                'conversation_chatbot_id'    => $chatbot_id,
                'conversation_visitor_ip'    => $visitor_data['ip'] ?? '',
                'conversation_visitor_ua'    => $visitor_data['ua'] ?? '',
                'conversation_visitor_page_url'  => $visitor_data['page_url'] ?? '',
                'conversation_message_count' => 0,
                'conversation_started_at'    => current_time('mysql'),
            ],
        ]);

        if (is_wp_error($id)) {
            return 0;
        }

        // Set title to "Session ID | #Conversation ID" for easy identification
        wp_update_post([
            'ID'         => $id,
            'post_title' => $session_id . ' | #' . $id,
        ]);

        return $id;
    }

    /**
     * Format a token count with unit suffix (K/M) for compact display.
     */
    public static function format_token_number(int $count): string {
        if ($count >= 1000000) {
            return number_format($count / 1000000, 1) . 'M';
        }
        if ($count >= 1000) {
            return number_format($count / 1000, 1) . 'K';
        }
        return (string) $count;
    }
}
