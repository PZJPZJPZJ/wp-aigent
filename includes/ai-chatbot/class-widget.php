<?php
defined('ABSPATH') || exit;

class AI_Chatbot_Widget {

    public static function init(): void {
        add_action('elementor/widgets/register', [self::class, 'register_widget']);
    }

    public static function register_widget($widgets_manager): void {
        require_once WP_AIGENT_PATH . 'includes/ai-chatbot/class-widget-base.php';
        $widgets_manager->register(new AI_Chatbot_Widget_Base());
    }

}
