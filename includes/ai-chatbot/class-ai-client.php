<?php
defined('ABSPATH') || exit;

class AI_Chatbot_AI_Client {

    private array $config;
    private array $fallback_config;

    public function __construct(array $config, array $fallback_config = []) {
        $this->config = $config;
        $this->fallback_config = $fallback_config;
    }

    /**
     * Send a chat completion request to the configured platform.
     * On failure, retries through an independently configured fallback provider.
     *
     * @return array ['content' => '...', 'raw' => [...]] on success,
     *               ['error' => 'message', 'error_code' => 'code'] on failure.
     */
    public function chat(array $messages): array {
        $used_model = $this->config['chatbot_model'] ?? '';

        $result = $this->send_chat($messages, $this->config);

        if (isset($result['error']) && !empty($this->fallback_config['chatbot_model'])) {
            $primary_error = $result['error'];
            $used_model = $this->fallback_config['chatbot_model'];
            $result = $this->send_chat($messages, $this->fallback_config);

            if (isset($result['error'])) {
                $result['error'] = "{$primary_error} | fallback {$used_model} also failed: {$result['error']}";
            }
        }

        // Report the model that was actually used
        $result['model'] = $used_model;

        return $result;
    }

    private function send_chat(array $messages, array $config): array {
        return ($config['chatbot_platform'] ?? 'openai') === 'anthropic'
            ? $this->chat_anthropic($messages, $config)
            : $this->chat_openai($messages, $config);
    }

    /**
     * OpenAI-compatible API (OpenAI, OpenRouter, DeepSeek, Custom).
     */
    private function chat_openai(array $messages, array $config): array {
        $model = !empty($config['chatbot_model']) ? $config['chatbot_model'] : '';

        $body = [
            'model'      => $model,
            'messages'   => $messages,
            'max_tokens' => (int) ($config['chatbot_max_tokens'] ?? 2000),
        ];

        // Optional: Temperature
        if (!empty($config['chatbot_temperature_enabled'])
            && $config['chatbot_temperature_enabled'] === '1') {
            $body['temperature'] = (float) ($config['chatbot_temperature'] ?? 0.2);
        }

        // Optional: Reasoning Effort (OpenAI-compatible: o-series, DeepSeek, etc.)
        if (!empty($config['chatbot_thinking_enabled'])
            && $config['chatbot_thinking_enabled'] === '1') {
            $body['reasoning_effort'] = $config['chatbot_reasoning_effort'] ?? 'medium';
        }

        $api_url = rtrim($config['chatbot_api_base_url'] ?? 'https://api.openai.com/v1', '/');
        $api_key = $config['chatbot_api_key'] ?? '';

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
    private function chat_anthropic(array $messages, array $config): array {
        $api_url = rtrim($config['chatbot_api_base_url'] ?? 'https://api.anthropic.com/v1', '/');
        $api_key = $config['chatbot_api_key'] ?? '';

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
            'model'      => !empty($config['chatbot_model']) ? $config['chatbot_model'] : '',
            'messages'   => $clean_messages,
            'max_tokens' => (int) ($config['chatbot_max_tokens'] ?? 2000),
        ];

        // Optional: Temperature (not compatible with thinking — removed if thinking is enabled)
        if (!empty($config['chatbot_temperature_enabled'])
            && $config['chatbot_temperature_enabled'] === '1') {
            $body['temperature'] = (float) ($config['chatbot_temperature'] ?? 0.2);
        }

        // Optional: Extended Thinking + Reasoning Effort
        if (!empty($config['chatbot_thinking_enabled'])
            && $config['chatbot_thinking_enabled'] === '1') {
            $body['thinking'] = ['type' => 'adaptive'];

            $effort = $config['chatbot_reasoning_effort'] ?? '';
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
