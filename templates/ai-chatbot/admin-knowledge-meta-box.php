<?php
defined('ABSPATH') || exit;
/**
 * @var WP_Post $post
 */

$markdown = get_post_meta($post->ID, 'knowledge_markdown', true);
?>

<div class="ai-knowledge-meta">
    <div class="ai-chatbot-field">
        <label for="knowledge_markdown"><?php esc_html_e('Markdown Content', 'wp-aigent'); ?></label>
        <textarea id="knowledge_markdown" name="knowledge_markdown" rows="20" style="width:100%;font-family:monospace;"><?php echo esc_textarea($markdown); ?></textarea>
        <div class="description"><?php esc_html_e('Full Markdown content that will be injected into AI context.', 'wp-aigent'); ?></div>
    </div>
</div>
