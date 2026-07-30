<?php
defined('ABSPATH') || exit;

class WP_AIGent_Plugin {

    private static ?WP_AIGent_Plugin $instance = null;

    public static function init(): void {
        if (self::$instance === null) {
            self::$instance = new self();
        }
    }

    private function __construct() {
        $this->load_dependencies();
        $this->register_hooks();
    }

    private function load_dependencies(): void {
        $includes = WP_AIGENT_PATH . 'includes/';

        // CPTs
        require_once $includes . 'ai-chatbot/class-cpt-provider.php';
        require_once $includes . 'ai-chatbot/class-cpt-chatbot.php';
        require_once $includes . 'ai-chatbot/class-cpt-knowledge.php';
        require_once $includes . 'ai-chatbot/class-cpt-conversation.php';

        // Core engine
        require_once $includes . 'ai-chatbot/class-ai-client.php';
        require_once $includes . 'ai-chatbot/class-knowledge-indexer.php';
        require_once $includes . 'ai-chatbot/class-knowledge-card-service.php';
        require_once $includes . 'ai-chatbot/class-knowledge-catalog.php';
        require_once $includes . 'ai-chatbot/class-knowledge-router.php';
        require_once $includes . 'ai-chatbot/class-knowledge-retriever.php';
        require_once $includes . 'ai-chatbot/class-knowledge-loader.php';
        require_once $includes . 'ai-chatbot/class-memory-manager.php';
        require_once $includes . 'ai-chatbot/class-lead-processor.php';
        require_once $includes . 'ai-chatbot/class-notifier.php';
        require_once $includes . 'ai-chatbot/class-chat-api.php';

        // Widget (Elementor integration)
        require_once $includes . 'ai-chatbot/class-widget.php';

        // AI Forms module
        require_once $includes . 'ai-form/class-country-resolver.php';
        require_once $includes . 'ai-form/class-elementor-form-enhancer.php';
        require_once $includes . 'ai-form/class-ai-form.php';
        new WP_AIGent_AI_Form();

        // GitHub updater (attaches hooks unconditionally so WP can detect updates)
        require_once $includes . 'class-github-updater.php';
        new WP_Plugin_Github_Updater(WP_AIGENT_FILE);

        // Admin
        if (is_admin()) {
            require_once $includes . 'ai-chatbot/class-admin-columns.php';
            require_once $includes . 'ai-chatbot/class-admin-ajax.php';
            require_once $includes . 'ai-chatbot/class-export.php';
            new AI_Chatbot_Admin_Columns();
            new AI_Chatbot_Export();
        }
    }

