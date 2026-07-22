<?php
defined('ABSPATH') || exit;

class WP_AIGent_AI_Form {

    public const OPTION_NAME = 'wp_aigent_ai_form_settings';

    private WP_AIGent_Elementor_Form_Enhancer $elementor_enhancer;

    public function __construct() {
        $this->elementor_enhancer = new WP_AIGent_Elementor_Form_Enhancer();
        $this->elementor_enhancer->init();

        add_action('wp_enqueue_scripts', [$this, 'enqueue_country_code_scripts']);
        add_action('elementor/frontend/after_enqueue_scripts', [$this, 'enqueue_country_code_scripts']);

        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_menu', [$this, 'move_ai_forms_submenu_after_chatbots'], 100);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        }
    }

    public static function defaults(): array {
        return [
            'elementor_enabled' => '0',
        ];
    }

    public static function get_settings(): array {
        $settings = get_option(self::OPTION_NAME, []);
        if (!is_array($settings)) {
            $settings = [];
        }

        return array_merge(self::defaults(), $settings);
    }

    public function add_admin_menu(): void {
        add_submenu_page(
            'edit.php?post_type=ai_chatbot',
            __('AI Forms', 'wp-aigent'),
            __('AI Forms', 'wp-aigent'),
            'manage_options',
            'wp-aigent-ai-form',
            [$this, 'render_admin_page'],
            6
        );
    }

    public function move_ai_forms_submenu_after_chatbots(): void {
        global $submenu;

        $parent_slug = 'edit.php?post_type=ai_chatbot';
        if (empty($submenu[$parent_slug]) || !is_array($submenu[$parent_slug])) {
            return;
        }

        $ai_forms_item = null;
        foreach ($submenu[$parent_slug] as $index => $item) {
            if (($item[2] ?? '') === 'wp-aigent-ai-form') {
                $ai_forms_item = $item;
                unset($submenu[$parent_slug][$index]);
                break;
            }
        }

        if ($ai_forms_item === null) {
            return;
        }

        $items = array_values($submenu[$parent_slug]);
        array_splice($items, 1, 0, [$ai_forms_item]);
        $submenu[$parent_slug] = $items;
    }

    public function register_settings(): void {
        register_setting(
            'wp_aigent_ai_form',
            self::OPTION_NAME,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default'           => self::defaults(),
            ]
        );
    }

    public function sanitize_settings($input): array {
        $input = is_array($input) ? $input : [];

        return [
            'elementor_enabled' => !empty($input['elementor_enabled']) ? '1' : '0',
        ];
    }

    public function enqueue_admin_assets(string $hook): void {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ($page !== 'wp-aigent-ai-form') {
            return;
        }

        wp_enqueue_style(
            'wp-aigent-ai-form-admin',
            WP_AIGENT_URL . 'assets/ai-form/css/ai-form-admin.css',
            [],
            $this->asset_version('assets/ai-form/css/ai-form-admin.css')
        );
    }

    /**
     * Enqueue the country-code frontend script.
     *
     * Only loads when the Country Code field type is active. The script
     * fetches the visitor's country from Cloudflare's /cdn-cgi/trace
     * endpoint (no PHP involved) and updates the country-code select.
     */
    public function enqueue_country_code_scripts(): void {
        $settings = self::get_settings();
        if ($settings['elementor_enabled'] !== '1') {
            return;
        }

        wp_enqueue_script(
            'wp-aigent-country-code',
            WP_AIGENT_URL . 'assets/ai-form/js/country-code.js',
            [],
            $this->asset_version('assets/ai-form/js/country-code.js'),
            ['strategy' => 'defer', 'in_footer' => true]
        );
    }

    private function asset_version(string $relative_path): string {
        $path = WP_AIGENT_PATH . ltrim($relative_path, '/\\');
        $mtime = is_readable($path) ? filemtime($path) : false;

        return $mtime ? (string) $mtime : WP_AIGENT_VERSION;
    }

    public function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = self::get_settings();
        ?>
        <div class="wrap wp-aigent-ai-form-admin">
            <h1><?php esc_html_e('AI Forms', 'wp-aigent'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('wp_aigent_ai_form'); ?>

                <div class="wp-aigent-settings-panel">
                    <h2><?php esc_html_e('Elementor Form Enhancer', 'wp-aigent'); ?></h2>
                    <div class="wp-aigent-checkbox-list">
                        <label class="wp-aigent-checkbox-row">
                            <input type="checkbox" name="<?php echo esc_attr(self::OPTION_NAME); ?>[elementor_enabled]" value="1" aria-label="<?php esc_attr_e('Enable Add Country Code field type', 'wp-aigent'); ?>" <?php checked($settings['elementor_enabled'], '1'); ?>>
                            <strong><?php esc_html_e('Add Country Code field type', 'wp-aigent'); ?></strong>
                        </label>
                    </div>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
