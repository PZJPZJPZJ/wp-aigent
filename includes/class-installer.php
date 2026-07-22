<?php
defined('ABSPATH') || exit;

class WP_AIGent_Installer {

    public static function activate(): void {
        self::register_cpts();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    private static function register_cpts(): void {
        require_once WP_AIGENT_PATH . 'includes/ai-chatbot/class-cpt-provider.php';
        require_once WP_AIGENT_PATH . 'includes/ai-chatbot/class-cpt-chatbot.php';
        require_once WP_AIGENT_PATH . 'includes/ai-chatbot/class-cpt-knowledge.php';
        require_once WP_AIGENT_PATH . 'includes/ai-chatbot/class-cpt-conversation.php';
        AI_Chatbot_CPT_Provider::register();
        AI_Chatbot_CPT_Chatbot::register();
        AI_Chatbot_CPT_Knowledge::register();
        AI_Chatbot_CPT_Conversation::register();
    }
}
