<?php
defined('ABSPATH') || exit;

/** Coordinates independent discovery, routing and retrieval services. */
class AI_Chatbot_Knowledge_Loader {
    public function load(int $chatbot_id, array $config, string $question): array {
        $mode = $config['chatbot_knowledge_mode'] ?? 'llm_router';
        $trace = ['mode' => $mode, 'status' => 'disabled', 'candidates' => [], 'document_ids' => [], 'sources' => [], 'router' => []];
        if ($mode === 'off' || empty($config['chatbot_knowledge_ids'])) return ['context' => '', 'trace' => $trace];
        if ($mode === 'full_text_legacy') {
            $context = $this->load_full_context($chatbot_id);
            $trace['status'] = $context === '' ? 'no_match' : 'legacy';
            return ['context' => $context, 'trace' => $trace];
        }
        $candidates = (new AI_Chatbot_Knowledge_Catalog())->candidates($chatbot_id, $question, (int) $config['chatbot_knowledge_max_candidates'], (int) $config['chatbot_knowledge_catalog_budget']);
        $trace['candidates'] = array_map(static fn($card) => ['id' => $card['id'], 'title' => $card['title'], 'score' => $card['score']], $candidates);
        $ids = [];
        if ($mode === 'llm_router') {
            $router_options = $this->router_options($config);
            if (!empty($router_options['config']) && !empty($router_options['model'])) {
                $route = (new AI_Chatbot_Knowledge_Router())->route(new AI_Chatbot_AI_Client($router_options['config'], $router_options['fallback_config']), $question, $candidates, $router_options);
                $call = $route['call'] ?? [];
                $trace['router'] = ['status' => $route['status'] ?? 'unknown', 'document_ids' => $route['document_ids'] ?? [], 'confidence' => $route['confidence'] ?? 0, 'model' => $call['model'] ?? '', 'duration_ms' => $call['duration_ms'] ?? 0];
                $ids = $route['document_ids'] ?? [];
                if (!$ids) {
                    $failure_mode = $config['chatbot_knowledge_route_failure_mode'] ?? 'local_fallback';
                    if ($failure_mode === 'full_text_legacy') {
                        $trace['status'] = 'full_text_fallback';
                        return ['context' => $this->load_full_context($chatbot_id), 'trace' => $trace];
                    }
                    if ($failure_mode === 'off') {
                        $trace['status'] = 'knowledge_disabled_after_router';
                        return ['context' => '', 'trace' => $trace];
                    }
                }
                $trace['status'] = $ids ? 'routed' : 'local_fallback';
            } else {
                $trace['router'] = ['status' => 'not_configured'];
                $trace['status'] = 'local_fallback';
            }
        }
        if (!$ids) {
            $ids = array_slice(array_map(static fn($card) => (int) $card['id'], $candidates), 0, (int) $config['chatbot_knowledge_max_documents']);
            if ($mode === 'local') $trace['status'] = $ids ? 'local' : 'no_match';
        }
        $result = (new AI_Chatbot_Knowledge_Retriever())->retrieve($ids, $question, $config);
        $trace['document_ids'] = $ids;
        $trace['sources'] = array_map(static fn($source) => ['id' => $source['id'], 'title' => $source['title'], 'heading' => $source['heading']], $result['sources']);
        $trace['token_estimate'] = $result['token_estimate'];
        return ['context' => $result['context'], 'trace' => $trace];
    }

    /** Compatibility bridge for integrations still expecting full text. */
    public function load_context(int $chatbot_id): string { return $this->load_full_context($chatbot_id); }

    private function load_full_context(int $chatbot_id): string {
        $ids = (array) get_post_meta($chatbot_id, 'chatbot_knowledge_ids', true);
        $parts = [];
        foreach (get_posts(['post__in' => $ids, 'post_type' => 'ai_knowledge', 'post_status' => 'publish', 'posts_per_page' => -1]) as $doc) {
            $markdown = (string) get_post_meta($doc->ID, 'knowledge_markdown', true);
            if ($markdown !== '') $parts[] = "---\nSource: {$doc->post_title}\n{$markdown}\n---";
        }
        return apply_filters('ai_chatbot_knowledge_context', implode("\n\n", $parts), $chatbot_id);
    }

    private function router_options(array $config): array {
        $provider_id = (int) ($config['chatbot_knowledge_router_provider_id'] ?? 0);
        $connection = AI_Chatbot_CPT_Provider::get_connection_config($provider_id);
        $model = (string) ($config['chatbot_knowledge_router_model'] ?? '');
        return ['config' => $connection, 'fallback_config' => [], 'model' => $model, 'fallback_model' => '', 'max_tokens' => (int) $config['chatbot_knowledge_router_max_tokens'], 'timeout' => (int) $config['chatbot_knowledge_router_timeout'], 'max_documents' => (int) $config['chatbot_knowledge_max_documents']];
    }
}
