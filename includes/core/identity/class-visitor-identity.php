<?php
defined('ABSPATH') || exit;

/** Issues and verifies the server-owned visitor identity cookie. */
class WP_AIGent_Visitor_Identity {
    public const CONVERSATION_META_KEY = 'conversation_visitor_id';

    private const COOKIE_FORMAT = 's1';
    private const COOKIE_LIFETIME = 7776000; // 90 days.
    private const COOKIE_REFRESH_THRESHOLD = 1296000; // 15 days.

    /** Accept only RFC 4122 version 4 UUIDs. */
    public static function is_valid(string $visitor_id): bool {
        return (bool) preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $visitor_id);
    }

    /** Create a cryptographically random RFC 4122 version 4 UUID. */
    public static function generate_uuid(): string {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8) . '-'
            . substr($hex, 8, 4) . '-'
            . substr($hex, 12, 4) . '-'
            . substr($hex, 16, 4) . '-'
            . substr($hex, 20, 12);
    }

    /** Issue a new browser identity. The cookie value must never be returned to JavaScript. */
    public static function issue(?int $now = null): array {
        return self::credential(self::generate_uuid(), $now ?? time());
    }

    /** Renew an existing identity without changing its Visitor ID. */
    public static function renew(string $visitor_id, ?int $now = null): array {
        if (!self::is_valid($visitor_id)) {
            throw new InvalidArgumentException('A valid Visitor ID is required.');
        }

        return self::credential(strtolower($visitor_id), $now ?? time());
    }

    /** Read and verify the credential owned by the current REST host. */
    public static function current(?int $now = null): ?array {
        $cookie_name = self::cookie_name();
        $value = isset($_COOKIE[$cookie_name]) && is_scalar($_COOKIE[$cookie_name])
            ? (string) wp_unslash($_COOKIE[$cookie_name])
            : '';

        return self::verify($value, $now);
    }

    /** Verify a serialized credential and return only safe identity metadata. */
    public static function verify(string $value, ?int $now = null): ?array {
        if ($value === '' || strlen($value) > 180) {
            return null;
        }

        $parts = explode('.', $value);
        if (count($parts) !== 4) {
            return null;
        }

        [$format, $visitor_id, $expires_at, $signature] = $parts;
        $visitor_id = strtolower($visitor_id);
        if ($format !== self::COOKIE_FORMAT
            || !self::is_valid($visitor_id)
            || !preg_match('/^[1-9][0-9]{9}$/', $expires_at)
            || !preg_match('/^[a-f0-9]{64}$/', $signature)
        ) {
            return null;
        }

        $expires_at = (int) $expires_at;
        $now = $now ?? time();
        if ($expires_at <= $now || $expires_at > $now + self::COOKIE_LIFETIME) {
            return null;
        }

        $payload = self::payload($visitor_id, $expires_at);
        if (!hash_equals(self::signature($payload), $signature)) {
            return null;
        }

        return [
            'visitor_id' => $visitor_id,
            'expires_at' => $expires_at,
        ];
    }

    public static function should_refresh(array $identity, ?int $now = null): bool {
        $expires_at = isset($identity['expires_at']) ? (int) $identity['expires_at'] : 0;
        return $expires_at > 0 && $expires_at - ($now ?? time()) <= self::COOKIE_REFRESH_THRESHOLD;
    }

    /** Build the Set-Cookie value for a REST response. */
    public static function cookie_header(array $credential, ?int $now = null): string {
        $expires_at = (int) ($credential['expires_at'] ?? 0);
        $value = (string) ($credential['cookie_value'] ?? '');
        $now = $now ?? time();

        $parts = [
            self::cookie_name() . '=' . rawurlencode($value),
            'Expires=' . gmdate('D, d M Y H:i:s', $expires_at) . ' GMT',
            'Max-Age=' . max(0, $expires_at - $now),
            'Path=/',
            'HttpOnly',
            'SameSite=Lax',
        ];

        if (self::uses_secure_cookie()) {
            $parts[] = 'Secure';
        }

        return implode('; ', $parts);
    }

    /** Stable server-owned identity for an authenticated administrator preview. */
    public static function preview_id(int $user_id, int $chatbot_id): string {
        $hex = hash_hmac(
            'sha256',
            'wp-aigent|visitor-preview|' . $user_id . '|' . $chatbot_id,
            wp_salt('auth')
        );
        $hex = substr($hex, 0, 32);
        $hex[12] = '4';
        $variant = hexdec($hex[16]);
        $hex[16] = dechex(($variant & 0x3) | 0x8);

        return substr($hex, 0, 8) . '-'
            . substr($hex, 8, 4) . '-'
            . substr($hex, 12, 4) . '-'
            . substr($hex, 16, 4) . '-'
            . substr($hex, 20, 12);
    }

    public static function cookie_name(): string {
        return self::uses_secure_cookie()
            ? '__Host-wp_aigent_visitor'
            : 'wp_aigent_visitor';
    }

    public static function uses_secure_cookie(): bool {
        return function_exists('wp_is_using_https') ? wp_is_using_https() : is_ssl();
    }

    private static function credential(string $visitor_id, int $now): array {
        $expires_at = $now + self::COOKIE_LIFETIME;
        $payload = self::payload($visitor_id, $expires_at);

        return [
            'visitor_id'   => $visitor_id,
            'expires_at'   => $expires_at,
            'cookie_value' => $payload . '.' . self::signature($payload),
        ];
    }

    private static function payload(string $visitor_id, int $expires_at): string {
        return self::COOKIE_FORMAT . '.' . strtolower($visitor_id) . '.' . $expires_at;
    }

    private static function signature(string $payload): string {
        return hash_hmac('sha256', 'wp-aigent|visitor-cookie|' . $payload, wp_salt('auth'));
    }
}
