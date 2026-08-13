<?php
defined('ABSPATH') || exit;

class WP_AIGent_Bootstrap {

    private static ?WP_AIGent_Bootstrap $instance = null;

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

        require_once $includes . 'core/identity/class-visitor-identity.php';

        // CPTs
        require_once $includes . 'modules/providers/class-cpt-provider.php';
        require_once $includes . 'modules/chatbots/class-cpt-chatbot.php';
        require_once $includes . 'modules/knowledge/class-cpt-knowledge.php';
        require_once $includes . 'modules/conversations/class-cpt-conversation.php';

        // Core engine
        require_once $includes . 'core/ai/class-ai-client.php';
        require_once $includes . 'core/ai/class-token-usage.php';
        require_once $includes . 'modules/knowledge/class-knowledge-indexer.php';
        require_once $includes . 'modules/knowledge/class-knowledge-card-service.php';
        require_once $includes . 'modules/knowledge/class-knowledge-catalog.php';
        require_once $includes . 'modules/knowledge/class-knowledge-router.php';
        require_once $includes . 'modules/knowledge/class-knowledge-retriever.php';
        require_once $includes . 'modules/knowledge/class-knowledge-loader.php';
        require_once $includes . 'modules/conversations/class-memory-manager.php';
        require_once $includes . 'modules/intelligence/class-lead-processor.php';
        require_once $includes . 'modules/notifications/class-notifier.php';
        require_once $includes . 'modules/chatbots/class-chat-api.php';

        // Widget (Elementor integration)
        require_once $includes . 'integrations/elementor/class-chatbot-widget.php';

        // AI Forms module
        require_once $includes . 'core/contracts/interface-form-submission-source.php';
        require_once $includes . 'modules/forms/class-form-analysis-schema.php';
        require_once $includes . 'modules/forms/class-country-resolver.php';
        require_once $includes . 'modules/forms/class-form-analysis-repository.php';
        require_once $includes . 'modules/forms/class-form-submissions-query.php';
        require_once $includes . 'modules/forms/class-form-submission-normalizer.php';
        require_once $includes . 'modules/forms/class-form-analysis-service.php';
        require_once $includes . 'modules/forms/class-module.php';
        require_once $includes . 'integrations/elementor/class-submission-source-adapter.php';
        require_once $includes . 'integrations/elementor/class-form-enhancer.php';
        require_once $includes . 'admin/forms/class-analysis-ajax-controller.php';
        require_once $includes . 'admin/forms/class-submissions-list-table.php';
        require_once $includes . 'admin/forms/class-forms-admin-page.php';
        new WP_AIGent_Forms_Module();
        (new WP_AIGent_Elementor_Form_Enhancer())->init();
        $submission_source = new WP_AIGent_Elementor_Submission_Adapter();
        $form_analysis_repository = new WP_AIGent_Form_Analysis_Repository();
        $form_submissions_query = new WP_AIGent_Form_Submissions_Query($submission_source, $form_analysis_repository);
        $form_analysis_service = new WP_AIGent_Form_Analysis_Service($submission_source, $form_analysis_repository, new WP_AIGent_Form_Submission_Normalizer(), $form_submissions_query);
        new WP_AIGent_Form_Analysis_Ajax_Controller($form_analysis_service);
        if (is_admin()) (new WP_AIGent_Forms_Admin_Page($form_submissions_query))->register();

        // GitHub updater (attaches hooks unconditionally so WP can detect updates)
        require_once $includes . 'integrations/wordpress/class-github-updater.php';
        new WP_Plugin_Github_Updater(WP_AIGENT_FILE);

        // Admin
        if (is_admin()) {
            require_once $includes . 'admin/chatbots/class-admin-columns.php';
            require_once $includes . 'admin/chatbots/class-admin-ajax.php';
            require_once $includes . 'admin/chatbots/class-admin-assets.php';
            require_once $includes . 'admin/conversations/class-export.php';
            new AI_Chatbot_Admin_Columns();
            new AI_Chatbot_Export();
            (new AI_Chatbot_Admin_Assets())->register();
        }
    }

    private function register_hooks(): void {
        AI_Chatbot_Knowledge_Indexer::maybe_install();
        WP_AIGent_Form_Analysis_Schema::maybe_install();
        add_action('init', [$this, 'register_cpts']);
        add_action('init', [$this, 'register_widget']);
        add_action('rest_api_init', [$this, 'register_api_routes']);
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

    public function plugin_action_links(array $links): array {
        $settings_link = '<a href="' . admin_url('edit.php?post_type=ai_chatbot') . '">'
            . __('Manage Chatbots', 'wp-aigent') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}
