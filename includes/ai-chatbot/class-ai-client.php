<?php
defined('ABSPATH') || exit;

class AI_Chatbot_AI_Client {

    private array $config;

    public function __construct(array $config) {
        $this->config = $config;
    }

    /**
     * Send a chat completion request to the configured platform.
     * On failure, retries with fallback model if configured.
     *
     * @return array ['content' => '...', 'raw' => [...]] on success,
     *               ['error' => 'message', 'error_code' => 'code'] on failure.
     */
    public function chat(array $messages): array {
        $platform = $this->config['chatbot_platform'] ?? 'openai';
        $used_model = $this->config['chatbot_model'] ?? '';

        // Primary attempt
        $result = $platform === 'anthropic'
            ? $this->chat_anthropic($messages)
            : $this->chat_openai($messages);

        // Fallback retry if primary failed and fallback is configured
        if (isset($result['error'])) {
            $fallback_model = trim($this->config['chatbot_fallback_model'] ?? '');

            if ($fallback_model !== ''
                && $fallback_model !== $this->config['chatbot_model']
            ) {
                $original_model = $this->config['chatbot_model'];
                $primary_error = $result['error'];
                $used_model = $fallback_model;
                $this->config['chatbot_model'] = $fallback_model;

                $result = $platform === 'anthropic'
                    ? $this->chat_anthropic($messages)
                    : $this->chat_openai($messages);

                if (isset($result['error'])) {
                    $result['error'] = "{$primary_error} | fallback {$fallback_model} also failed: {$result['error']}";
                }

                $this->config['chatbot_model'] = $original_model;
            }
        }

        // Report the model that was actually used
        $result['model'] = $used_model;

        return $result;
    }

    /**
     * OpenAI-compatible API (OpenAI, OpenRouter, DeepSeek, Custom).
     */
    private function chat_openai(array $messages): array {
        $model = !empty($this->config['chatbot_model']) ? $this->config['chatbot_model'] : '';

        $body = [
            'model'      => $model,
            'messages'   => $messages,
            'max_tokens' => (int) ($this->config['chatbot_max_tokens'] ?? 2000),
        ];

        // Optional: Temperature
        if (!empty($this->config['chatbot_temperature_enabled'])
            && $this->config['chatbot_temperature_enabled'] === '1') {
            $body['temperature'] = (float) ($this->config['chatbot_temperature'] ?? 0.2);
        }

        // Optional: Reasoning Effort (OpenAI-compatible: o-series, DeepSeek, etc.)
        if (!empty($this->config['chatbot_thinking_enabled'])
            && $this->config['chatbot_thinking_enabled'] === '1') {
            $body['reasoning_effort'] = $this->config['chatbot_reasoning_effort'] ?? 'medium';
        }

        $api_url = rtrim($this->config['chatbot_api_base_url'] ?? 'https://api.openai.com/v1', '/');
        $api_key = $this->config['chatbot_api_key'] ?? '';

        $response = wp_remote_post($api_url . '/chat/completions', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return [
                'error'      => 'OpenAI API HTTP error: ' . $response->get_error_message(),
                'error_code' => 'http_error',
            ];
        }

        $status = wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        $data = json_decode($raw_body, true);

        if ($status !== 200) {
            $error_detail = $data['error']['message'] ?? $data['error'] ?? wp_remote_retrieve_response_message($response);
            $detail = is_string($error_detail) ? $error_detail : wp_json_encode($error_detail);
            return [
                'error'      => "OpenAI API returned error status {$status}: {$detail}",
                'error_code' => 'api_error',
            ];
        }

        if (!isset($data['choices'][0]['message']['content'])) {
            return [
                'error'      => 'OpenAI API response missing content',
                'error_code' => 'response_error',
            ];
        }

        return [
            'content' => $data['choices'][0]['message']['content'],
            'raw'     => $data,
        ];
    }

    /**
     * Anthropic API format.
     */
    private function chat_anthropic(array $messages): array {
        $api_url = rtrim($this->config['chatbot_api_base_url'] ?? 'https://api.anthropic.com/v1', '/');
        $api_key = $this->config['chatbot_api_key'] ?? '';

        // Extract system message (Anthropic uses top-level "system" field)
        $system = '';
        $clean_messages = [];
        foreach ($messages as $msg) {
            if (($msg['role'] ?? '') === 'system') {
                $system .= ($system ? "\n\n" : '') . ($msg['content'] ?? '');
            } else {
                $clean_messages[] = $msg;
            }
        }

        $body = [
            'model'      => !empty($this->config['chatbot_model']) ? $this->config['chatbot_model'] : '',
            'messages'   => $clean_messages,
            'max_tokens' => (int) ($this->config['chatbot_max_tokens'] ?? 2000),
        ];

        // Optional: Temperature (not compatible with thinking — removed if thinking is enabled)
        if (!empty($this->config['chatbot_temperature_enabled'])
            && $this->config['chatbot_temperature_enabled'] === '1') {
            $body['temperature'] = (float) ($this->config['chatbot_temperature'] ?? 0.2);
        }

        // Optional: Extended Thinking + Reasoning Effort
        if (!empty($this->config['chatbot_thinking_enabled'])
            && $this->config['chatbot_thinking_enabled'] === '1') {
            $body['thinking'] = ['type' => 'adaptive'];

            $effort = $this->config['chatbot_reasoning_effort'] ?? '';
            if (!empty($effort)) {
                $body['output_config'] = ['effort' => $effort];
            }

            // Thinking mode rejects temperature — remove if both were enabled
            unset($body['temperature']);
        }

        if (!empty($system)) {
            $body['system'] = $system;
        }

        $response = wp_remote_post($api_url . '/messages', [
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return [
                'error'      => 'Anthropic API HTTP error: ' . $response->get_error_message(),
                'error_code' => 'http_error',
            ];
        }

        $status = wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        $data = json_decode($raw_body, true);

        if ($status !== 200) {
            $error_detail = $data['error']['message'] ?? $data['error'] ?? wp_remote_retrieve_response_message($response);
            $detail = is_string($error_detail) ? $error_detail : wp_json_encode($error_detail);
            return [
                'error'      => "Anthropic API returned error status {$status}: {$detail}",
                'error_code' => 'api_error',
            ];
        }

        if (!isset($data['content'][0]['text'])) {
            return [
                'error'      => 'Anthropic API response missing content',
                'error_code' => 'response_error',
            ];
        }

        return [
            'content' => $data['content'][0]['text'],
            'raw'     => $data,
        ];
    }

    /**
     * [Reserved] Embeddings API for future RAG support.
     */
    public function embed(string $text): array {
        return [];
    }

    /**
     * List available models from the API.
     * For Anthropic: returns empty (manual entry only).
     * For OpenAI-compatible: calls GET {base_url}/models.
     */
    public function list_models(): array {
        $platform = $this->config['chatbot_platform'] ?? 'openai';

        if ($platform === 'anthropic') {
            return [];
        }

        $api_url = rtrim($this->config['chatbot_api_base_url'] ?? 'https://api.openai.com/v1', '/');
        $api_key = $this->config['chatbot_api_key'] ?? '';

        $response = wp_remote_get($api_url . '/models', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $models = $body['data'] ?? [];

        return array_values(array_map(function ($m) {
            return $m['id'] ?? '';
        }, array_filter($models, fn($m) => !empty($m['id']))));
    }
}
