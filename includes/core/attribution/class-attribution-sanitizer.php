<?php
defined('ABSPATH') || exit;

/** Validates untrusted browser attribution before module-owned storage. */
class WP_AIGent_Attribution_Sanitizer {
    private const MAX_BYTES = 12288;
    private const MAX_JOURNEY = 50;

    public static function sanitize($input, string $visitor_id): ?array {
        if (!is_array($input) || (int) ($input['schema_version'] ?? 0) !== 1) {
            return null;
        }

        $encoded = wp_json_encode($input);
        if (!is_string($encoded) || strlen($encoded) > self::MAX_BYTES) {
            return null;
        }

        $first_touch = self::touch($input['first_touch'] ?? null);
        $last_touch = self::touch($input['last_touch'] ?? null);
        $first_visit = self::timestamp($input['first_visit_at_gmt'] ?? '');
        $event = self::event($input['event'] ?? null);
        if ($first_touch === null || $last_touch === null || $first_visit === '' || $event === null) {
            return null;
        }

        $snapshot = [
            'schema_version'     => 1,
            'visitor_id'        => WP_AIGent_Visitor_Identity::is_valid($visitor_id) ? strtolower($visitor_id) : '',
            'first_visit_at_gmt' => $first_visit,
            'first_touch'        => $first_touch,
            'last_touch'         => $last_touch,
            'journey'            => self::journey($input['journey'] ?? []),
            'event'              => $event,
        ];

        $encoded = wp_json_encode($snapshot);
        return is_string($encoded) && strlen($encoded) <= self::MAX_BYTES ? $snapshot : null;
    }

    private static function touch($value): ?array {
        if (!is_array($value)) {
            return null;
        }

        $source = self::text($value['source'] ?? '', 120);
        $medium = self::text($value['medium'] ?? '', 120);
        $observed_at = self::timestamp($value['observed_at_gmt'] ?? '');
        if ($source === '' || $medium === '' || $observed_at === '') {
            return null;
        }

        return [
            'source'          => $source,
            'medium'          => $medium,
            'campaign'        => self::text($value['campaign'] ?? '', 200),
            'term'            => self::text($value['term'] ?? '', 200),
            'content'         => self::text($value['content'] ?? '', 200),
            'gclid'           => self::text($value['gclid'] ?? '', 200),
            'wbraid'          => self::text($value['wbraid'] ?? '', 200),
            'gbraid'          => self::text($value['gbraid'] ?? '', 200),
            'landing_path'    => self::path($value['landing_path'] ?? ''),
            'referrer_url'    => self::referrer($value['referrer_url'] ?? ''),
            'observed_at_gmt' => $observed_at,
        ];
    }

    private static function journey($value): array {
        if (!is_array($value)) {
            return [];
        }

        $journey = [];
        foreach (array_slice($value, -self::MAX_JOURNEY) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $path = self::path($item['path'] ?? '');
            $observed_at = self::timestamp($item['observed_at_gmt'] ?? '');
            if ($path !== '' && $observed_at !== '') {
                $journey[] = ['path' => $path, 'observed_at_gmt' => $observed_at];
            }
        }

        return $journey;
    }

    private static function event($value): ?array {
        if (!is_array($value)) {
            return null;
        }

        $type = sanitize_key((string) ($value['type'] ?? ''));
        $page_path = self::path($value['page_path'] ?? '');
        $observed_at = self::timestamp($value['observed_at_gmt'] ?? '');
        if (!in_array($type, ['form_submit', 'chat_message'], true) || $page_path === '' || $observed_at === '') {
            return null;
        }

        return [
            'type'            => $type,
            'lead_event_id'   => self::uuid($value['lead_event_id'] ?? ''),
            'page_path'       => $page_path,
            'observed_at_gmt' => $observed_at,
        ];
    }

    private static function text($value, int $length): string {
        return is_scalar($value) ? substr(sanitize_text_field((string) $value), 0, $length) : '';
    }

    private static function uuid($value): string {
        $value = is_scalar($value) ? strtolower(trim((string) $value)) : '';
        return WP_AIGent_Visitor_Identity::is_valid($value) ? $value : '';
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
        $path = trim((string) $value);
        $path = explode('?', explode('#', $path, 2)[0], 2)[0];
        if ($path === '' || $path[0] !== '/') {
            return '';
        }
        return substr(sanitize_text_field($path), 0, 500);
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
        if (!empty($parts['port'])) {
            $url .= ':' . absint($parts['port']);
        }
        $url .= isset($parts['path']) ? self::path($parts['path']) : '/';
        return substr(esc_url_raw($url), 0, 500);
    }
}
