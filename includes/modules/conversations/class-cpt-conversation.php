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
        add_action('restrict_manage_posts', [self::class, 'render_list_filters'], 10, 2);
        add_action('pre_get_posts', [self::class, 'filter_list_query']);

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

    /** Render the standard WordPress list-table filter for one global visitor ID. */
    public static function render_list_filters(string $post_type, string $which): void {
        if ($post_type !== 'ai_conversation' || $which !== 'top') return;
        $value = isset($_GET['ai_conversation_visitor_id']) && is_scalar($_GET['ai_conversation_visitor_id'])
            ? sanitize_text_field(wp_unslash($_GET['ai_conversation_visitor_id']))
            : '';
        echo '<label class="screen-reader-text" for="ai-conversation-visitor-filter">' . esc_html__('Visitor ID', 'wp-aigent') . '</label>';
        echo '<input type="search" id="ai-conversation-visitor-filter" name="ai_conversation_visitor_id" value="' . esc_attr($value) . '" placeholder="' . esc_attr__('Visitor ID', 'wp-aigent') . '" />';
    }

    /** Apply the global Visitor ID filter to the Conversations list table only. */
    public static function filter_list_query(WP_Query $query): void {
        if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'ai_conversation') return;
        $visitor_id = isset($_GET['ai_conversation_visitor_id']) && is_scalar($_GET['ai_conversation_visitor_id'])
            ? sanitize_text_field(wp_unslash($_GET['ai_conversation_visitor_id']))
            : '';
        if ($visitor_id === '') return;
        $meta_query = (array) $query->get('meta_query');
        $meta_query[] = ['key' => WP_AIGent_Visitor_Identity::CONVERSATION_META_KEY, 'value' => $visitor_id, 'compare' => '='];
        $query->set('meta_query', $meta_query);
    }

    /** Builds a list-table URL scoped to one global visitor ID. */
    public static function list_filter_url(string $visitor_id): string {
        return add_query_arg([
            'post_type' => 'ai_conversation',
            'ai_conversation_visitor_id' => $visitor_id,
        ], admin_url('edit.php'));
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
        $visitor_id   = get_post_meta($post->ID, WP_AIGent_Visitor_Identity::CONVERSATION_META_KEY, true);
        $chatbot_id   = (int) get_post_meta($post->ID, 'conversation_chatbot_id', true);
        $msg_count    = (int) get_post_meta($post->ID, 'conversation_message_count', true);
        $started_at   = get_post_meta($post->ID, 'conversation_started_at', true);
        $lead_data    = get_post_meta($post->ID, 'conversation_lead_data', true);
        $attribution  = get_post_meta($post->ID, 'conversation_attribution', true);
        if (!is_array($attribution)) $attribution = [];
        $attribution_view = self::attribution_view($attribution);
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
                        'duration_ms' => $ex['duration_ms'] ?? 0,
                        'knowledge_trace' => $ex['knowledge_trace'] ?? [],
                    ];
                } else {
                    $messages[] = [
                        'role'        => 'assistant',
                        'content'     => $ex['assistant'],
                        'token_usage' => $ex['token_usage'] ?? [],
                        'model'       => $ex['model'] ?? '',
                        'effort'      => $ex['effort'] ?? '',
                        'time'        => $msg_time,
                        'duration_ms' => $ex['duration_ms'] ?? 0,
                        'knowledge_trace' => $ex['knowledge_trace'] ?? [],
                    ];
                }
            }
        }

        include WP_AIGENT_PATH . 'templates/admin/conversations/details.php';
    }

    private static function attribution_view(array $attribution): array {
        $rows = [];
        $journey = is_array($attribution['journey'] ?? null) ? $attribution['journey'] : [];
        $is_journey_only = !empty($journey[0]) && is_array($journey[0]) && array_key_exists('time', $journey[0]);
        if ($is_journey_only) {
            foreach ($journey as $item) {
                if (!is_array($item)) continue;
                $time = (string) ($item['time'] ?? '');
                $path = (string) ($item['path'] ?? '');
                if ($time === '' || $path === '') continue;
                $rows[] = [
                    'time'         => $time,
                    'path'         => $path,
                    'source'       => (string) ($item['source'] ?? ''),
                    'referrer_url' => (string) ($item['referrer_url'] ?? ''),
                    'event'        => (string) ($item['event'] ?? ''),
                    'event_id'     => (string) ($item['event_id'] ?? ''),
                ];
            }
        }

        // Read-only compatibility for 2.0.9 First/Last Touch records.
        if (!$is_journey_only && !empty($attribution['first_touch']) && is_array($attribution['first_touch'])) {
            $first = $attribution['first_touch'];
            $rows[] = [
                'time'         => (string) ($first['observed_at_gmt'] ?? $attribution['first_visit_at_gmt'] ?? ''),
                'path'         => (string) ($first['landing_path'] ?? '/'),
                'source'       => (string) ($first['source'] ?? ''),
                'referrer_url' => (string) ($first['referrer_url'] ?? ''),
                'event'        => '',
                'event_id'     => '',
            ];
            foreach ($journey as $item) {
                if (!is_array($item)) continue;
                $time = (string) ($item['observed_at_gmt'] ?? '');
                $path = (string) ($item['path'] ?? '');
                if ($time !== '' && $path !== '') {
                    $rows[] = [
                        'time' => $time, 'path' => $path, 'source' => '',
                        'referrer_url' => '', 'event' => '', 'event_id' => '',
                    ];
                }
            }
        }

        return [
            'rows'          => $rows,
            'show_source'   => self::attribution_column_has_value($rows, 'source'),
            'show_referrer' => self::attribution_column_has_value($rows, 'referrer_url'),
            'show_event'    => self::attribution_column_has_value($rows, 'event'),
            'show_event_id' => self::attribution_column_has_value($rows, 'event_id'),
        ];
    }

    private static function attribution_column_has_value(array $rows, string $key): bool {
        foreach ($rows as $row) {
            if ((string) ($row[$key] ?? '') !== '') return true;
        }
        return false;
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

    public static function create(string $visitor_id, int $chatbot_id, array $visitor_data): int {
        $id = wp_insert_post([
            'post_title'  => __('Conversation', 'wp-aigent'),
            'post_type'   => 'ai_conversation',
            'post_status' => 'publish',
            'meta_input'  => [
                WP_AIGent_Visitor_Identity::CONVERSATION_META_KEY => $visitor_id,
                'conversation_chatbot_id'    => $chatbot_id,
                'conversation_visitor_ip'    => $visitor_data['ip'] ?? '',
                'conversation_visitor_ua'    => $visitor_data['ua'] ?? '',
                'conversation_visitor_page_url'  => $visitor_data['page_url'] ?? '',
                'conversation_message_count' => 0,
                'conversation_started_at'    => current_time('mysql'),
                'conversation_last_activity' => time(),
            ],
        ]);

        if (is_wp_error($id)) {
            return 0;
        }

        // Conversation ID alone distinguishes records; visitor identity is stored separately.
        wp_update_post([
            'ID'         => $id,
            'post_title' => sprintf(__('Conversation #%d', 'wp-aigent'), $id),
        ]);

        return $id;
    }

    /** Returns the latest non-expired conversation for one visitor and chatbot. */
    public static function find_active(string $visitor_id, int $chatbot_id, int $ttl_hours): ?int {
        $existing = get_posts([
            'post_type'      => 'ai_conversation',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_key'       => 'conversation_last_activity',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => WP_AIGent_Visitor_Identity::CONVERSATION_META_KEY, 'value' => $visitor_id, 'compare' => '='],
                ['key' => 'conversation_chatbot_id', 'value' => $chatbot_id, 'compare' => '='],
            ],
        ]);
        if (!$existing) return null;
        $conversation_id = (int) $existing[0];
        $last_activity = (int) get_post_meta($conversation_id, 'conversation_last_activity', true);
        if ($last_activity <= 0) $last_activity = strtotime((string) get_post_meta($conversation_id, 'conversation_started_at', true)) ?: 0;
        if ($ttl_hours > 0 && (!$last_activity || time() > $last_activity + ($ttl_hours * HOUR_IN_SECONDS))) return null;
        return $conversation_id;
    }

    /** Finds a live conversation or creates a new record for the visitor. */
    public static function find_or_create_active(string $visitor_id, int $chatbot_id, int $ttl_hours, array $visitor_data): int {
        $conversation_id = self::find_active($visitor_id, $chatbot_id, $ttl_hours);
        return $conversation_id ?? self::create($visitor_id, $chatbot_id, $visitor_data);
    }

    /** Save the current validated Journey projection. */
    public static function save_attribution(int $conversation_id, array $attribution): void {
        if ($conversation_id <= 0 || get_post_type($conversation_id) !== 'ai_conversation') {
            return;
        }

        update_post_meta($conversation_id, 'conversation_attribution', $attribution);
        update_post_meta($conversation_id, 'conversation_attribution_updated_at_gmt', current_time('mysql', true));
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
