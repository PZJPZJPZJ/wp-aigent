<?php
defined('ABSPATH') || exit;

/** Loads complete selected documents after a router or local policy has selected them. */
class AI_Chatbot_Knowledge_Retriever {
    public function retrieve(array $document_ids): array {
        $selected = []; $used = 0;
        foreach (array_values(array_unique(array_filter(array_map('absint', $document_ids)))) as $id) {
            $post = get_post($id);
            if (!$post || $post->post_type !== 'ai_knowledge' || $post->post_status !== 'publish') continue;
            $markdown = (string) get_post_meta($id, 'knowledge_markdown', true);
            if ($markdown === '') continue;
            $selected[] = ['id' => 'K' . $id, 'document_id' => $id, 'title' => $post->post_title, 'heading' => '', 'content' => $markdown];
            $used += max(1, (int) ceil(strlen($markdown) / 4));
        }
        $context = '';
        foreach ($selected as $source) $context .= sprintf("<source id=\"%s\" title=\"%s\" heading=\"%s\">\n%s\n</source>\n", esc_attr($source['id']), esc_attr($source['title']), esc_attr($source['heading']), $source['content']);
        return ['context' => apply_filters('ai_chatbot_knowledge_context', $context), 'sources' => apply_filters('ai_chatbot_knowledge_sources', $selected, $document_ids), 'token_estimate' => $used];
    }

    private function score(string $question, string $text): int {
        preg_match_all('/[\\p{L}\\p{N}_-]{2,}/u', mb_strtolower($question), $m);
        $text = mb_strtolower($text); $score = 0;
        foreach (array_unique($m[0] ?? []) as $term) if (str_contains($text, $term)) $score++;
        return $score;
    }
}
