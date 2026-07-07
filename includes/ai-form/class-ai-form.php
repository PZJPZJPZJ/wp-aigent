<?php
defined('ABSPATH') || exit;

class WP_AIGent_AI_Form {

    public const OPTION_NAME = 'wp_aigent_ai_form_settings';

    private WP_AIGent_Elementor_Form_Enhancer $elementor_enhancer;

    public function __construct() {
        $this->elementor_enhancer = new WP_AIGent_Elementor_Form_Enhancer();
        $this->elementor_enhancer->init();

        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_admin_menu']);
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
            __('AI Form', 'wp-aigent'),
            __('AI Form', 'wp-aigent'),
            'manage_options',
            'wp-aigent-ai-form',
            [$this, 'render_admin_page']
        );
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
            WP_AIGENT_VERSION
        );
    }

    public function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $settings = self::get_settings();
        ?>
        <div class="wrap wp-aigent-ai-form-admin">
            <h1><?php esc_html_e('AI Form', 'wp-aigent'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('wp_aigent_ai_form'); ?>

                <div class="wp-aigent-settings-panel">
                    <h2><?php esc_html_e('Elementor Form Enhancement', 'wp-aigent'); ?></h2>
                    <table class="form-table" role="presentation">
                        <tbody>
                        <tr>
                            <th scope="row"><?php esc_html_e('Elementor Form Enhancement', 'wp-aigent'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(self::OPTION_NAME); ?>[elementor_enabled]" value="1" <?php checked($settings['elementor_enabled'], '1'); ?>>
                                    <?php esc_html_e('Add a Country Code field type to Elementor Forms', 'wp-aigent'); ?>
                                </label>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
