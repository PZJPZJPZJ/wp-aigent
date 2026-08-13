<?php
defined('ABSPATH') || exit;

class AI_Chatbot_Memory_Manager {

    private const MAX_EXCHANGES = 500;

    /**
     * Load conversation history as an array of messages for AI context.
     */
    public function load_history(int $conversation_id, int $max_history = 10): array {
        if (!$conversation_id) {
            return [];
        }

        $exchanges = get_post_meta($conversation_id, 'conversation_exchange');
        if (empty($exchanges)) {
            return [];
        }

        $messages = [];
        foreach ($exchanges as $ex) {
            $messages[] = ['role' => 'user', 'content' => $ex['user']];
            $messages[] = ['role' => 'assistant', 'content' => $ex['assistant']];
        }

        // Keep only the last N complete rounds (user + assistant pairs)
        $total = count($messages);
        $keep = $max_history * 2;
        if ($total > $keep) {
            $messages = array_slice($messages, $total - $keep);
        }

        return $messages;
    }

    /**
     * Append a message exchange to the conversation history.
     */
    public function append(int $conversation_id, string $user_message, string $assistant_reply, array $token_usage = [], string $model = '', string $error = '', string $effort = '', array $knowledge_trace = [], int $duration_ms = 0): void {
        if (!$conversation_id) {
            return;
        }

        $exchange = [
            'user'  => $user_message,
            'model' => $model,
            'time'  => current_time('mysql'),
        ];

        if (!empty($effort)) {
            $exchange['effort'] = $effort;
        }
        if (!empty($knowledge_trace)) {
            $exchange['knowledge_trace'] = $knowledge_trace;
        }
        if ($duration_ms > 0) {
            $exchange['duration_ms'] = $duration_ms;
        }

        if (!empty($error)) {
            $exchange['error'] = $error;
        } else {
            $exchange['assistant']   = $assistant_reply;
            $exchange['token_usage'] = $token_usage;
        }

        add_post_meta($conversation_id, 'conversation_exchange', $exchange);

        // Update message count
        $count = (int) get_post_meta($conversation_id, 'conversation_message_count', true);
        update_post_meta($conversation_id, 'conversation_message_count', $count + 1);

        if ($count > self::MAX_EXCHANGES) {
            $this->maybe_cull_exchanges($conversation_id);
        }
    }

    /**
     * Remove oldest exchanges when count exceeds MAX_EXCHANGES.
     */
    private function maybe_cull_exchanges(int $conversation_id): void {
        global $wpdb;

        $existing = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta}
             WHERE post_id = %d AND meta_key = 'conversation_exchange'",
            $conversation_id
        ));

        if ($existing <= self::MAX_EXCHANGES) {
            return;
        }

        // Keep the most recent 100 exchanges, delete the rest
        $keep = 100;
        $ids = $wpdb->get_col($wpdb->prepare(
            "SELECT meta_id FROM {$wpdb->postmeta}
             WHERE post_id = %d AND meta_key = 'conversation_exchange'
             ORDER BY meta_id ASC
             LIMIT %d",
            $conversation_id,
            $existing - $keep
        ));

        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '%d'));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_id IN ({$placeholders})",
                $ids
            ));
        }
    }
}
