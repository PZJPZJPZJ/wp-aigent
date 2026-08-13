<?php
defined('ABSPATH') || exit;

/** Owns AI Forms admin menu, assets, and view-model preparation. */
class WP_AIGent_Forms_Admin_Page {

    private WP_AIGent_Form_Submissions_Query $query;

    public function __construct(WP_AIGent_Form_Submissions_Query $query) {
        $this->query = $query;
    }

    public function register(): void {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_menu', [$this, 'move_menu'], 100);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_filter('set-screen-option', [$this, 'set_screen_option'], 10, 3);
    }

    public function add_menu(): void {
        add_submenu_page('edit.php?post_type=ai_chatbot', __('AI Forms', 'wp-aigent'), __('AI Forms', 'wp-aigent'), 'manage_options', 'wp-aigent-ai-form', [$this, 'render_settings'], 6);
        $analysis_hook = add_submenu_page('edit.php?post_type=ai_chatbot', __('Submissions', 'wp-aigent'), __('Submissions', 'wp-aigent'), 'manage_options', 'wp-aigent-submission-analysis', [$this, 'render_analysis'], 7);
        if ($analysis_hook) add_action('load-' . $analysis_hook, [$this, 'configure_submission_screen']);
    }

    public function move_menu(): void {
        global $submenu;
        $parent = 'edit.php?post_type=ai_chatbot';
        if (empty($submenu[$parent]) || !is_array($submenu[$parent])) return;
        $forms_item = null;
        $analysis_item = null;
        foreach ($submenu[$parent] as $index => $item) {
            if (($item[2] ?? '') === 'wp-aigent-ai-form') {
                $forms_item = $item;
                unset($submenu[$parent][$index]);
            } elseif (($item[2] ?? '') === 'wp-aigent-submission-analysis') {
                $analysis_item = $item;
                unset($submenu[$parent][$index]);
            }
        }
        $items = array_values($submenu[$parent]);
        if ($forms_item) array_splice($items, 1, 0, [$forms_item]);
        if ($analysis_item) {
            $conversation_index = null;
            foreach ($items as $index => $item) {
                if (($item[2] ?? '') === 'edit.php?post_type=ai_conversation') {
                    $conversation_index = $index;
                    break;
                }
            }
            array_splice($items, $conversation_index === null ? count($items) : $conversation_index + 1, 0, [$analysis_item]);
        }
        $submenu[$parent] = $items;
    }

    public function enqueue_assets(string $hook): void {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if (!in_array($page, ['wp-aigent-ai-form', 'wp-aigent-submission-analysis'], true)) return;

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
                'chooseModel' => __('Choose a model', 'wp-aigent'),
                'startingUpdate' => __('Creating an update analysis job…', 'wp-aigent'),
                'startingOverwrite' => __('Creating an overwrite analysis job…', 'wp-aigent'),
                'counted' => __('Found %d submissions. Starting analysis…', 'wp-aigent'),
                'progress' => __('Processed %1$d of %2$d (%3$d%); analyzed %4$d; skipped %5$d; failed %6$d.', 'wp-aigent'),
                'complete' => __('Analysis complete.', 'wp-aigent'), 'failed' => __('Analysis request failed.', 'wp-aigent'),
            ],
        ]);
    }

    public function render_settings(): void {
        if (!current_user_can('manage_options')) return;
        $settings = WP_AIGent_Forms_Module::get_settings();
        $providers = get_posts(['post_type' => 'ai_provider', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        include WP_AIGENT_PATH . 'templates/modules/forms/settings-page.php';
    }

    public function render_analysis(): void {
        if (!current_user_can('manage_options')) return;
        if (!$this->query->is_available()) {
            echo '<div class="wrap"><h1>' . esc_html__('Submissions', 'wp-aigent') . '</h1><div class="notice notice-warning inline"><p>' . esc_html__('Elementor Pro submission storage is unavailable. Enable Elementor Pro submissions before using this page.', 'wp-aigent') . '</p></div></div>';
            return;
        }
        $filters = $this->submission_filters();
        $this->query->cleanup_orphans();
        $filter_options = $this->query->filter_options($filters['date_from_gmt'], $filters['date_to_gmt']);
        $submission_table = new WP_AIGent_Submissions_List_Table($this->query, $filters, $filter_options);
        $submission_table->prepare_items();
        include WP_AIGENT_PATH . 'templates/modules/forms/analysis-page.php';
    }

    public function configure_submission_screen(): void {
        add_screen_option('per_page', [
            'label' => __('Submissions per page', 'wp-aigent'),
            'default' => 20,
            'option' => 'wp_aigent_submissions_per_page',
        ]);
        $screen = get_current_screen();
        if ($screen) add_filter('manage_' . $screen->id . '_columns', [$this, 'submission_columns']);
    }

    public function submission_columns(array $columns = []): array {
        return WP_AIGent_Submissions_List_Table::column_definitions();
    }

    public function set_screen_option($status, string $option, $value) {
        if ($option !== 'wp_aigent_submissions_per_page') return $status;
        return min(100, max(1, absint($value)));
    }

    private function asset_version(string $path): string {
        $mtime = is_readable(WP_AIGENT_PATH . $path) ? filemtime(WP_AIGENT_PATH . $path) : false;
        return $mtime ? (string) $mtime : WP_AIGENT_VERSION;
    }

    private function submission_filters(): array {
        $default_to = wp_date('Y-m-d');
        $default_from = wp_date('Y-m-d', time() - 30 * DAY_IN_SECONDS);
        $date_from = sanitize_text_field(wp_unslash($_REQUEST['date_from'] ?? $default_from));
        $date_to = sanitize_text_field(wp_unslash($_REQUEST['date_to'] ?? $default_to));
        $from = $this->local_date_to_gmt($date_from, false);
        $to = $this->local_date_to_gmt($date_to, true);
        if (!$from || !$to || $from > $to) {
            $date_from = $default_from;
            $date_to = $default_to;
            $from = $this->local_date_to_gmt($date_from, false);
            $to = $this->local_date_to_gmt($date_to, true);
        }
        return [
            'date_from' => $date_from, 'date_to' => $date_to,
            'date_from_gmt' => $from, 'date_to_gmt' => $to,
            'search' => sanitize_text_field(wp_unslash($_REQUEST['s'] ?? '')),
            'form' => sanitize_text_field(wp_unslash($_REQUEST['form'] ?? '')),
            'page_url' => esc_url_raw(wp_unslash($_REQUEST['page_url'] ?? '')),
            'spam' => sanitize_key(wp_unslash($_REQUEST['is_spam'] ?? '')),
            'intent' => sanitize_key(wp_unslash($_REQUEST['intent'] ?? '')),
            'analysis_status' => sanitize_key(wp_unslash($_REQUEST['analysis_status'] ?? '')),
        ];
    }

    private function local_date_to_gmt(string $date, bool $end_of_day): string {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, wp_timezone());
        $errors = DateTimeImmutable::getLastErrors();
        if (!$parsed || (is_array($errors) && ($errors['warning_count'] || $errors['error_count']))) return '';
        if ($end_of_day) $parsed = $parsed->setTime(23, 59, 59);
        return $parsed->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
