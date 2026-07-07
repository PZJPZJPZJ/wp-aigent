<?php
defined('ABSPATH') || exit;

class AI_Chatbot_Lead_Processor {

    /**
     * Parse the structured JSON from AI response.
     * AI is instructed to return pure JSON; fallback to extracting from code fences.
     */
    public function parse(string $ai_response): ?array {
        // Try direct parse
        $data = json_decode($ai_response, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($data['answer'])) {
            return $data;
        }

        // Try extracting from markdown code fence
        preg_match('/```(?:json)?\s*(\{[\s\S]*?\})\s*```/', $ai_response, $matches);
        if (!empty($matches[1])) {
            $data = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE && isset($data['answer'])) {
                return $data;
            }
        }

        return null;
    }
}
