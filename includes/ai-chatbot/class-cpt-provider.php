<?php
defined('ABSPATH') || exit;

class AI_Chatbot_CPT_Provider {

    public static function register(): void {
        register_post_type('ai_provider', [
            'labels' => [
                'name'               => __('API Providers', 'wp-aigent'),
                'singular_name'      => __('API Provider', 'wp-aigent'),
                'add_new'            => __('Add Provider', 'wp-aigent'),
                'add_new_item'       => __('Add API Provider', 'wp-aigent'),
                'edit_item'          => __('Edit API Provider', 'wp-aigent'),
                'view_item'          => __('View API Provider', 'wp-aigent'),
                'all_items'          => __('API Providers', 'wp-aigent'),
                'menu_name'          => __('API Providers', 'wp-aigent'),
            ],
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => 'edit.php?post_type=ai_chatbot',
            'supports'           => ['title'],
            'capability_type'    => 'post',
            'map_meta_cap'       => true,
        ]);

        add_action('add_meta_boxes_ai_provider', [self::class, 'add_meta_boxes']);
        add_action('save_post_ai_provider', [self::class, 'save_meta'], 10, 2);
        add_action('admin_menu', [self::class, 'move_submenu_to_first'], 101);
    }

    public static function add_meta_boxes(): void {
        add_meta_box(
            'ai_provider_config',
            __('Provider Connection', 'wp-aigent'),
            [self::class, 'render_meta_box'],
            'ai_provider',
            'normal',
            'high'
        );
    }

    /** Keep API Providers as the first item under the AIgent menu. */
    public static function move_submenu_to_first(): void {
        global $submenu;

        $parent_slug = 'edit.php?post_type=ai_chatbot';
        $provider_slug = 'edit.php?post_type=ai_provider';
        if (empty($submenu[$parent_slug]) || !is_array($submenu[$parent_slug])) {
            return;
        }

        $provider_item = null;
        foreach ($submenu[$parent_slug] as $index => $item) {
            if (($item[2] ?? '') === $provider_slug) {
                $provider_item = $item;
                unset($submenu[$parent_slug][$index]);
                break;
            }
        }

        if ($provider_item === null) {
            return;
        }

        $items = array_values($submenu[$parent_slug]);
        array_splice($items, 0, 0, [$provider_item]);
        $submenu[$parent_slug] = $items;
    }

    public static function render_meta_box($post): void {
        wp_nonce_field('ai_provider_meta', 'ai_provider_meta_nonce');
        include WP_AIGENT_PATH . 'templates/ai-chatbot/admin-provider-meta-box.php';
    }

    public static function save_meta(int $post_id, $post): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!isset($_POST['ai_provider_meta_nonce'])
            || !wp_verify_nonce($_POST['ai_provider_meta_nonce'], 'ai_provider_meta')) return;
        if (!current_user_can('edit_post', $post_id)) return;

        // Keep the previous provider_* metadata untouched and unread. The new
        // API provider format deliberately has its own metadata namespace.
        $protocol = sanitize_key($_POST['api_provider_protocol'] ?? 'openai_completions');
        if (!in_array($protocol, self::get_protocols(), true)) {
            $protocol = 'openai_completions';
        }

        update_post_meta($post_id, 'api_provider_protocol', $protocol);
        update_post_meta($post_id, 'api_provider_base_url', esc_url_raw($_POST['api_provider_base_url'] ?? ''));

        // Empty keys intentionally preserve the encrypted key already stored.
        if (isset($_POST['api_provider_api_key']) && $_POST['api_provider_api_key'] !== '') {
            update_post_meta($post_id, 'api_provider_api_key', self::encrypt(sanitize_text_field($_POST['api_provider_api_key'])));
        }
    }

    public static function get_protocols(): array {
        return ['openai_completions', 'openai_responses', 'anthropic', 'gemini'];
    }

    public static function get_defaults(): array {
        return [
            'api_provider_protocol' => 'openai_completions',
            'api_provider_base_url' => 'https://api.openai.com/v1',
            'api_provider_api_key'  => '',
            'api_provider_model_list' => [],
        ];
    }

    public static function get_meta(int $post_id): array {
        $defaults = self::get_defaults();
        $all_meta = get_post_meta($post_id);
        $meta = [];

        foreach ($defaults as $key => $default) {
            $raw = isset($all_meta[$key][0]) ? $all_meta[$key][0] : '';
            $value = $raw !== '' ? maybe_unserialize($raw) : '';
            $meta[$key] = $value !== '' ? $value : $default;
        }

        if (!empty($meta['api_provider_api_key'])) {
            $meta['api_provider_api_key'] = self::decrypt($meta['api_provider_api_key']);
        }

        return $meta;
    }

    /**
     * Return an AI client config for a published provider. Credentials never
     * live on the consuming feature (chatbot, knowledge processor, etc.).
     */
    public static function get_connection_config(int $provider_id, bool $require_published = true): array {
        $provider = get_post($provider_id);
        if (!$provider || $provider->post_type !== 'ai_provider'
            || ($require_published && $provider->post_status !== 'publish')) {
            return [];
        }

        $meta = self::get_meta($provider_id);
        if (empty($meta['api_provider_base_url']) || empty($meta['api_provider_api_key'])) {
            return [];
        }

        return [
            'api_protocol' => $meta['api_provider_protocol'],
            'api_base_url' => $meta['api_provider_base_url'],
            'api_key'      => $meta['api_provider_api_key'],
        ];
    }

    private static function encrypt(string $value): string {
        if (!function_exists('openssl_encrypt')) return $value;
        $key = defined('AI_CHAT_ENCRYPT_KEY') ? AI_CHAT_ENCRYPT_KEY : wp_salt('secure_auth');
        $cipher = 'aes-256-cbc';
        $iv_len = openssl_cipher_iv_length($cipher);
        $iv = openssl_random_pseudo_bytes($iv_len);
        $encrypted = openssl_encrypt($value, $cipher, $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    private static function decrypt(string $value): string {
        if (!function_exists('openssl_decrypt')) return $value;
        $cipher = 'aes-256-cbc';
        $iv_len = openssl_cipher_iv_length($cipher);
        $data = base64_decode($value);
        if ($data === false || strlen($data) <= $iv_len) return $value;

        $iv = substr($data, 0, $iv_len);
        $encrypted = substr($data, $iv_len);
        $key = defined('AI_CHAT_ENCRYPT_KEY') ? AI_CHAT_ENCRYPT_KEY : wp_salt('secure_auth');
        $result = openssl_decrypt($encrypted, $cipher, $key, 0, $iv);

        return $result !== false ? $result : $value;
    }
}
