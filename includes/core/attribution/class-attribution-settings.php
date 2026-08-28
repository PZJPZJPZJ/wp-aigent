<?php
defined('ABSPATH') || exit;

/** Owns browser attribution and retained country-code feature settings. */
class WP_AIGent_Attribution_Settings {
    public const OPTION_NAME = 'wp_aigent_attribution_settings';

    private const DEFAULTS = [
        'tracking_enabled'       => '0',
        'retention_days'         => 90,
        'journey_limit'          => 20,
        'excluded_path_prefixes' => '',
        'data_layer_enabled'     => '0',
        'data_layer_event_name'  => 'elementor_form',
        'country_code_enabled'   => '0',
    ];

    public static function all(): array {
        $stored = get_option(self::OPTION_NAME, null);
        $settings = array_merge(self::DEFAULTS, is_array($stored) ? $stored : []);

        // Preserve the country-code feature until the new settings are saved.
        if (!is_array($stored) || !array_key_exists('country_code_enabled', $stored)) {
            $legacy = get_option('wp_aigent_ai_form_settings', []);
            if (is_array($legacy) && !empty($legacy['elementor_enabled'])) {
                $settings['country_code_enabled'] = '1';
            }
        }
        $settings['journey_limit'] = max(2, min(50, (int) $settings['journey_limit']));
        $settings['data_layer_event_name'] = self::sanitize_event_name($settings['data_layer_event_name'] ?? '');

        return $settings;
    }

    public static function get(string $key): int|string {
        $settings = self::all();
        return $settings[$key] ?? (self::DEFAULTS[$key] ?? '');
    }

    public static function excluded_path_prefixes(): array {
        return array_values(array_filter(array_map(
            'trim',
            preg_split('/\r\n|\r|\n/', (string) self::get('excluded_path_prefixes')) ?: []
        )));
    }

    public static function sanitize($input): array {
        $input = is_array($input) ? $input : [];

        return [
            'tracking_enabled'       => !empty($input['tracking_enabled']) ? '1' : '0',
            'retention_days'         => self::bounded_int($input, 'retention_days', 1, 365),
            'journey_limit'          => self::bounded_int($input, 'journey_limit', 2, 50),
            'excluded_path_prefixes' => self::sanitize_path_prefixes($input['excluded_path_prefixes'] ?? ''),
            'data_layer_enabled'     => !empty($input['data_layer_enabled']) ? '1' : '0',
            'data_layer_event_name'  => self::sanitize_event_name($input['data_layer_event_name'] ?? ''),
            'country_code_enabled'   => !empty($input['country_code_enabled']) ? '1' : '0',
        ];
    }

    private static function bounded_int(array $input, string $key, int $minimum, int $maximum): int {
        $value = isset($input[$key]) && is_scalar($input[$key])
            ? (int) $input[$key]
            : (int) self::DEFAULTS[$key];
        return max($minimum, min($maximum, $value));
    }

    private static function sanitize_path_prefixes($value): string {
        $value = is_scalar($value) ? (string) $value : '';
        $prefixes = [];

        foreach (array_slice(preg_split('/\r\n|\r|\n/', $value) ?: [], 0, 50) as $prefix) {
            $prefix = trim(sanitize_text_field($prefix));
            if ($prefix === '' || $prefix[0] !== '/') {
                continue;
            }
            $prefix = explode('?', explode('#', $prefix, 2)[0], 2)[0];
            $prefixes[] = substr($prefix, 0, 200);
        }

        return implode("\n", array_values(array_unique($prefixes)));
    }

    private static function sanitize_event_name($value): string {
        $value = is_scalar($value) ? substr(trim((string) $value), 0, 80) : '';
        return $value !== '' && preg_match('/^[A-Za-z0-9_.-]+$/', $value)
            ? $value
            : self::DEFAULTS['data_layer_event_name'];
    }
}
