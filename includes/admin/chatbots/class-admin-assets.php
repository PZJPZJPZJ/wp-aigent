<?php
defined('ABSPATH') || exit;

/** Registers admin assets for Provider, Chatbot, Knowledge, and Conversation screens. */
class AI_Chatbot_Admin_Assets {

    public function register(): void {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function enqueue(string $hook): void {
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, ['ai_provider', 'ai_chatbot', 'ai_knowledge', 'ai_conversation'], true)) return;

        wp_enqueue_style('ai-chatbot-admin', WP_AIGENT_URL . 'assets/modules/chatbots/css/admin.css', [], $this->version('assets/modules/chatbots/css/admin.css'));
        wp_enqueue_style('dashicons');

        if (in_array($screen->post_type, ['ai_chatbot', 'ai_knowledge'], true) && $screen->base === 'post') {
            wp_enqueue_script('ai-chatbot-admin', WP_AIGENT_URL . 'assets/modules/chatbots/js/admin.js', ['jquery'], $this->version('assets/modules/chatbots/js/admin.js'), true);
            wp_localize_script('ai-chatbot-admin', 'aiChatbotAdmin', [
                'preview_nonce' => wp_create_nonce('ai_chatbot_preview'),
                'providerModels' => $this->provider_models(),
            ]);
        }

        if ($screen->post_type === 'ai_provider' && $screen->base === 'post') {
            wp_enqueue_script('ai-provider-admin', WP_AIGENT_URL . 'assets/modules/providers/js/admin.js', ['jquery'], $this->version('assets/modules/providers/js/admin.js'), true);
            wp_localize_script('ai-provider-admin', 'aiProviderAdmin', [
                'providerId' => (int) get_the_ID(),
                'fetchModelsNonce' => wp_create_nonce('ai_chatbot_fetch_models'),
                'manageModelsNonce' => wp_create_nonce('ai_provider_manage_models'),
                'i18n' => [
                    'fetchModels' => __('Fetch Models', 'wp-aigent'), 'fetching' => __('Fetching…', 'wp-aigent'),
                    'fetchFailed' => __('Could not fetch models. Check the saved API URL and key.', 'wp-aigent'),
                    'modelsFound' => __('Found %d models.', 'wp-aigent'), 'editModels' => __('Edit Models', 'wp-aigent'),
                    'done' => __('Done', 'wp-aigent'), 'deleteModel' => __('Delete model', 'wp-aigent'),
                    'saveFailed' => __('Could not save the model list.', 'wp-aigent'),
                ],
            ]);
        }
    }

    private function provider_models(): array {
        $models = [];
        $ids = get_posts(['post_type' => 'ai_provider', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true]);
        foreach ($ids as $id) {
            $meta = AI_Chatbot_CPT_Provider::get_meta((int) $id);
            $models[$id] = is_array($meta['api_provider_model_list'] ?? null) ? $meta['api_provider_model_list'] : [];
        }
        return $models;
    }

    private function version(string $path): string {
        $mtime = is_readable(WP_AIGENT_PATH . $path) ? filemtime(WP_AIGENT_PATH . $path) : false;
        return $mtime ? (string) $mtime : WP_AIGENT_VERSION;
    }
}
