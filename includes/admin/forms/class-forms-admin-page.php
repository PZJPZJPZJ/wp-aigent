<?php
defined('ABSPATH') || exit;

/** Owns AI Forms admin menu, assets, and view-model preparation. */
class WP_AIGent_Forms_Admin_Page {

    public function register(): void {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_menu', [$this, 'move_menu'], 100);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function add_menu(): void {
        add_submenu_page('edit.php?post_type=ai_chatbot', __('AI Forms', 'wp-aigent'), __('AI Forms', 'wp-aigent'), 'manage_options', 'wp-aigent-ai-form', [$this, 'render'], 6);
    }

    public function move_menu(): void {
        global $submenu;
        $parent = 'edit.php?post_type=ai_chatbot';
        if (empty($submenu[$parent]) || !is_array($submenu[$parent])) return;
        $found = null;
        foreach ($submenu[$parent] as $index => $item) {
            if (($item[2] ?? '') === 'wp-aigent-ai-form') {
                $found = $item;
                unset($submenu[$parent][$index]);
                break;
            }
        }
        if (!$found) return;
        $items = array_values($submenu[$parent]);
        array_splice($items, 1, 0, [$found]);
        $submenu[$parent] = $items;
    }

    public function enqueue_assets(string $hook): void {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ($page !== 'wp-aigent-ai-form') return;

        wp_enqueue_style('wp-aigent-ai-form-admin', WP_AIGENT_URL . 'assets/modules/forms/css/admin.css', [], $this->asset_version('assets/modules/forms/css/admin.css'));
        wp_enqueue_script('wp-aigent-form-analysis-admin', WP_AIGENT_URL . 'assets/modules/forms/js/analysis-admin.js', [], $this->asset_version('assets/modules/forms/js/analysis-admin.js'), true);
        $provider_models = [];
        foreach (get_posts(['post_type' => 'ai_provider', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true]) as $provider_id) {
            $meta = AI_Chatbot_CPT_Provider::get_meta((int) $provider_id);
            $provider_models[$provider_id] = is_array($meta['api_provider_model_list'] ?? null) ? $meta['api_provider_model_list'] : [];
        }
        wp_localize_script('wp-aigent-form-analysis-admin', 'wpAIgentFormAnalysis', [
            'ajaxUrl' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('wp_aigent_form_analysis'), 'providerModels' => $provider_models,
            'i18n' => [
                'chooseModel' => __('Choose a model', 'wp-aigent'), 'starting' => __('Creating the manual analysis job…', 'wp-aigent'),
                'progress' => __('Inspected %1$d; analyzed %2$d; already completed %3$d; failed %4$d.', 'wp-aigent'),
                'complete' => __('Analysis complete.', 'wp-aigent'), 'failed' => __('Analysis request failed.', 'wp-aigent'),
            ],
        ]);
    }

    public function render(): void {
        if (!current_user_can('manage_options')) return;
        $settings = WP_AIGent_Forms_Module::get_settings();
        $providers = get_posts(['post_type' => 'ai_provider', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        $results = (new WP_AIGent_Form_Analysis_Repository())->latest_results(20);
        $result_rows = array_map(static function (array $row): array {
            $source = json_decode($row['normalized_source'] ?? '', true) ?: [];
            $contact = json_decode($row['normalized_contact'] ?? '', true) ?: [];
            return [
                'submission_id' => absint($row['source_record_id'] ?? 0),
                'source' => trim(($source['form_name'] ?? '') . ' / ' . ($source['page_url'] ?? ''), ' /'),
                'contact' => implode(' · ', array_filter([$contact['name'] ?? '', $contact['email'] ?? '', $contact['phone'] ?? '', $contact['whatsapp'] ?? ''])),
                'requirements_summary' => (string) ($row['requirements_summary'] ?? ''),
                'completed_at' => get_date_from_gmt((string) ($row['completed_at_gmt'] ?? ''), 'Y-m-d H:i'),
            ];
        }, $results);
        $today = wp_date('Y-m-d');
        $month_ago = wp_date('Y-m-d', time() - 30 * DAY_IN_SECONDS);
        include WP_AIGENT_PATH . 'templates/modules/forms/admin-page.php';
    }

    private function asset_version(string $path): string {
        $mtime = is_readable(WP_AIGENT_PATH . $path) ? filemtime(WP_AIGENT_PATH . $path) : false;
        return $mtime ? (string) $mtime : WP_AIGENT_VERSION;
    }
}
