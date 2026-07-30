<?php
defined('ABSPATH') || exit;

class AI_Chatbot_CPT_Knowledge {
    private static bool $updating_generated_title = false;

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
        add_meta_box('ai_knowledge_card', __('Description', 'wp-aigent'), [self::class, 'render_card_meta_box'], 'ai_knowledge', 'normal', 'default');
    }

    public static function render_meta_box($post): void {
        wp_nonce_field('ai_knowledge_meta', 'ai_knowledge_meta_nonce');
        include WP_AIGENT_PATH . 'templates/ai-chatbot/admin-knowledge-meta-box.php';
    }

    public static function render_card_meta_box($post): void {
        include WP_AIGENT_PATH . 'templates/ai-chatbot/admin-knowledge-card-meta-box.php';
    }

    public static function save_meta(int $post_id, $post): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!isset($_POST['ai_knowledge_meta_nonce'])
            || !wp_verify_nonce($_POST['ai_knowledge_meta_nonce'], 'ai_knowledge_meta')) return;
        if (!current_user_can('edit_post', $post_id)) return;
        if (self::$updating_generated_title) return;

        if (isset($_POST['knowledge_markdown'])) {
            $markdown = wp_kses_post(wp_unslash($_POST['knowledge_markdown']));
            update_post_meta($post_id, 'knowledge_markdown', $markdown);
            update_post_meta($post_id, 'knowledge_metadata_provider_id', absint($_POST['knowledge_metadata_provider_id'] ?? 0));
            update_post_meta($post_id, 'knowledge_metadata_model', sanitize_text_field(wp_unslash($_POST['knowledge_metadata_model'] ?? '')));
            $cards = new AI_Chatbot_Knowledge_Card_Service();
            $cards->rebuild_index($post_id, $markdown);
            update_post_meta($post_id, 'knowledge_card_status', 'generating');
            $metadata = $cards->generate_metadata($post_id, $markdown, trim((string) $post->post_title) === '');
            if (($metadata['error'] ?? '') === 'not_configured') {
                $cards->clear_generated_metadata($post_id);
                return;
            }
            if (!empty($metadata['error'])) {
                update_post_meta($post_id, 'knowledge_card_status', 'failed');
                return;
            }
            $cards->store_generated_metadata($post_id, $metadata);

            // The document title is the only title used by discovery. Generate it
            // only when the author intentionally leaves it blank.
            if (!self::$updating_generated_title && trim((string) $post->post_title) === '') {
                $title = $metadata['title'] ?? '';
                if ($title !== '') {
                    self::$updating_generated_title = true;
                    wp_update_post(['ID' => $post_id, 'post_title' => $title]);
                    self::$updating_generated_title = false;
                }
            }
        }
    }

    public static function get_defaults(): array {
        return [
            'knowledge_markdown'   => '',
        ];
    }
}
