<?php
defined('ABSPATH') || exit;

/** Owns global security settings used across public WP AIgent endpoints. */
class WP_AIGent_Security_Settings {
    public const OPTION_NAME = 'wp_aigent_security_settings';

    private const CLOUDFLARE_PROXY_CIDRS = [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];

    private const DEFAULTS = [
        'visitor_issue_limit'        => 10,
        'visitor_issue_window'       => 3600,
        'chat_visitor_rate_limit'    => 30,
        'chat_ip_rate_limit'         => 60,
        'chat_rate_window'           => 60,
        'history_visitor_rate_limit' => 60,
        'history_ip_rate_limit'      => 120,
        'history_rate_window'        => 60,
        'max_message_length'         => 2000,
        'client_ip_mode'             => 'origin_server',
        'trusted_proxy_cidrs'        => '',
    ];

    public static function all(): array {
        $stored = get_option(self::OPTION_NAME, []);
        $settings = array_merge(self::DEFAULTS, is_array($stored) ? $stored : []);

        // Preserve the intent of the unreleased single-bucket draft setting.
        if (is_array($stored) && isset($stored['chat_rate_limit']) && !isset($stored['chat_visitor_rate_limit'])) {
            $settings['chat_visitor_rate_limit'] = (int) $stored['chat_rate_limit'];
        }
        if (is_array($stored) && isset($stored['history_rate_limit']) && !isset($stored['history_visitor_rate_limit'])) {
            $settings['history_visitor_rate_limit'] = (int) $stored['history_rate_limit'];
        }
        if (is_array($stored) && !isset($stored['client_ip_mode']) && isset($stored['client_ip_source'])) {
            $settings['client_ip_mode'] = $stored['client_ip_source'] === 'remote_addr'
                ? 'origin_server'
                : 'reverse_proxy';
        }

        return $settings;
    }

    public static function get(string $key): int|string {
        $settings = self::all();
        return $settings[$key] ?? (self::DEFAULTS[$key] ?? '');
    }

    public static function trusted_proxy_cidrs(): array {
        $value = (string) self::get('trusted_proxy_cidrs');
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $value) ?: [])));
    }

    public static function cloudflare_proxy_cidrs(): array {
        return self::CLOUDFLARE_PROXY_CIDRS;
    }

    public static function sanitize($input): array {
        $input = is_array($input) ? $input : [];

        return [
            'visitor_issue_limit'        => self::bounded_int($input, 'visitor_issue_limit', 1, 1000),
            'visitor_issue_window'       => self::bounded_int($input, 'visitor_issue_window', 60, DAY_IN_SECONDS),
            'chat_visitor_rate_limit'    => self::bounded_int($input, 'chat_visitor_rate_limit', 1, 1000),
            'chat_ip_rate_limit'         => self::bounded_int($input, 'chat_ip_rate_limit', 1, 5000),
            'chat_rate_window'           => self::bounded_int($input, 'chat_rate_window', 1, HOUR_IN_SECONDS),
            'history_visitor_rate_limit' => self::bounded_int($input, 'history_visitor_rate_limit', 1, 2000),
            'history_ip_rate_limit'      => self::bounded_int($input, 'history_ip_rate_limit', 1, 10000),
            'history_rate_window'        => self::bounded_int($input, 'history_rate_window', 1, HOUR_IN_SECONDS),
            'max_message_length'         => self::bounded_int($input, 'max_message_length', 100, 20000),
            'client_ip_mode'             => self::sanitize_ip_mode($input['client_ip_mode'] ?? ''),
            'trusted_proxy_cidrs'        => self::sanitize_cidrs($input['trusted_proxy_cidrs'] ?? ''),
        ];
    }

    private static function bounded_int(array $input, string $key, int $minimum, int $maximum): int {
        $default = (int) self::DEFAULTS[$key];
        $value = isset($input[$key]) && is_scalar($input[$key]) ? (int) $input[$key] : $default;
        return max($minimum, min($maximum, $value));
    }

    private static function sanitize_ip_mode($value): string {
        $value = is_scalar($value) ? sanitize_key((string) $value) : '';
        return in_array($value, ['origin_server', 'reverse_proxy', 'cloudflare_proxy'], true)
            ? $value
            : self::DEFAULTS['client_ip_mode'];
    }

    private static function sanitize_cidrs($value): string {
        $value = is_scalar($value) ? (string) $value : '';
        $valid = [];

        foreach (preg_split('/\r\n|\r|\n/', $value) ?: [] as $cidr) {
            $cidr = trim($cidr);
            if ($cidr === '') {
                continue;
            }

            [$ip, $prefix] = array_pad(explode('/', $cidr, 2), 2, null);
            $packed = filter_var($ip, FILTER_VALIDATE_IP) !== false ? inet_pton($ip) : false;
            $max_prefix = is_string($packed) ? strlen($packed) * 8 : 0;
            if ($packed === false || $prefix === null || !ctype_digit($prefix)) {
                continue;
            }

            $prefix = (int) $prefix;
            if ($prefix < 0 || $prefix > $max_prefix) {
                continue;
            }

            $valid[] = $ip . '/' . $prefix;
        }

        return implode("\n", array_values(array_unique($valid)));
    }
}
