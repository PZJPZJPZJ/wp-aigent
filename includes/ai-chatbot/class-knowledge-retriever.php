<?php
defined('ABSPATH') || exit;

/** Retrieves pre-indexed chunks only after a router or local policy has selected documents. */
class AI_Chatbot_Knowledge_Retriever {
    public function retrieve(array $document_ids, string $question, array $options = []): array {
        $budget = min(16000, max(200, absint($options['context_budget'] ?? 1800)));
        $per_document = min(5, max(1, absint($options['max_chunks_per_document'] ?? 2)));
        $indexer = new AI_Chatbot_Knowledge_Indexer();
        $chunks = $indexer->get_chunks($document_ids);
        $by_doc = [];
        foreach ($chunks as $chunk) {
            $chunk['score'] = $this->score($question, $chunk['heading_path'] . ' ' . $chunk['plain_text']);
            $by_doc[$chunk['document_id']][] = $chunk;
        }
        $selected = []; $used = 0;
        foreach ($document_ids as $id) {
            $doc_chunks = $by_doc[$id] ?? [];
            usort($doc_chunks, static fn($a, $b) => $b['score'] <=> $a['score']);
            foreach (array_slice($doc_chunks, 0, $per_document) as $chunk) {
                if ($used && $used + (int) $chunk['token_estimate'] > $budget) continue;
                $post = get_post((int) $id);
                $source_id = 'K' . $id . '#' . $chunk['chunk_no'];
                $selected[] = ['id' => $source_id, 'document_id' => (int) $id, 'title' => $post ? $post->post_title : '', 'heading' => $chunk['heading_path'], 'content' => $chunk['content']];
                $used += (int) $chunk['token_estimate'];
            }
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
