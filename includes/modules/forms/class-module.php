<?php
defined('ABSPATH') || exit;

class WP_AIGent_Forms_Module {

    public const OPTION_NAME = 'wp_aigent_ai_form_settings';

    public function __construct() {
        if (is_admin()) add_action('admin_init', [$this, 'register_settings']);
    }

    public static function defaults(): array {
        return [
            'elementor_enabled' => '0',
            'analysis_provider_id' => 0,
            'analysis_model' => '',
            'analysis_reasoning_effort' => 'off',
            'analysis_output_tokens' => 1600,
        ];
    }

    public static function get_settings(): array {
        $settings = get_option(self::OPTION_NAME, []);
        if (!is_array($settings)) {
            $settings = [];
        }

        return array_merge(self::defaults(), $settings);
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

        $provider_id = absint($input['analysis_provider_id'] ?? 0);
        $provider = get_post($provider_id);
        if (!$provider || $provider->post_type !== 'ai_provider' || $provider->post_status !== 'publish') $provider_id = 0;
        $effort = sanitize_key($input['analysis_reasoning_effort'] ?? 'off');
        if (!in_array($effort, ['off', 'low', 'medium', 'high', 'xhigh'], true)) $effort = 'off';

        return [
            'elementor_enabled' => !empty($input['elementor_enabled']) ? '1' : '0',
            'analysis_provider_id' => $provider_id,
            'analysis_model' => sanitize_text_field($input['analysis_model'] ?? ''),
            'analysis_reasoning_effort' => $effort,
            'analysis_output_tokens' => min(8000, max(256, absint($input['analysis_output_tokens'] ?? 1600))),
        ];
    }

}
