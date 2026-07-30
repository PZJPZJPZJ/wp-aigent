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
     * Send a request, retrying once through the independently configured fallback.
     * Request options deliberately belong to the caller's use case, not to a
     * provider connection. This lets knowledge processing reuse connections.
     */
    public function chat(array $messages, array $request_options = []): array {
        $primary = array_merge($this->config, array_intersect_key($request_options, array_flip([
            'api_model', 'api_reasoning_effort', 'api_output_tokens', 'api_timeout'
        ])));
        $fallback = $this->fallback_config;
        if (!empty($fallback)) {
            $fallback = array_merge($fallback, array_intersect_key($request_options, array_flip([
                'fallback_model', 'fallback_reasoning_effort', 'fallback_output_tokens', 'api_timeout'
            ])));
            if (isset($fallback['fallback_model'])) {
                $fallback['api_model'] = $fallback['fallback_model'];
                unset($fallback['fallback_model']);
            }
        }

        $started = microtime(true);
        $used_model = $primary['api_model'] ?? '';
        $result = $this->send_chat($messages, $primary);

        if (isset($result['error']) && !empty($fallback['api_model'])) {
            $primary_error = $result['error'];
            $used_model = $fallback['api_model'];
            $result = $this->send_chat($messages, $fallback);
            if (isset($result['error'])) {
                $result['error'] = "{$primary_error} | fallback {$used_model} also failed: {$result['error']}";
            }
        }

        $result['model'] = $used_model;
        $result['purpose'] = sanitize_key((string) ($request_options['purpose'] ?? 'answer'));
        $result['duration_ms'] = (int) round((microtime(true) - $started) * 1000);
        return $result;
    }

    private function send_chat(array $messages, array $config): array {
        switch ($config['api_protocol'] ?? 'openai_completions') {
            case 'openai_responses': return $this->chat_openai_responses($messages, $config);
            case 'anthropic': return $this->chat_anthropic($messages, $config);
            case 'gemini': return $this->chat_gemini($messages, $config);
            default: return $this->chat_openai_completions($messages, $config);
        }
    }

    private function chat_openai_completions(array $messages, array $config): array {
        $body = [
            'model' => $config['api_model'] ?? '',
            'messages' => $messages,
            'max_completion_tokens' => $this->output_tokens($config),
        ];
        if (($effort = $this->reasoning_effort($config)) !== '') $body['reasoning_effort'] = $effort;
        $data = $this->post_json($this->api_url($config, '/chat/completions'), $body, $this->bearer_headers($config), 'OpenAI Chat Completions', $this->timeout($config));
        if (isset($data['error'])) return $data;
        $content = $data['data']['choices'][0]['message']['content'] ?? '';
        return is_string($content) && $content !== '' ? ['content' => $content, 'raw' => $data['data']] : $this->response_error('OpenAI Chat Completions');
    }

    private function chat_openai_responses(array $messages, array $config): array {
        $body = ['model' => $config['api_model'] ?? '', 'max_output_tokens' => $this->output_tokens($config), 'input' => array_map(static function ($message): array {
            return ['role' => $message['role'] ?? 'user', 'content' => $message['content'] ?? ''];
        }, $messages)];
        if (($effort = $this->reasoning_effort($config)) !== '') $body['reasoning'] = ['effort' => $effort];
        $data = $this->post_json($this->api_url($config, '/responses'), $body, $this->bearer_headers($config), 'OpenAI Responses', $this->timeout($config));
        if (isset($data['error'])) return $data;
        $content = $data['data']['output_text'] ?? $this->extract_openai_response_text($data['data']['output'] ?? []);
        return is_string($content) && $content !== '' ? ['content' => $content, 'raw' => $data['data']] : $this->response_error('OpenAI Responses');
    }

    private function chat_anthropic(array $messages, array $config): array {
        $system = '';
        $clean_messages = [];
        foreach ($messages as $message) {
            if (($message['role'] ?? '') === 'system') {
                $system .= ($system === '' ? '' : "\n\n") . ($message['content'] ?? '');
            } else {
                $clean_messages[] = $message;
            }
        }
        $body = ['model' => $config['api_model'] ?? '', 'messages' => $clean_messages, 'max_tokens' => $this->output_tokens($config)];
        if ($system !== '') $body['system'] = $system;
        if (($effort = $this->reasoning_effort($config)) !== '') {
            $body['thinking'] = ['type' => 'adaptive'];
            $body['output_config'] = ['effort' => $effort];
        }
        $headers = ['Content-Type' => 'application/json', 'x-api-key' => $config['api_key'] ?? '', 'anthropic-version' => '2023-06-01'];
        $data = $this->post_json($this->api_url($config, '/messages'), $body, $headers, 'Anthropic Messages', $this->timeout($config));
        if (isset($data['error'])) return $data;
        $content = '';
        foreach (($data['data']['content'] ?? []) as $part) {
            if (($part['type'] ?? '') === 'text' && isset($part['text'])) $content .= $part['text'];
        }
        return $content !== '' ? ['content' => $content, 'raw' => $data['data']] : $this->response_error('Anthropic Messages');
    }

    private function chat_gemini(array $messages, array $config): array {
        $system_parts = [];
        $contents = [];
        foreach ($messages as $message) {
            $text = (string) ($message['content'] ?? '');
            if (($message['role'] ?? '') === 'system') {
                $system_parts[] = ['text' => $text];
            } else {
                $contents[] = ['role' => ($message['role'] ?? '') === 'assistant' ? 'model' : 'user', 'parts' => [['text' => $text]]];
            }
        }
        $body = ['contents' => $contents];
        if (!empty($system_parts)) $body['systemInstruction'] = ['parts' => $system_parts];
        $effort = $config['api_reasoning_effort'] ?? 'off';
        $budgets = ['low' => 1024, 'medium' => 4096, 'high' => 8192, 'xhigh' => 16384];
        $body['generationConfig'] = [
            'maxOutputTokens' => $this->output_tokens($config),
            'thinkingConfig' => ['thinkingBudget' => $effort === 'off' ? 0 : ($budgets[$effort] ?? 4096)],
        ];

        $model = preg_replace('#^models/#', '', (string) ($config['api_model'] ?? ''));
        $headers = ['Content-Type' => 'application/json', 'x-goog-api-key' => $config['api_key'] ?? ''];
        $data = $this->post_json($this->api_url($config, '/models/' . rawurlencode($model) . ':generateContent'), $body, $headers, 'Gemini Generate Content', $this->timeout($config));
        if (isset($data['error'])) return $data;
        $content = '';
        foreach (($data['data']['candidates'][0]['content']['parts'] ?? []) as $part) {
            if (empty($part['thought']) && isset($part['text'])) $content .= $part['text'];
        }
        return $content !== '' ? ['content' => $content, 'raw' => $data['data']] : $this->response_error('Gemini Generate Content');
    }

    /** Lists models with the selected provider's official discovery API. */
    public function list_models(): array {
        $protocol = $this->config['api_protocol'] ?? 'openai_completions';
        if ($protocol === 'gemini') return $this->list_gemini_models();
        if ($protocol === 'anthropic') return $this->list_anthropic_models();
        $data = $this->get_json($this->api_url($this->config, '/models'), $this->bearer_headers($this->config));
        if (isset($data['error'])) return [];
        return array_values(array_filter(array_map(static fn($model) => $model['id'] ?? '', $data['data']['data'] ?? [])));
    }

    private function list_anthropic_models(): array {
        $headers = ['x-api-key' => $this->config['api_key'] ?? '', 'anthropic-version' => '2023-06-01'];
        $models = [];
        $after_id = '';
        do {
            $url = $this->api_url($this->config, '/models?limit=100');
            if ($after_id !== '') $url .= '&after_id=' . rawurlencode($after_id);
            $response = $this->get_json($url, $headers);
            if (isset($response['error'])) break;
            foreach (($response['data']['data'] ?? []) as $model) if (!empty($model['id'])) $models[] = $model['id'];
            $after_id = $response['data']['last_id'] ?? '';
        } while (!empty($response['data']['has_more']) && count($models) < 500 && $after_id !== '');
        return array_slice(array_values(array_unique($models)), 0, 500);
    }

    private function list_gemini_models(): array {
        $models = [];
        $page_token = '';
        do {
            $url = $this->api_url($this->config, '/models?pageSize=1000');
            if ($page_token !== '') $url .= '&pageToken=' . rawurlencode($page_token);
            $response = $this->get_json($url, ['x-goog-api-key' => $this->config['api_key'] ?? '']);
            if (isset($response['error'])) break;
            foreach (($response['data']['models'] ?? []) as $model) {
                if (in_array('generateContent', $model['supportedGenerationMethods'] ?? [], true) && !empty($model['name'])) {
                    $models[] = preg_replace('#^models/#', '', $model['name']);
                }
            }
            $page_token = $response['data']['nextPageToken'] ?? '';
        } while ($page_token !== '' && count($models) < 500);
        return array_slice(array_values(array_unique($models)), 0, 500);
    }

    private function reasoning_effort(array $config): string {
        $effort = $config['api_reasoning_effort'] ?? 'off';
        return in_array($effort, ['low', 'medium', 'high', 'xhigh'], true) ? $effort : '';
    }

    private function output_tokens(array $config): int {
        return min(128000, max(1, absint($config['api_output_tokens'] ?? 4096)));
    }

    private function timeout(array $config): int {
        return min(120, max(1, absint($config['api_timeout'] ?? 60)));
    }

    private function api_url(array $config, string $path): string {
        return rtrim((string) ($config['api_base_url'] ?? ''), '/') . $path;
    }

    private function bearer_headers(array $config): array {
        return ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . ($config['api_key'] ?? '')];
    }

    private function post_json(string $url, array $body, array $headers, string $service, int $timeout = 60): array {
        return $this->parse_response(wp_remote_post($url, ['headers' => $headers, 'body' => wp_json_encode($body), 'timeout' => $timeout]), $service);
    }

    private function get_json(string $url, array $headers): array {
        return $this->parse_response(wp_remote_get($url, ['headers' => $headers, 'timeout' => 20]), 'Model discovery');
    }

    private function parse_response($response, string $service): array {
        if (is_wp_error($response)) return ['error' => "{$service} HTTP error: " . $response->get_error_message(), 'error_code' => 'http_error'];
        $status = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($status < 200 || $status >= 300) {
            $detail = $data['error']['message'] ?? $data['error'] ?? wp_remote_retrieve_response_message($response);
            return ['error' => "{$service} returned error status {$status}: " . (is_string($detail) ? $detail : wp_json_encode($detail)), 'error_code' => 'api_error'];
        }
        return ['data' => is_array($data) ? $data : []];
    }

    private function extract_openai_response_text(array $output): string {
        $text = '';
        foreach ($output as $item) foreach (($item['content'] ?? []) as $part) {
            if (($part['type'] ?? '') === 'output_text' && isset($part['text'])) $text .= $part['text'];
        }
        return $text;
    }

    private function response_error(string $service): array {
        return ['error' => "{$service} response missing text content", 'error_code' => 'response_error'];
    }

    /** Reserved for future RAG support. */
    public function embed(string $text): array { return []; }
}