    private function register_hooks(): void {
        AI_Chatbot_Knowledge_Indexer::maybe_install();
        add_action('init', [$this, 'register_cpts']);
        add_action('init', [$this, 'register_widget']);
        add_action('rest_api_init', [$this, 'register_api_routes']);
        add_action('wp_enqueue_scripts', [self::class, 'register_chatbot_assets']);
        add_action('elementor/frontend/after_register_scripts', [self::class, 'register_chatbot_assets']);
        add_action('elementor/frontend/after_register_styles', [self::class, 'register_chatbot_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_notices', [$this, 'elementor_dependency_notice']);
        add_filter('plugin_action_links_' . plugin_basename(WP_AIGENT_FILE), [$this, 'plugin_action_links']);

        // AJAX handlers
        add_action('wp_ajax_ai_chatbot_preview', ['AI_Chatbot_Admin_Ajax', 'preview']);
        add_action('wp_ajax_ai_chatbot_trigger_notify', ['AI_Chatbot_Admin_Ajax', 'trigger_notify']);
        add_action('wp_ajax_ai_chatbot_fetch_models', ['AI_Chatbot_Admin_Ajax', 'fetch_models']);
        add_action('wp_ajax_ai_provider_save_models', ['AI_Chatbot_Admin_Ajax', 'save_provider_models']);

        // WP Cron: inactivity notification check
        add_action('ai_chatbot_inactivity_notify', ['AI_Chatbot_Notifier', 'check_inactivity_and_notify']);
    }

    public static function get_client_ip(): string {
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                $ips = explode(',', $_SERVER[$h]);
                return trim($ips[0]);
            }
        }
        return '127.0.0.1';
    }

    public function register_cpts(): void {
        AI_Chatbot_CPT_Provider::register();
        AI_Chatbot_CPT_Chatbot::register();
        AI_Chatbot_CPT_Knowledge::register();
        AI_Chatbot_CPT_Conversation::register();
    }

    public function register_widget(): void {
        AI_Chatbot_Widget::init();
    }

    public function register_api_routes(): void {
        AI_Chatbot_Chat_API::register_routes();
    }

    public function enqueue_admin_assets(string $hook): void {
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, ['ai_provider', 'ai_chatbot', 'ai_knowledge', 'ai_conversation'], true)) {
            return;
        }
        wp_enqueue_style(
            'ai-chatbot-admin',
            WP_AIGENT_URL . 'assets/ai-chatbot/css/admin.css',
            [],
            self::asset_version('assets/ai-chatbot/css/admin.css')
        );

        // Always enqueue Dashicons for the icon selector
        wp_enqueue_style('dashicons');

        // Load chatbot-specific configuration UI and live preview.
        if ($screen->post_type === 'ai_chatbot' && $screen->base === 'post') {
            // Admin JS for chatbot config (tabs, schema builder, notification rules)
            wp_enqueue_script(
                'ai-chatbot-admin',
                WP_AIGENT_URL . 'assets/ai-chatbot/js/admin.js',
                ['jquery'],
                self::asset_version('assets/ai-chatbot/js/admin.js'),
                true
            );

            $provider_models = [];
            $providers = get_posts([
                'post_type'      => 'ai_provider',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]);
            foreach ($providers as $provider_id) {
                $provider_meta = AI_Chatbot_CPT_Provider::get_meta((int) $provider_id);
                $provider_models[$provider_id] = is_array($provider_meta['api_provider_model_list'] ?? null)
                    ? $provider_meta['api_provider_model_list']
                    : [];
            }

            wp_localize_script('ai-chatbot-admin', 'aiChatbotAdmin', [
                'preview_nonce' => wp_create_nonce('ai_chatbot_preview'),
                'providerModels'   => $provider_models,
            ]);
        } elseif ($screen->post_type === 'ai_knowledge' && $screen->base === 'post') {
            wp_enqueue_script(
                'ai-chatbot-admin',
                WP_AIGENT_URL . 'assets/ai-chatbot/js/admin.js',
                ['jquery'],
                self::asset_version('assets/ai-chatbot/js/admin.js'),
                true
            );
            $provider_models = [];
            $providers = get_posts([
                'post_type' => 'ai_provider', 'post_status' => 'publish',
                'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
            ]);
            foreach ($providers as $provider_id) {
                $provider_meta = AI_Chatbot_CPT_Provider::get_meta((int) $provider_id);
                $provider_models[$provider_id] = is_array($provider_meta['api_provider_model_list'] ?? null) ? $provider_meta['api_provider_model_list'] : [];
            }
            wp_localize_script('ai-chatbot-admin', 'aiChatbotAdmin', ['providerModels' => $provider_models]);
        } elseif ($screen->post_type === 'ai_knowledge' && $screen->base === 'post') {
            wp_enqueue_script(
                'ai-chatbot-admin',
                WP_AIGENT_URL . 'assets/ai-chatbot/js/admin.js',
                ['jquery'],
                self::asset_version('assets/ai-chatbot/js/admin.js'),
                true
            );
            $provider_models = [];
            $providers = get_posts([
                'post_type' => 'ai_provider', 'post_status' => 'publish',
                'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true,
            ]);
            foreach ($providers as $provider_id) {
                $provider_meta = AI_Chatbot_CPT_Provider::get_meta((int) $provider_id);
                $provider_models[$provider_id] = is_array($provider_meta['api_provider_model_list'] ?? null) ? $provider_meta['api_provider_model_list'] : [];
            }
            wp_localize_script('ai-chatbot-admin', 'aiChatbotAdmin', ['providerModels' => $provider_models]);
        } elseif ($screen->post_type === 'ai_provider' && $screen->base === 'post') {
            wp_enqueue_script(
                'ai-provider-admin',
                WP_AIGENT_URL . 'assets/ai-chatbot/js/provider-admin.js',
                ['jquery'],
                self::asset_version('assets/ai-chatbot/js/provider-admin.js'),
                true
            );
            wp_localize_script('ai-provider-admin', 'aiProviderAdmin', [
                'providerId'       => (int) get_the_ID(),
                'fetchModelsNonce' => wp_create_nonce('ai_chatbot_fetch_models'),
                'manageModelsNonce' => wp_create_nonce('ai_provider_manage_models'),
                'i18n' => [
                    'fetchModels' => __('Fetch Models', 'wp-aigent'),
                    'fetching'    => __('Fetching…', 'wp-aigent'),
                    'fetchFailed' => __('Could not fetch models. Check the saved API URL and key.', 'wp-aigent'),
                    'modelsFound' => __('Found %d models.', 'wp-aigent'),
                    'editModels'  => __('Edit Models', 'wp-aigent'),
                    'done'        => __('Done', 'wp-aigent'),
                    'deleteModel' => __('Delete model', 'wp-aigent'),
                    'saveFailed'  => __('Could not save the model list.', 'wp-aigent'),
                ],
            ]);
        }
    }

    public function enqueue_frontend_assets(): void {
        // Frontend assets are loaded by the Elementor widget when it renders.
    }

    /**
     * Enqueue Font Awesome 4 — uses Elementor's FA4 shim if available, otherwise loads from CDN.
     */
    public static function enqueue_font_awesome(): void {
        self::register_font_awesome();
        wp_enqueue_style('font-awesome');
    }

    /**
     * Register the chatbot widget CSS and JS for Elementor widget dependencies.
     */
    public static function register_chatbot_assets(): void {
        self::register_font_awesome();

        wp_register_style(
            'ai-chat-widget',
            WP_AIGENT_URL . 'assets/ai-chatbot/css/chat-widget.css',
            [],
            self::asset_version('assets/ai-chatbot/css/chat-widget.css')
        );

        wp_register_script(
            'ai-chat-widget',
            WP_AIGENT_URL . 'assets/ai-chatbot/js/chat-widget.js',
            [],
            self::asset_version('assets/ai-chatbot/js/chat-widget.js'),
            true
        );

        wp_localize_script('ai-chat-widget', 'AIChatBotGlobals', [
            'rest_url'    => esc_url_raw(rest_url('ai-chat/v1/chat')),
            'history_url' => esc_url_raw(rest_url('ai-chat/v1/history')),
            'nonce'       => wp_create_nonce('wp_rest'),
        ]);
    }

    private static function asset_version(string $relative_path): string {
        $path = WP_AIGENT_PATH . ltrim($relative_path, '/\\');
        $mtime = is_readable($path) ? filemtime($path) : false;

        return $mtime ? (string) $mtime : WP_AIGENT_VERSION;
    }

    public static function enqueue_chatbot_assets(): void {
        self::register_chatbot_assets();
        wp_enqueue_style('font-awesome');
        wp_enqueue_style('ai-chat-widget');
        wp_enqueue_script('ai-chat-widget');
    }

    private static function register_font_awesome(): void {
        if (wp_style_is('font-awesome', 'registered')) {
            return;
        }

        if (did_action('elementor/loaded') && wp_style_is('font-awesome-4-shim', 'registered')) {
            wp_register_style(
                'font-awesome',
                false,
                ['font-awesome-4-shim'],
                WP_AIGENT_VERSION
            );
            return;
        }

        wp_register_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css',
            [],
            '4.7.0'
        );
    }

    public function elementor_dependency_notice(): void {
        if (did_action('elementor/loaded')) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type ?? '', ['ai_chatbot', 'ai_knowledge', 'ai_conversation'], true)) {
            return;
        }

        echo '<div class="notice notice-warning"><p>'
            . esc_html__('WP AIgent chatbot rendering now requires Elementor. Activate Elementor and use the AI Chatbot widget to place chatbots on pages.', 'wp-aigent')
            . '</p></div>';
    }

    public function plugin_action_links(array $links): array {
        $settings_link = '<a href="' . admin_url('edit.php?post_type=ai_chatbot') . '">'
            . __('Manage Chatbots', 'wp-aigent') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}
