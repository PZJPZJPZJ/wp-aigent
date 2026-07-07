<?php
defined('ABSPATH') || exit;

class AI_Chatbot_Notifier {

    /**
     * Previous lead data for 'changed' operator comparison.
     * Set by notify() from conversation_lead_data post meta before evaluation.
     */
    private ?array $old_lead_data = null;

    /**
     * Send notifications if any rule matches the parsed AI response.
     * Supports: WeCom (企业微信) Webhook and Email.
     *
     * @param array $parsed  Full parsed JSON from AI (includes 'answer', 'lead')
     * @param array $visitor_data
     * @param array $config
     * @param int   $conversation_id  Save notification count to this conversation.
     */
    public function notify(array $parsed, array $visitor_data, array $config, int $conversation_id = 0): void {
        if (empty($config['chatbot_notify_enabled'])) {
            return; // disabled — no count written (0 = not sent)
        }

        // Load previous lead data for 'changed' operator comparison.
        // Note: conversation_lead_data stores the lead sub-array only (e.g. {lead_score, name, ...}),
        // but rule field paths use "lead.xxx" prefix relative to the full parsed data.
        // Wrap it so resolve_field('lead.lead_score') resolves correctly.
        $this->old_lead_data = null;
        if ($conversation_id > 0) {
            $old = get_post_meta($conversation_id, 'conversation_lead_data', true);
            if (is_array($old)) {
                $this->old_lead_data = ['lead' => $old];
            }
        }

        // Inactivity timeout: schedule WP Cron check, skip immediate rules evaluation
        if (!empty($config['chatbot_notify_inactivity_enabled'])) {
            $this->schedule_inactivity_check($conversation_id, $config);
            return;
        }

        if (!$this->should_notify($parsed, $config)) {
            return; // no match — no log written
        }

        $rule_summary = $this->get_matched_rule_summary($parsed, $config);
        $lead_data = $parsed['lead'] ?? [];
        $conv = $this->load_conversation_overview($conversation_id);
        $payload = array_merge($lead_data, ['visitor' => $visitor_data, 'conversation' => $conv]);

        $has_webhook = !empty($config['chatbot_notify_webhook']);
        $has_email   = !empty($config['chatbot_notify_email']);

        $channels = [];
        if ($has_webhook) $channels[] = 'webhook';
        if ($has_email)   $channels[] = 'email';

        $webhook_ok = true;
        $email_ok   = true;

        // Webhook (企业微信)
        if ($has_webhook) {
            $webhook_ok = $this->send_webhook($config['chatbot_notify_webhook'], $payload, $conversation_id);
        }

        // Email
        if ($has_email) {
            $email_ok = $this->send_email($config['chatbot_notify_email'], $payload, $conversation_id);
        }

        // Log notification result with rule info
        $any_ok = ($has_webhook && $webhook_ok) || ($has_email && $email_ok);
        $this->add_notification_log($conversation_id, 'rule_match', $channels, $any_ok, [
            'rule' => $rule_summary,
        ]);
    }

    /**
     * Add a notification log entry to conversation history.
     * Each entry stores: time, reason, channels, status, rule, etc.
     * Total count is derived from array length.
     */
    private function add_notification_log(int $conversation_id, string $reason, array $channels, bool $success, array $details = []): void {
        if ($conversation_id <= 0) {
            return;
        }
        $log = get_post_meta($conversation_id, 'conversation_notification_log', true);
        if (!is_array($log)) {
            $log = [];
        }
        $log[] = array_merge([
            'time'     => current_time('mysql'),
            'reason'   => $reason,
            'channels' => $channels,
            'status'   => $success ? 'success' : 'failed',
            'rule'     => '',
        ], $details);
        update_post_meta($conversation_id, 'conversation_notification_log', $log);
    }

