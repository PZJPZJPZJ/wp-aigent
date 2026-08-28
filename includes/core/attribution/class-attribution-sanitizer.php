<?php
defined('ABSPATH') || exit;

/** Validates untrusted Journey-only attribution before module-owned storage. */
class WP_AIGent_Attribution_Sanitizer {
    private const MAX_BYTES = 12288;
    private const MAX_JOURNEY = 50;
    private const MAX_URL_LENGTH = 2048;

    public static function sanitize($input): ?array {
        if (!is_array($input) || !is_array($input['journey'] ?? null) || !$input['journey']) {
            return null;
        }

        $encoded = wp_json_encode($input);
        if (!is_string($encoded) || strlen($encoded) > self::MAX_BYTES || count($input['journey']) > self::MAX_JOURNEY) {
            return null;
        }

        $journey = [];
        foreach ($input['journey'] as $index => $item) {
            if (!is_array($item)) {
                return null;
            }

            $path = self::path($item['path'] ?? '');
            $time = self::timestamp($item['time'] ?? '');
            if ($path === '' || $time === '') {
                return null;
            }

            $normalized = ['path' => $path, 'time' => $time];
            if ($index === 0) {
                $source = self::text($item['source'] ?? '', 120);
                if ($source === '') {
                    return null;
                }
                $normalized['source'] = $source;
                $normalized['referrer_url'] = self::referrer($item['referrer_url'] ?? '');
            }
            $journey[] = $normalized;
        }

        $snapshot = ['journey' => $journey];
        $encoded = wp_json_encode($snapshot);
        return is_string($encoded) && strlen($encoded) <= self::MAX_BYTES ? $snapshot : null;
    }

    private static function text($value, int $length): string {
        return is_scalar($value) ? substr(sanitize_text_field((string) $value), 0, $length) : '';
    }

    private static function timestamp($value): string {
        $value = is_scalar($value) ? trim((string) $value) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,3})?Z$/', $value)) {
            return '';
        }
        $timestamp = strtotime($value);
        return $timestamp === false ? '' : gmdate('Y-m-d\TH:i:s\Z', $timestamp);
    }

    private static function path($value): string {
        if (!is_scalar($value)) {
            return '';
        }
        $path = explode('#', trim((string) $value), 2)[0];
        if ($path === '' || $path[0] !== '/') {
            return '';
        }
        return substr(sanitize_text_field($path), 0, self::MAX_URL_LENGTH);
    }

    private static function referrer($value): string {
        if (!is_scalar($value) || trim((string) $value) === '') {
            return '';
        }
        $parts = wp_parse_url((string) $value);
        if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
            return '';
        }
        $url = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) $url .= ':' . absint($parts['port']);
        $url .= isset($parts['path']) ? self::path($parts['path']) : '/';
        if (isset($parts['query']) && $parts['query'] !== '') $url .= '?' . sanitize_text_field((string) $parts['query']);
        return substr(esc_url_raw($url), 0, self::MAX_URL_LENGTH);
    }
}
