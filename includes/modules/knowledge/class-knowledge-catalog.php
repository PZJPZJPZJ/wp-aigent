<?php
defined('ABSPATH') || exit;

/** Builds a chatbot-scoped discovery catalogue; no document body is read here. */
class AI_Chatbot_Knowledge_Catalog {
    public function candidates(int $chatbot_id, string $question, int $limit = 12, int $char_budget = 4000): array {
        $ids = array_values(array_filter(array_map('absint', (array) get_post_meta($chatbot_id, 'chatbot_knowledge_ids', true))));
        if (!$ids) return [];
        $cards = [];
        $service = new AI_Chatbot_Knowledge_Card_Service();
        foreach ($ids as $id) {
            if (get_post_status($id) !== 'publish' || get_post_type($id) !== 'ai_knowledge') continue;
            $card = $service->get_card($id);
            $card['title'] = get_the_title($id);
            $card['score'] = $this->score($question, $card);
            $cards[] = $card;
        }
        usort($cards, static fn($a, $b) => $b['score'] <=> $a['score']);
        $cards = array_slice($cards, 0, min(20, max(1, $limit)));
        $used = 0; $result = [];
        foreach ($cards as $card) {
            $card['description'] = mb_substr($card['description'], 0, 250);
            $cost = strlen($card['title'] . $card['description'] . implode(' ', $card['tags'])) + 40;
            if ($result && $used + $cost > $char_budget) continue;
            $result[] = $card; $used += $cost;
        }
        return apply_filters('ai_chatbot_knowledge_candidates', $result, $question, $chatbot_id);
    }

    private function score(string $question, array $card): int {
        preg_match_all('/[\\p{L}\\p{N}_-]{2,}/u', mb_strtolower($question), $m);
        $terms = array_unique($m[0] ?? []);
        $title = mb_strtolower($card['title']);
        $haystack = $title . ' ' . mb_strtolower(implode(' ', $card['tags'])) . ' ' . mb_strtolower($card['description']);
        $score = 0;
        foreach ($terms as $term) {
            if (str_contains($title, $term)) $score += 8;
            elseif (str_contains($haystack, $term)) $score += 2;
        }
        return $score;
    }
}