    /**
     * Send notification for a conversation, bypassing enable/once/rules checks.
     * Used for manual "trigger notification" button in admin.
     *
     * @return bool True if at least one channel succeeded.
     */
    public function force_notify(int $conversation_id): bool {
        $lead_data = get_post_meta($conversation_id, 'conversation_lead_data', true);
        $chatbot_id = (int) get_post_meta($conversation_id, 'conversation_chatbot_id', true);

        if (!is_array($lead_data)) {
            $lead_data = [];
        }

        $config = $chatbot_id ? AI_Chatbot_CPT_Chatbot::get_meta($chatbot_id) : [];

        $has_webhook = !empty($config['chatbot_notify_webhook']);
        $has_email   = !empty($config['chatbot_notify_email']);

        if (!$has_webhook && !$has_email) {
            return false;
        }

        $visitor_data = [
            'ip'       => get_post_meta($conversation_id, 'conversation_visitor_ip', true),
            'ua'       => get_post_meta($conversation_id, 'conversation_visitor_ua', true),
            'page_url' => get_post_meta($conversation_id, 'conversation_visitor_page_url', true),
        ];

        $conv = $this->load_conversation_overview($conversation_id);
        $payload = array_merge($lead_data, ['visitor' => $visitor_data, 'conversation' => $conv]);

        $channels = [];
        if ($has_webhook) $channels[] = 'webhook';
        if ($has_email)   $channels[] = 'email';

        $webhook_ok = true;
        $email_ok   = true;

        if ($has_webhook) {
            $webhook_ok = $this->send_webhook($config['chatbot_notify_webhook'], $payload, $conversation_id);
        }
        if ($has_email) {
            $email_ok = $this->send_email($config['chatbot_notify_email'], $payload, $conversation_id);
        }

        $any_ok = ($has_webhook && $webhook_ok) || ($has_email && $email_ok);
        $this->add_notification_log($conversation_id, 'manual', $channels, $any_ok, [
            'rule' => 'Manual trigger by admin',
        ]);

        return $any_ok;
    }

    /**
     * Load conversation overview data from post meta.
     */
    public function load_conversation_overview(int $conversation_id): array {
        if ($conversation_id <= 0) {
            return [];
        }

        $chatbot_id = (int) get_post_meta($conversation_id, 'conversation_chatbot_id', true);
        $bot = $chatbot_id ? get_post($chatbot_id) : null;

        return [
            'session_id'    => get_post_meta($conversation_id, 'conversation_session_id', true),
            'chatbot_name'  => $bot ? $bot->post_title : '—',
            'chatbot_id'    => $chatbot_id,
            'message_count' => (int) get_post_meta($conversation_id, 'conversation_message_count', true),
            'started_at'    => get_post_meta($conversation_id, 'conversation_started_at', true),
            'summary'       => get_post_meta($conversation_id, 'conversation_summary', true),
        ];
    }

