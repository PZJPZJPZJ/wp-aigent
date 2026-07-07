<?php
defined('ABSPATH') || exit;

class AI_Chatbot_Export {

    public function __construct() {
        add_action('admin_post_ai_chatbot_export', [$this, 'handle_export']);
    }

    public function handle_export(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied.');
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'ai_chatbot_export')) {
            wp_die('Security check failed.');
        }

        $conversation_id = (int) ($_GET['conversation_id'] ?? 0);
        $format = $_GET['format'] ?? 'md';

        if (!$conversation_id) {
            wp_die('Invalid conversation.');
        }

        $post = get_post($conversation_id);
        if (!$post || $post->post_type !== 'ai_conversation') {
            wp_die('Conversation not found.');
        }

        $exchanges = get_post_meta($conversation_id, 'conversation_exchange');
        $history = '';
        if (!empty($exchanges)) {
            foreach ($exchanges as $ex) {
                $history .= "\n**User:** {$ex['user']}\n**Assistant:** {$ex['assistant']}";
            }
        }
        $lead = get_post_meta($conversation_id, 'conversation_lead_data', true);

        switch ($format) {
            case 'md':
                $this->export_markdown($post, $history, $lead);
                break;
            case 'json':
                $this->export_json($post, $history, $lead);
                break;
            default:
                wp_die('Unsupported format.');
        }
    }

    private function export_markdown($post, string $history, $lead): void {
        header('Content-Type: text/markdown; charset=utf-8');
        header('Content-Disposition: attachment; filename="conversation-' . $post->ID . '.md"');

        echo "# Conversation: {$post->post_title}\n\n";
        echo "**Started:** " . get_post_meta($post->ID, 'conversation_started_at', true) . "\n\n";

        if (!empty($lead)) {
            echo "## Lead Data\n\n";
            foreach ((array) $lead as $k => $v) {
                if (!is_array($v)) {
                    echo "- **{$k}:** {$v}\n";
                }
            }
            echo "\n";
        }

        echo "## Messages\n\n";
        echo $history;
        exit;
    }

    private function export_json($post, string $history, $lead): void {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="conversation-' . $post->ID . '.json"');

        echo wp_json_encode([
            'id'      => $post->ID,
            'title'   => $post->post_title,
            'started' => get_post_meta($post->ID, 'conversation_started_at', true),
            'lead'    => $lead,
            'history' => $history,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
