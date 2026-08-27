<?php
defined('ABSPATH') || exit;

/** Public transport for the versionless AI Chat REST API. */
class AI_Chatbot_Chat_API {

    public static function register_routes(): void {
        register_rest_route('ai-chat', '/visitor', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'handle_visitor'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('ai-chat', '/chat', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'handle_chat'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('ai-chat', '/history', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'handle_history'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function handle_visitor(WP_REST_Request $request): WP_REST_Response {
        $now = time();
        $identity = WP_AIGent_Visitor_Identity::current($now);
        $credential = null;

        if ($identity === null) {
            $credential = self::issue_visitor_identity($now);
            if ($credential instanceof WP_REST_Response) {
                return $credential;
            }
            $identity = $credential;
        } elseif (WP_AIGent_Visitor_Identity::should_refresh($identity, $now)) {
            $credential = WP_AIGent_Visitor_Identity::renew($identity['visitor_id'], $now);
            $identity = $credential;
        }

        $response = new WP_REST_Response([
            'ok'   => true,
            'data' => [
                'visitor_id'                => $identity['visitor_id'],
                'credential_expires_at_gmt' => gmdate('Y-m-d\TH:i:s\Z', (int) $identity['expires_at']),
            ],
        ], 200);
        self::set_private_no_store($response);
        if ($credential !== null) {
            $response->header('Set-Cookie', WP_AIGent_Visitor_Identity::cookie_header($credential, $now));
        }

        return $response;
    }

    public static function handle_chat(WP_REST_Request $request): WP_REST_Response {
        $chatbot_id = (int) $request->get_param('chatbot_id');
        $chatbot = get_post($chatbot_id);
        if (!$chatbot || $chatbot->post_type !== 'ai_chatbot' || $chatbot->post_status !== 'publish') {
            return self::error('invalid_chatbot', __('Chatbot not found or not published.', 'wp-aigent'), 404);
        }

        $message = self::string_param($request, 'message');
        if ($message === '') {
            return self::error('empty_message', __('Message cannot be empty.', 'wp-aigent'), 400);
        }
        if (mb_strlen($message) > (int) WP_AIGent_Security_Settings::get('max_message_length')) {
            return self::error('message_too_long', __('Message exceeds maximum length.', 'wp-aigent'), 400);
        }

        $client_ip = WP_AIGent_Bootstrap::get_client_ip();
        $window = (int) WP_AIGent_Security_Settings::get('chat_rate_window');
        if (self::is_rate_limited(
            'chat_ip',
            $client_ip,
            (int) WP_AIGent_Security_Settings::get('chat_ip_rate_limit'),
            $window
        )) {
            return self::error('rate_limited', __('Too many requests. Please try again later.', 'wp-aigent'), 429);
        }

        $now = time();
        $identity = WP_AIGent_Visitor_Identity::current($now);
        $credential = null;
        if ($identity === null) {
            // Cached pre-cookie widgets may still send a client-owned UUID or
            // token. Never trust or reclaim those values; issue a new server-
            // owned identity and continue the current message instead.
            $credential = self::issue_visitor_identity($now);
            if ($credential instanceof WP_REST_Response) {
                return $credential;
            }
            $identity = $credential;
        }

        if (self::is_rate_limited(
            'chat_visitor',
            $identity['visitor_id'],
            (int) WP_AIGent_Security_Settings::get('chat_visitor_rate_limit'),
            $window
        )) {
            return self::error('rate_limited', __('Too many requests. Please try again later.', 'wp-aigent'), 429);
        }

        $service = new AI_Chatbot_Chat_Service();
        $result = $service->send(
            $chatbot_id,
            $message,
            $identity['visitor_id'],
            self::metadata($request, $identity['visitor_id']),
            $client_ip
        );

        $response = new WP_REST_Response($result['body'], (int) $result['status']);
        self::set_private_no_store($response);
        if ($credential !== null) {
            $response->header('Set-Cookie', WP_AIGent_Visitor_Identity::cookie_header($credential, $now));
        }
        return $response;
    }

    public static function handle_history(WP_REST_Request $request): WP_REST_Response {
        $identity = WP_AIGent_Visitor_Identity::current();
        if ($identity === null) {
            return self::error(
                'visitor_credential_required',
                __('A valid visitor credential is required.', 'wp-aigent'),
                401
            );
        }

        $chatbot_id = (int) $request->get_param('chatbot_id');
        $chatbot = get_post($chatbot_id);
        if (!$chatbot || $chatbot->post_type !== 'ai_chatbot' || $chatbot->post_status !== 'publish') {
            return self::error('invalid_chatbot', __('Chatbot not found.', 'wp-aigent'), 404);
        }

        $client_ip = WP_AIGent_Bootstrap::get_client_ip();
        $window = (int) WP_AIGent_Security_Settings::get('history_rate_window');
        $ip_limited = self::is_rate_limited(
            'history_ip',
            $client_ip,
            (int) WP_AIGent_Security_Settings::get('history_ip_rate_limit'),
            $window
        );
        $visitor_limited = self::is_rate_limited(
            'history_visitor',
            $identity['visitor_id'],
            (int) WP_AIGent_Security_Settings::get('history_visitor_rate_limit'),
            $window
        );
        if ($ip_limited || $visitor_limited) {
            return self::error('rate_limited', __('Too many requests. Please try again later.', 'wp-aigent'), 429);
        }

        $config = AI_Chatbot_CPT_Chatbot::get_meta($chatbot_id);
        $conversation_id = AI_Chatbot_CPT_Conversation::find_active(
            $identity['visitor_id'],
            $chatbot_id,
            (int) ($config['chatbot_session_ttl'] ?? 168)
        );
        $messages = [];

        if ($conversation_id !== null) {
            $memory = new AI_Chatbot_Memory_Manager();
            foreach ($memory->load_history($conversation_id, 50) as $history_item) {
                $messages[] = [
                    'role'    => $history_item['role'] === 'assistant' ? 'bot' : 'user',
                    'content' => $history_item['content'],
                ];
            }
        }

        $response = new WP_REST_Response([
            'ok'   => true,
            'data' => ['messages' => $messages],
        ], 200);
        self::set_private_no_store($response);
        return $response;
    }

    private static function metadata(WP_REST_Request $request, string $visitor_id): array {
        $metadata = $request->get_param('metadata');
        $metadata = is_array($metadata) ? $metadata : [];

        $normalized = [
            'page'     => self::url_value($metadata['page'] ?? ''),
            'referrer' => self::url_value($metadata['referrer'] ?? ''),
            'language' => isset($metadata['language']) && is_scalar($metadata['language'])
                ? substr(sanitize_text_field((string) $metadata['language']), 0, 32)
                : '',
        ];
        $attribution = WP_AIGent_Attribution_Sanitizer::sanitize($metadata['attribution'] ?? null, $visitor_id);
        if ($attribution !== null) {
            $attribution['event']['type'] = 'chat_message';
            $attribution['event']['lead_event_id'] = '';
            $normalized['attribution'] = $attribution;
        }

        return $normalized;
    }

    private static function url_value($value): string {
        if (!is_scalar($value)) {
            return '';
        }

        return esc_url_raw(substr((string) $value, 0, 2048));
    }

    private static function string_param(WP_REST_Request $request, string $key): string {
        $value = $request->get_param($key);
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function is_rate_limited(string $scope, string $subject, int $limit, int $window): bool {
        $key = 'wp_aigent_' . sanitize_key($scope) . '_' . hash('sha256', $subject);
        $count = get_transient($key);

        if ($count === false) {
            set_transient($key, 1, $window);
            return false;
        }

        if ((int) $count >= $limit) {
            return true;
        }

        set_transient($key, (int) $count + 1, $window);
        return false;
    }

    /** Issue a new identity subject to the public per-IP issuance limit. */
    private static function issue_visitor_identity(int $now): array|WP_REST_Response {
        $client_ip = WP_AIGent_Bootstrap::get_client_ip();
        if (self::is_rate_limited(
            'visitor_issue_ip',
            $client_ip,
            (int) WP_AIGent_Security_Settings::get('visitor_issue_limit'),
            (int) WP_AIGent_Security_Settings::get('visitor_issue_window')
        )) {
            return self::error(
                'visitor_issue_rate_limited',
                __('Too many new visitor identities. Please try again later.', 'wp-aigent'),
                429
            );
        }

        return WP_AIGent_Visitor_Identity::issue($now);
    }

    private static function error(string $code, string $message, int $status): WP_REST_Response {
        $response = new WP_REST_Response([
            'ok'      => false,
            'code'    => $code,
            'message' => $message,
        ], $status);
        self::set_private_no_store($response);
        return $response;
    }

    private static function set_private_no_store(WP_REST_Response $response): void {
        $response->header('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
    }
}