    /**
     * Evaluate notification rules against parsed AI data.
     * OR between rule groups, AND within each group.
     */
    public function should_notify(array $parsed, array $config): bool {
        $rules = $config['chatbot_notify_rules'] ?? null;

        // If no rules configured, fall back to legacy chatbot_notify_on_scores behavior
        if (empty($rules)) {
            $score = $parsed['lead']['lead_score'] ?? 'D';
            $notify_on = (array) ($config['chatbot_notify_on_scores'] ?? ['A', 'B']);
            return in_array($score, $notify_on, true);
        }

        // Backward compat: flat format -> single group
        if (isset($rules[0]['field'])) {
            $rules = [$rules];
        }

        foreach ($rules as $group) {
            $match = true;
            foreach ($group as $condition) {
                if (!$this->evaluate_rule($parsed, $condition)) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get a human-readable summary of which rule triggered the notification.
     * Reuses evaluate_rule() logic independently — duplicates evaluation for clarity.
     */
    private function get_matched_rule_summary(array $parsed, array $config): string {
        $rules = $config['chatbot_notify_rules'] ?? null;

        if (empty($rules)) {
            $score = $parsed['lead']['lead_score'] ?? 'D';
            return sprintf('Lead score: %s (legacy A/B rule)', $score);
        }

        // Backward compat: flat format -> single group
        if (isset($rules[0]['field'])) {
            $rules = [$rules];
        }

        foreach ($rules as $i => $group) {
            $match = true;
            $conds = [];
            foreach ($group as $condition) {
                if (!$this->evaluate_rule($parsed, $condition)) {
                    $match = false;
                    break;
                }
                $field = $condition['field'] ?? '';
                $op    = $condition['operator'] ?? 'eq';
                $val   = $condition['value'] ?? '';
                $conds[] = sprintf('%s %s %s', $field, $op, $val);
            }
            if ($match) {
                return sprintf('Group %d: %s', $i + 1, implode(' AND ', $conds));
            }
        }

        return '';
    }

    /**
     * Evaluate a single rule against the parsed data.
     */
    private function evaluate_rule(array $data, array $rule): bool {
        $field = $rule['field'] ?? '';
        $operator = $rule['operator'] ?? 'eq';
        $expected = $rule['value'] ?? null;

        if (empty($field)) {
            return false;
        }

        $actual = $this->resolve_field($data, $field);

        // If the field doesn't exist in the data, only changed/empty/not_empty/neq can proceed
        if ($actual === null && !in_array($operator, ['neq', 'changed', 'empty', 'not_empty'], true)) {
            return false;
        }

        switch ($operator) {
            case 'eq':
            case '==':
                return $actual === $expected;

            case 'neq':
            case '!=':
                return $actual !== $expected;

            case 'in':
                $values = is_array($expected)
                    ? $expected
                    : array_map('trim', explode(',', (string) $expected));
                return in_array((string) $actual, $values, true);

            case 'contains':
                return is_string($actual) && str_contains($actual, (string) $expected);

            case 'gt':
            case '>':
                return is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected;

            case 'gte':
            case '>=':
                return is_numeric($actual) && is_numeric($expected) && (float) $actual >= (float) $expected;

            case 'lt':
            case '<':
                return is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected;

            case 'lte':
            case '<=':
                return is_numeric($actual) && is_numeric($expected) && (float) $actual <= (float) $expected;

            case 'empty':
                return empty($actual) && $actual !== false && $actual !== 0;

            case 'not_empty':
                return !empty($actual) || $actual === false || $actual === 0;

            case 'changed':
                if (!isset($this->old_lead_data)) {
                    // First exchange — treat as changed from nothing so it can trigger too
                    if ($expected !== null && $expected !== '') {
                        $values = array_map('trim', explode(',', (string) $expected));
                        return in_array((string) $actual, $values, true);
                    }
                    return true;
                }
                $old_value = $this->resolve_field($this->old_lead_data, $field);
                if ($actual === $old_value) {
                    return false; // value did not change
                }
                // Value changed — if expected values specified, check match
                if ($expected !== null && $expected !== '') {
                    $values = array_map('trim', explode(',', (string) $expected));
                    return in_array((string) $actual, $values, true);
                }
                return true; // changed, no value filter

            default:
                return false;
        }
    }

    /**
     * Resolve a dot-notation field path (e.g. "lead.lead_score") against an array.
     */
    private function resolve_field(array $data, string $path) {
        $keys = explode('.', $path);
        $current = $data;

        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }

    private function send_webhook(string $url, array $payload, int $conversation_id = 0): bool {
        $markdown = $this->format_wecom_markdown($payload, $conversation_id);

        $response = wp_remote_post($url, [
            'headers'  => ['Content-Type' => 'application/json'],
            'body'     => wp_json_encode([
                'msgtype'    => 'markdown_v2',
                'markdown_v2' => ['content' => $markdown],
            ]),
            'timeout'  => 15,
            'blocking' => true,
        ]);

        if (is_wp_error($response)) {
            return false;
        }
        $code = wp_remote_retrieve_response_code($response);
        return $code >= 200 && $code < 300;
    }

    private function send_email(string $to, array $payload, int $conversation_id = 0): bool {
        $subject = sprintf(
            '[AI Chatbot] New Lead - Score %s',
            $payload['lead_score'] ?? 'N/A'
        );

        $body = $this->format_email_html($payload, $conversation_id);
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        return wp_mail($to, $subject, $body, $headers);
    }

    private function format_wecom_markdown(array $data, int $conversation_id = 0): string {
        ob_start();
        include WP_AIGENT_PATH . 'templates/ai-chatbot/notify-wecom-markdown.php';
        return ob_get_clean();
    }

    private function format_email_html(array $data, int $conversation_id = 0): string {
        ob_start();
        include WP_AIGENT_PATH . 'templates/ai-chatbot/notify-email-html.php';
        return ob_get_clean();
    }

    /**
     * Schedule a WP Cron single event to check conversation inactivity.
     * Cancels any previously scheduled event for this conversation first.
     */
    private function schedule_inactivity_check(int $conversation_id, array $config): void {
        if ($conversation_id <= 0) {
            return;
        }
        $timeout = max(1, (int) ($config['chatbot_notify_inactivity_timeout'] ?? 1));
        $delay   = $timeout * HOUR_IN_SECONDS;

        wp_clear_scheduled_hook('ai_chatbot_inactivity_notify', [$conversation_id]);
        wp_schedule_single_event(time() + $delay, 'ai_chatbot_inactivity_notify', [$conversation_id]);
    }

    /**
     * WP Cron callback: check if a conversation has been inactive long enough,
     * then evaluate notification rules. May fire once per inactivity cycle.
     */
    public static function check_inactivity_and_notify(int $conversation_id): void {
        $last_activity = (int) get_post_meta($conversation_id, 'conversation_last_activity', true);
        if ($last_activity <= 0) {
            return;
        }

        $chatbot_id = (int) get_post_meta($conversation_id, 'conversation_chatbot_id', true);
        if (!$chatbot_id) {
            return;
        }
        $config = AI_Chatbot_CPT_Chatbot::get_meta($chatbot_id);
        if (empty($config['chatbot_notify_enabled']) || empty($config['chatbot_notify_inactivity_enabled'])) {
            return;
        }

        $timeout_hours = max(1, (int) ($config['chatbot_notify_inactivity_timeout'] ?? 1));
        if (time() - $last_activity < $timeout_hours * HOUR_IN_SECONDS) {
            return;
        }

        // Dedup per cycle: skip if already notified for this inactivity period
        $log = get_post_meta($conversation_id, 'conversation_notification_log', true);
        if (is_array($log)) {
            foreach (array_reverse($log) as $entry) {
                if (($entry['reason'] ?? '') === 'inactivity') {
                    $entry_time = is_numeric($entry['time'] ?? null) ? (int) $entry['time'] : strtotime((string) ($entry['time'] ?? ''));
                    if ($entry_time > $last_activity) {
                        return; // already notified for this inactivity cycle
                    }
                    break;
                }
            }
        }

        // Reconstruct parsed data for rule evaluation
        $lead_data = get_post_meta($conversation_id, 'conversation_lead_data', true) ?: [];
        $visitor_data = [
            'ip'       => get_post_meta($conversation_id, 'conversation_visitor_ip', true),
            'ua'       => get_post_meta($conversation_id, 'conversation_visitor_ua', true),
            'page_url' => get_post_meta($conversation_id, 'conversation_visitor_page_url', true),
        ];
        $parsed = ['lead' => $lead_data, 'answer' => ''];

        $notifier = new self();
        if (!$notifier->should_notify($parsed, $config)) {
            return;
        }

        $rule_summary = $notifier->get_matched_rule_summary($parsed, $config);

        // Send notification
        $has_webhook = !empty($config['chatbot_notify_webhook']);
        $has_email   = !empty($config['chatbot_notify_email']);
        $channels    = [];
        if ($has_webhook) $channels[] = 'webhook';
        if ($has_email)   $channels[] = 'email';

        $webhook_ok = true;
        $email_ok   = true;
        $conv = $notifier->load_conversation_overview($conversation_id);
        $payload = array_merge($lead_data, ['visitor' => $visitor_data, 'conversation' => $conv]);

        if ($has_webhook) {
            $webhook_ok = $notifier->send_webhook($config['chatbot_notify_webhook'], $payload, $conversation_id);
        }
        if ($has_email) {
            $email_ok = $notifier->send_email($config['chatbot_notify_email'], $payload, $conversation_id);
        }
        $any_ok = ($has_webhook && $webhook_ok) || ($has_email && $email_ok);

        $notifier->add_notification_log($conversation_id, 'inactivity', $channels, $any_ok, [
            'rule' => $rule_summary,
        ]);
    }
}
