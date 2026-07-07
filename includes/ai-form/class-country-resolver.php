<?php
defined('ABSPATH') || exit;

class WP_AIGent_Country_Resolver {

    public static function resolve(string $default_country = 'US', bool $use_cloudflare_country = true): string {
        $default_country = self::normalize_country($default_country) ?: 'US';

        if ($use_cloudflare_country) {
            $cloudflare_country = self::from_cloudflare_header();
            if ($cloudflare_country !== '') {
                return $cloudflare_country;
            }
        }

        return $default_country;
    }

    public static function normalize_country(string $country): string {
        $country = strtoupper(sanitize_text_field($country));
        if (!preg_match('/^[A-Z]{2}$/', $country)) {
            return '';
        }

        if (in_array($country, ['XX', 'T1'], true)) {
            return '';
        }

        return array_key_exists($country, self::countries()) ? $country : '';
    }

    private static function from_cloudflare_header(): string {
        $country = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '';
        return self::normalize_country((string) $country);
    }

    public static function country_options(): array {
        $countries = self::countries();
        uasort($countries, static function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $countries;
    }

    public static function country_select_options(): array {
        $options = [];
        foreach (self::country_options() as $country_code => $country) {
            $dial = $country['dial'] ?? '';
            $name = $country['name'] ?? $country_code;
            $options[$country_code] = $name . ' (' . $dial . ')';
        }

        return $options;
    }

    public static function countries(): array {
        $countries = include WP_AIGENT_PATH . 'templates/ai-form/country-codes.php';
        if (!is_array($countries)) {
            $countries = [];
        }

        return apply_filters('wp_aigent_phone_countries', $countries);
    }
}
