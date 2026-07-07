<?php
defined('ABSPATH') || exit;

class AI_Chatbot_CPT_Knowledge {

    public static function register(): void {
        register_post_type('ai_knowledge', [
            'labels' => [
                'name'               => __('Knowledge Base', 'wp-aigent'),
                'singular_name'      => __('Knowledge Doc', 'wp-aigent'),
                'add_new'            => __('New Document', 'wp-aigent'),
                'add_new_item'       => __('Add New Document', 'wp-aigent'),
                'edit_item'          => __('Edit Document', 'wp-aigent'),
                'view_item'          => __('View Document', 'wp-aigent'),
                'all_items'          => __('Knowledge Base', 'wp-aigent'),
            ],
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=ai_chatbot',
            'supports'           => ['title'],
            'capability_type'    => 'post',
            'map_meta_cap'       => true,
        ]);

        add_action('add_meta_boxes_ai_knowledge', [self::class, 'add_meta_boxes']);
        add_action('save_post_ai_knowledge', [self::class, 'save_meta'], 10, 2);
    }

    public static function add_meta_boxes(): void {
        add_meta_box(
            'ai_knowledge_content',
            __('Content (Markdown)', 'wp-aigent'),
            [self::class, 'render_meta_box'],
            'ai_knowledge',
            'normal',
            'high'
        );
    }

    public static function render_meta_box($post): void {
        wp_nonce_field('ai_knowledge_meta', 'ai_knowledge_meta_nonce');
        include WP_AIGENT_PATH . 'templates/ai-chatbot/admin-knowledge-meta-box.php';
    }

    public static function save_meta(int $post_id, $post): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!isset($_POST['ai_knowledge_meta_nonce'])
            || !wp_verify_nonce($_POST['ai_knowledge_meta_nonce'], 'ai_knowledge_meta')) return;
        if (!current_user_can('edit_post', $post_id)) return;

        if (isset($_POST['knowledge_markdown'])) {
            update_post_meta($post_id, 'knowledge_markdown', wp_kses_post($_POST['knowledge_markdown']));
        }
    }

    public static function get_defaults(): array {
        return [
            'knowledge_markdown'   => '',
        ];
    }
}
