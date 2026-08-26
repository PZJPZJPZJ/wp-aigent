<?php
defined('ABSPATH') || exit;

class AI_Chatbot_Widget {

    public static function init(): void {
        add_action('elementor/widgets/register', [self::class, 'register_widget']);
        add_action('wp_enqueue_scripts', [self::class, 'register_assets']);
        add_action('elementor/frontend/after_register_scripts', [self::class, 'register_assets']);
        add_action('elementor/frontend/after_register_styles', [self::class, 'register_assets']);
        add_action('admin_notices', [self::class, 'dependency_notice']);
    }

    public static function register_widget($widgets_manager): void {
        require_once WP_AIGENT_PATH . 'includes/integrations/elementor/class-chatbot-widget-base.php';
        $widgets_manager->register(new AI_Chatbot_Widget_Base());
    }

    public static function register_assets(): void {
        self::register_font_awesome();
        wp_register_style('ai-chat-widget', WP_AIGENT_URL . 'assets/modules/chatbots/css/widget.css', [], self::version('assets/modules/chatbots/css/widget.css'));
        wp_register_script('ai-chat-widget', WP_AIGENT_URL . 'assets/modules/chatbots/js/widget.js', [], self::version('assets/modules/chatbots/js/widget.js'), true);
        wp_localize_script('ai-chat-widget', 'AIChatBotGlobals', [
            'rest_url'    => esc_url_raw(rest_url('ai-chat/chat')),
            'history_url' => esc_url_raw(rest_url('ai-chat/history')),
            'visitor_url' => esc_url_raw(rest_url('ai-chat/visitor')),
        ]);
    }

    public static function dependency_notice(): void {
        if (did_action('elementor/loaded')) return;
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type ?? '', ['ai_chatbot', 'ai_knowledge', 'ai_conversation'], true)) return;
        echo '<div class="notice notice-warning"><p>' . esc_html__('WP AIgent chatbot rendering now requires Elementor. Activate Elementor and use the AI Chatbot widget to place chatbots on pages.', 'wp-aigent') . '</p></div>';
    }

    private static function register_font_awesome(): void {
        if (wp_style_is('font-awesome', 'registered')) return;
        if (did_action('elementor/loaded') && wp_style_is('font-awesome-4-shim', 'registered')) {
            wp_register_style('font-awesome', false, ['font-awesome-4-shim'], WP_AIGENT_VERSION);
            return;
        }
        wp_register_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css', [], '4.7.0');
    }

    private static function version(string $path): string {
        $mtime = is_readable(WP_AIGENT_PATH . $path) ? filemtime(WP_AIGENT_PATH . $path) : false;
        return $mtime ? (string) $mtime : WP_AIGENT_VERSION;
    }

}
