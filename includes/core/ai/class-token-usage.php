<?php
defined('ABSPATH') || exit;

/** Normalizes provider usage payloads for every AI call persisted by the plugin. */
class AI_Chatbot_Token_Usage {
    public static function normalize(array $usage): array {
        if (isset($usage['prompt_tokens']) || isset($usage['completion_tokens'])) {
            $normalized = [
                'prompt_tokens'     => (int) ($usage['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
                'total_tokens'      => (int) ($usage['total_tokens'] ?? 0),
            ];
            if (!empty($usage['prompt_tokens_details']['cached_tokens'])) {
                $normalized['cached_tokens'] = (int) $usage['prompt_tokens_details']['cached_tokens'];
            }
            return $normalized;
        }

        if (isset($usage['input_tokens']) || isset($usage['output_tokens'])) {
            $prompt = (int) ($usage['input_tokens'] ?? 0);
            $completion = (int) ($usage['output_tokens'] ?? 0);
            $normalized = [
                'prompt_tokens'     => $prompt,
                'completion_tokens' => $completion,
                'total_tokens'      => $prompt + $completion,
            ];
            if (!empty($usage['cache_read_input_tokens'])) {
                $normalized['cached_tokens'] = (int) $usage['cache_read_input_tokens'];
            }
            if (!empty($usage['cache_creation_input_tokens'])) {
                $normalized['cached_tokens'] = ($normalized['cached_tokens'] ?? 0) + (int) $usage['cache_creation_input_tokens'];
            }
            return $normalized;
        }

        return [];
    }
}
