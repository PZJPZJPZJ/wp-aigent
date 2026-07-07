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
        require_once $includes . 'ai-chatbot/class-cpt-chatbot.php';
        require_once $includes . 'ai-chatbot/class-cpt-knowledge.php';
        require_once $includes . 'ai-chatbot/class-cpt-conversation.php';

        // Core engine
        require_once $includes . 'ai-chatbot/class-ai-client.php';
        require_once $includes . 'ai-chatbot/class-knowledge-loader.php';
        require_once $includes . 'ai-chatbot/class-memory-manager.php';
        require_once $includes . 'ai-chatbot/class-lead-processor.php';
        require_once $includes . 'ai-chatbot/class-notifier.php';
        require_once $includes . 'ai-chatbot/class-chat-api.php';

        // Widget (Elementor integration)
        require_once $includes . 'ai-chatbot/class-widget.php';

        // AI Form module
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
        add_action('init', [$this, 'register_cpts']);
        add_action('init', [$this, 'register_widget']);
        add_action('rest_api_init', [$this, 'register_api_routes']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_filter('plugin_action_links_' . plugin_basename(WP_AIGENT_FILE), [$this, 'plugin_action_links']);
        add_shortcode('ai_chatbot', [$this, 'shortcode_render']);

        // AJAX handlers
        add_action('wp_ajax_ai_chatbot_preview', ['AI_Chatbot_Admin_Ajax', 'preview']);
        add_action('wp_ajax_ai_chatbot_trigger_notify', ['AI_Chatbot_Admin_Ajax', 'trigger_notify']);
        add_action('wp_ajax_ai_chatbot_fetch_models', ['AI_Chatbot_Admin_Ajax', 'fetch_models']);

        // WP Cron: inactivity notification check
        add_action('ai_chatbot_inactivity_notify', ['AI_Chatbot_Notifier', 'check_inactivity_and_notify']);
    }

    /**
     * Shortcode: [ai_chatbot id="123"]
     */
    public function shortcode_render($atts): string {
        $chatbot_id = (int) ($atts['id'] ?? 0);
        if (!$chatbot_id || get_post_type($chatbot_id) !== 'ai_chatbot') {
            return '<div class="ai-chat-error">' . __('Invalid chatbot ID.', 'wp-aigent') . '</div>';
        }

        // Ensure CSS and JS are enqueued
        $this->enqueue_widget_assets();

        return self::render_chatbot_html($chatbot_id);
    }

    /**
     * Render chatbot HTML — shared by shortcode, admin preview, and widget.
     */
    public static function render_chatbot_html(int $chatbot_id, string $widget_id = ''): string {
        // Ensure CSS and JS are always loaded, regardless of how the widget is rendered
        wp_enqueue_style('ai-chat-widget', WP_AIGENT_URL . 'assets/ai-chatbot/css/chat-widget.css', [], WP_AIGENT_VERSION);
        self::enqueue_font_awesome();
        wp_enqueue_script('ai-chat-widget', WP_AIGENT_URL . 'assets/ai-chatbot/js/chat-widget.js', [], WP_AIGENT_VERSION, true);

        // Always set global API config (safe to call multiple times — WordPress merges)
        wp_localize_script('ai-chat-widget', 'AIChatBotGlobals', [
            'rest_url'    => esc_url_raw(rest_url('ai-chat/v1/chat')),
            'history_url' => esc_url_raw(rest_url('ai-chat/v1/history')),
            'nonce'       => wp_create_nonce('wp_rest'),
        ]);

        $config = AI_Chatbot_CPT_Chatbot::get_meta($chatbot_id);
        if (empty($widget_id)) {
            $widget_id = 'ai_' . $chatbot_id . '_' . uniqid();
        }

        // Pass config to JS
        $fab_icon = $config['chatbot_fab_icon'] ?: 'fa-envelope';
        // Enqueue Dashicons on frontend if the icon uses it
        if (strpos($fab_icon, 'dashicons-') === 0) {
            wp_enqueue_style('dashicons');
        }
        wp_localize_script('ai-chat-widget', 'AIChatConfig_' . $widget_id, [
            'chatbot_id'    => $chatbot_id,
            'session_id'    => '',
            'session_token' => '', // Widget uses visitor-based session; server computes from UUID
            'layout_mode'   => $config['chatbot_layout_mode'] ?: 'inline',
            'greeting'      => $config['chatbot_greeting'] ?: 'Hello!',
            'avatar'        => $config['chatbot_avatar'] ? wp_get_attachment_url($config['chatbot_avatar']) : '',
            'i18n'          => $config['chatbot_i18n'] ?: [],
            'fab_icon'      => $fab_icon,
            'widget_id'     => $widget_id,
            'ripple_enabled'   => $config['chatbot_fab_ripple_enabled'] ?? '0',
            'ripple_color'     => $config['chatbot_fab_ripple_color'] ?: '',
            'ripple_opacity'   => $config['chatbot_fab_ripple_opacity'] ?: '0.2',
            'ripple_speed'     => $config['chatbot_fab_ripple_speed'] ?: '1',
            'ripple_radius'    => $config['chatbot_fab_ripple_radius'] ?: '2.5',
            'icon_shake'       => $config['chatbot_fab_icon_shake'] ?? '0',
            'lead_fields'      => is_array($config['chatbot_lead_fields'] ?? null) ? $config['chatbot_lead_fields'] : [],
            'fab_hint'         => $config['chatbot_fab_hint'] ?: '',
            'fab_hint_position'  => $config['chatbot_fab_hint_position'] ?: 'right',
            'fab_hint_font_size' => $config['chatbot_fab_hint_font_size'] ?: '13',
            'fab_default_open'   => $config['chatbot_fab_default_open'] ?? '0',
            'fab_open_delay'     => $config['chatbot_fab_open_delay'] ?: '20',
            'popup_transition_duration' => $config['chatbot_popup_transition_duration'] ?: '100',
            'open_cache_ttl'    => $config['chatbot_open_cache_ttl'] ?: '1440',
            'fab_position'      => $config['chatbot_fab_position'] ?: 'bottom-right',
            'fab_distance_x'    => $config['chatbot_fab_distance_x'] ?: '24',
            'fab_distance_y'    => $config['chatbot_fab_distance_y'] ?: '24',
        ]);

        $container_id = 'ai-chatbot-container-' . $widget_id;
        $layout = $config['chatbot_layout_mode'] ?? 'inline';
        ob_start();

        // Inline theme styles for custom colors + ripple + position CSS variables
        $popup_color = $config['chatbot_popup_color'] ?? $config['chatbot_primary_color'] ?? '';
        $button_color = $config['chatbot_button_color'] ?? $config['chatbot_primary_color'] ?? '';
        $ripple_color = $config['chatbot_fab_ripple_color'] ?: $button_color;
        $ripple_opacity = $config['chatbot_fab_ripple_opacity'] ?: '0.2';
        $ripple_speed = $config['chatbot_fab_ripple_speed'] ?: '1';
        $ripple_radius = $config['chatbot_fab_ripple_radius'] ?: '2.5';
        $fab_position = $config['chatbot_fab_position'] ?: 'bottom-right';
        $fab_dist_x = $config['chatbot_fab_distance_x'] ?: '24';
        $fab_dist_y = $config['chatbot_fab_distance_y'] ?: '24';
        $hint_bg = $config['chatbot_fab_hint_bg'] ?: '#333333';
        $hint_text = $config['chatbot_fab_hint_text'] ?: '#ffffff';
        $hint_font_size = $config['chatbot_fab_hint_font_size'] ?: '15';
        $transition_dur = $config['chatbot_popup_transition_duration'] ?: '100';
        $has_style = false;
        $container_selector = '#' . $container_id;
        $style_output = '<style id="ai-chat-theme-' . esc_attr($widget_id) . '">' . "\n";
        // Scope CSS variables to the container — colors use var() fallbacks in CSS
        $style_output .= $container_selector . ' {';
        $style_output .= ' --ripple-color: ' . esc_attr($ripple_color) . ';';
        $style_output .= ' --ripple-opacity: ' . esc_attr($ripple_opacity) . ';';
        $style_output .= ' --ripple-speed: ' . esc_attr($ripple_speed) . 's;';
        $style_output .= ' --ripple-radius: ' . esc_attr($ripple_radius) . ';';
        $style_output .= ' --fab-x: ' . esc_attr($fab_dist_x) . 'px;';
        $style_output .= ' --fab-y: ' . esc_attr($fab_dist_y) . 'px;';
        $style_output .= ' --hint-bg: ' . esc_attr($hint_bg) . ';';
        $style_output .= ' --hint-text: ' . esc_attr($hint_text) . ';';
        $style_output .= ' --hint-font-size: ' . esc_attr($hint_font_size) . 'px;';
        $style_output .= ' --popup-transition-duration: ' . esc_attr($transition_dur) . 'ms;';
        $safe_popup = esc_attr($popup_color ?: '#25b366');
        $safe_button = esc_attr($button_color ?: '#25b366');
        $style_output .= ' --ai-chatbot-primary: ' . $safe_button . ';';
        $style_output .= ' --ai-chatbot-popup: ' . $safe_popup . ';';
        $style_output .= ' --ai-chatbot-button: ' . $safe_button . ';';
        $style_output .= ' --ai-chatbot-button-hover: ' . $safe_button . ';';
        $style_output .= ' }' . "\n";
        $has_style = true;
        // Position-specific rules
        $popup_gap = '66px';
        $fab_rules = [
            'bottom-right' => 'bottom:var(--fab-y);right:var(--fab-x);top:auto;left:auto;',
            'bottom-left'  => 'bottom:var(--fab-y);left:var(--fab-x);top:auto;right:auto;',
            'top-right'    => 'top:var(--fab-y);right:var(--fab-x);bottom:auto;left:auto;',
            'top-left'     => 'top:var(--fab-y);left:var(--fab-x);bottom:auto;right:auto;',
        ];
        $popup_rules = [
            'bottom-right' => 'bottom:calc(var(--fab-y) + ' . $popup_gap . ');right:var(--fab-x);top:auto;left:auto;',
            'bottom-left'  => 'bottom:calc(var(--fab-y) + ' . $popup_gap . ');left:var(--fab-x);top:auto;right:auto;',
            'top-right'    => 'top:calc(var(--fab-y) + ' . $popup_gap . ');right:var(--fab-x);bottom:auto;left:auto;',
            'top-left'     => 'top:calc(var(--fab-y) + ' . $popup_gap . ');left:var(--fab-x);bottom:auto;right:auto;',
        ];
        if (isset($fab_rules[$fab_position])) {
            // Only output non-default position rules to avoid redundancy
            if ($fab_position !== 'bottom-right') {
                $style_output .= '.ai-chatbot-fab { ' . $fab_rules[$fab_position] . ' }' . "\n";
            }
            $style_output .= '.ai-chatbot-popup { ' . $popup_rules[$fab_position] . ' }' . "\n";
        }
        $style_output .= '</style>' . "\n";
        if ($has_style || $config['chatbot_fab_ripple_enabled'] === '1') {
            echo $style_output;
        }

        // Render container — custom HTML or default
        $custom_html = $config['chatbot_html_template'] ?? '';
        if (!empty($custom_html)) {
            echo str_replace(
                ['{{container_id}}', '{{widget_id}}', '{{chatbot_id}}', '{{layout}}'],
                [esc_attr($container_id), esc_attr($widget_id), (int) $chatbot_id, esc_attr($layout)],
                $custom_html
            );
        } else {
            echo '<div id="' . esc_attr($container_id) . '" class="ai-chatbot-container" data-widget-id="' . esc_attr($widget_id) . '" data-layout="' . esc_attr($layout) . '"></div>';
        }

        // Inline custom CSS if saved
        $custom_css = $config['chatbot_custom_css'] ?? '';
        if (!empty($custom_css)) {
            echo '<style id="ai-chat-css-' . esc_attr($widget_id) . '">' . "\n" . $custom_css . "\n" . '</style>';
        }

        // Inline custom JS if saved
        $custom_js = $config['chatbot_custom_js'] ?? '';
        if (!empty($custom_js)) {
            echo '<script id="ai-chat-js-' . esc_attr($widget_id) . '">' . "\n" . $custom_js . "\n" . '</script>';
        }

        return ob_get_clean();
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
        if (!$screen || !in_array($screen->post_type, ['ai_chatbot', 'ai_knowledge', 'ai_conversation'], true)) {
            return;
        }
        wp_enqueue_style('ai-chatbot-admin', WP_AIGENT_URL . 'assets/ai-chatbot/css/admin.css', [], WP_AIGENT_VERSION);

        // Always enqueue Dashicons for the icon selector
        wp_enqueue_style('dashicons');

        // Load widget CSS/JS on chatbot edit screen for the live preview
        if ($screen->post_type === 'ai_chatbot' && $screen->base === 'post') {
            $this->enqueue_widget_assets();

            // Admin JS for chatbot config (tabs, schema builder, notification rules)
            wp_enqueue_script(
                'ai-chatbot-admin',
                WP_AIGENT_URL . 'assets/ai-chatbot/js/admin.js',
                ['jquery'],
                WP_AIGENT_VERSION,
                true
            );

            wp_localize_script('ai-chatbot-admin', 'aiChatbotAdmin', [
                'preview_nonce' => wp_create_nonce('ai_chatbot_preview'),
                'fetchModelsNonce' => wp_create_nonce('ai_chatbot_fetch_models'),
                'modelList'        => get_post_meta(get_the_ID(), 'chatbot_model_list', true) ?: [],
                'i18n' => [
                    'anthropicManual' => __('Anthropic only supports manual entry.', 'wp-aigent'),
                    'fetchModels' => __('Fetch Models', 'wp-aigent'),
                    'fetching' => __('Fetching...', 'wp-aigent'),
                    'modelsFound' => __('Found %d models', 'wp-aigent'),
                ],
            ]);
        }
    }

    public function enqueue_frontend_assets(): void {
        // Only enqueue on demand via widget
    }

    /**
     * Enqueue Font Awesome 4 — uses Elementor's FA4 shim if available, otherwise loads from CDN.
     */
    private static function enqueue_font_awesome(): void {
        if (did_action('elementor/loaded') && wp_style_is('font-awesome-4-shim', 'registered')) {
            wp_enqueue_style('font-awesome-4-shim');
            return;
        }
        wp_enqueue_style(
            'font-awesome',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css',
            [],
            '4.7.0'
        );
    }

    /**
     * Enqueue the chatbot widget CSS and JS.
     */
    public function enqueue_widget_assets(): void {
        wp_enqueue_style(
            'ai-chat-widget',
            WP_AIGENT_URL . 'assets/ai-chatbot/css/chat-widget.css',
            [],
            WP_AIGENT_VERSION
        );

        self::enqueue_font_awesome();

        wp_enqueue_script(
            'ai-chat-widget',
            WP_AIGENT_URL . 'assets/ai-chatbot/js/chat-widget.js',
            [],
            WP_AIGENT_VERSION,
            true
        );
    }

    public function plugin_action_links(array $links): array {
        $settings_link = '<a href="' . admin_url('edit.php?post_type=ai_chatbot') . '">'
            . __('Manage Chatbots', 'wp-aigent') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}
