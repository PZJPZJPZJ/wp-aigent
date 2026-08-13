<?php
defined('ABSPATH') || exit;

/** Owns the persistent, replaceable Markdown chunk index. */
class AI_Chatbot_Knowledge_Indexer {
    public const SCHEMA_VERSION = '1';

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'ai_chatbot_knowledge_chunks';
    }

    public static function install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table_name();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            document_id bigint(20) unsigned NOT NULL,
            document_hash char(64) NOT NULL,
            chunk_no int(10) unsigned NOT NULL,
            heading_path text NOT NULL,
            content longtext NOT NULL,
            plain_text longtext NOT NULL,
            keywords text NOT NULL,
            token_estimate int(10) unsigned NOT NULL DEFAULT 0,
            char_count int(10) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY document_chunk (document_id,chunk_no),
            KEY document_hash (document_hash),
            KEY document_id (document_id)
        ) {$charset};");
        update_option('ai_chatbot_knowledge_schema_version', self::SCHEMA_VERSION, false);
    }

    public static function maybe_install(): void {
        if (get_option('ai_chatbot_knowledge_schema_version') !== self::SCHEMA_VERSION) self::install();
    }

    public function rebuild(int $document_id, string $markdown = ''): array {
        global $wpdb;
        $markdown = $markdown !== '' ? $markdown : (string) get_post_meta($document_id, 'knowledge_markdown', true);
        $hash = hash('sha256', $markdown . '|chunker-v1');
        $chunks = $this->chunk($markdown);
        $table = self::table_name();
        $wpdb->delete($table, ['document_id' => $document_id], ['%d']);
        foreach ($chunks as $number => $chunk) {
            $wpdb->insert($table, [
                'document_id' => $document_id,
                'document_hash' => $hash,
                'chunk_no' => $number + 1,
                'heading_path' => $chunk['heading'],
                'content' => $chunk['content'],
                'plain_text' => $this->plain($chunk['content']),
                'keywords' => implode(' ', $this->keywords($chunk['heading'] . ' ' . $chunk['content'])),
                'token_estimate' => $this->tokens($chunk['content']),
                'char_count' => strlen($chunk['content']),
            ], ['%d','%s','%d','%s','%s','%s','%s','%d','%d']);
        }
        update_post_meta($document_id, 'knowledge_card_version_hash', $hash);
        update_post_meta($document_id, 'knowledge_card_status', 'ready');
        return ['hash' => $hash, 'chunks' => count($chunks)];
    }

    public function get_chunks(array $document_ids): array {
        global $wpdb;
        $ids = array_values(array_filter(array_map('absint', $document_ids)));
        if (!$ids) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $sql = "SELECT * FROM " . self::table_name() . " WHERE document_id IN ({$placeholders}) ORDER BY document_id, chunk_no";
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$ids), ARRAY_A) ?: [];
        // Safe lazy migration for existing documents after an upgrade. New saves
        // are indexed synchronously, so this executes at most once per legacy doc.
        $indexed = array_flip(array_map('absint', wp_list_pluck($rows, 'document_id')));
        foreach ($ids as $id) {
            if (!isset($indexed[$id]) && get_post_status($id) === 'publish') $this->rebuild($id);
        }
        if (count($indexed) !== count($ids)) $rows = $wpdb->get_results($wpdb->prepare($sql, ...$ids), ARRAY_A) ?: [];
        return $rows;
    }

    private function chunk(string $markdown): array {
        $markdown = trim($markdown);
        if ($markdown === '') return [];
        $sections = preg_split('/(?=^#{1,6}\\s+)/m', $markdown) ?: [];
        $chunks = [];
        $heading = '';
        foreach ($sections as $section) {
            $section = trim($section);
            if ($section === '') continue;
            if (preg_match('/^#{1,6}\\s+(.+)$/m', $section, $match)) $heading = trim($match[1]);
            // Keep tables/code intact; split only between prose paragraphs when needed.
            $parts = strlen($section) > 5000 ? preg_split('/\\n{2,}(?![^`]*```)/', $section) : [$section];
            $buffer = '';
            foreach ($parts as $part) {
                if ($buffer !== '' && strlen($buffer) + strlen($part) > 3500) {
                    $chunks[] = ['heading' => $heading, 'content' => $buffer];
                    $buffer = '';
                }
                $buffer .= ($buffer === '' ? '' : "\n\n") . $part;
            }
            if ($buffer !== '') $chunks[] = ['heading' => $heading, 'content' => $buffer];
        }
        return $chunks;
    }

    private function plain(string $value): string { return trim(wp_strip_all_tags(preg_replace('/[`*_>#|]/', ' ', $value))); }
    private function tokens(string $value): int { return max(1, (int) ceil(strlen($value) / 4)); }
    private function keywords(string $value): array {
        preg_match_all('/[\\p{L}\\p{N}_-]{2,}/u', mb_strtolower($this->plain($value)), $matches);
        return array_values(array_unique(array_slice($matches[0] ?? [], 0, 80)));
    }
}
