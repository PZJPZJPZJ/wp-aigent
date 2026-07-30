<?php
defined('ABSPATH') || exit;
$card = (new AI_Chatbot_Knowledge_Card_Service())->get_card($post->ID);
$providers = get_posts(['post_type' => 'ai_provider', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true]);
$metadata_provider_id = absint(get_post_meta($post->ID, 'knowledge_metadata_provider_id', true));
$metadata_model = (string) get_post_meta($post->ID, 'knowledge_metadata_model', true);
$metadata_provider_meta = $metadata_provider_id ? AI_Chatbot_CPT_Provider::get_meta($metadata_provider_id) : [];
$metadata_models = is_array($metadata_provider_meta['api_provider_model_list'] ?? null) ? $metadata_provider_meta['api_provider_model_list'] : [];
?>
<div class="ai-knowledge-meta">
    <div class="ai-chatbot-field-row">
        <div class="ai-chatbot-field"><label for="knowledge_metadata_provider_id"><?php esc_html_e('Metadata Provider', 'wp-aigent'); ?></label><select id="knowledge_metadata_provider_id" name="knowledge_metadata_provider_id"><option value="0"><?php esc_html_e('— Disabled —', 'wp-aigent'); ?></option><?php foreach ($providers as $provider): ?><option value="<?php echo esc_attr($provider->ID); ?>" <?php selected($metadata_provider_id, $provider->ID); ?>><?php echo esc_html($provider->post_title); ?></option><?php endforeach; ?></select><div class="description"><?php esc_html_e('This connection generates this document’s description, tags, and an empty title. It does not affect chat replies.', 'wp-aigent'); ?></div></div>
        <div class="ai-chatbot-field"><label for="knowledge_metadata_model"><?php esc_html_e('Metadata Model', 'wp-aigent'); ?></label><select id="knowledge_metadata_model" name="knowledge_metadata_model"><option value=""><?php esc_html_e('— Disabled —', 'wp-aigent'); ?></option><?php foreach ($metadata_models as $model): ?><option value="<?php echo esc_attr($model); ?>" <?php selected($metadata_model, $model); ?>><?php echo esc_html($model); ?></option><?php endforeach; ?></select><div class="description"><?php esc_html_e('Models are loaded from the selected Metadata Provider.', 'wp-aigent'); ?></div></div>
    </div>
    <p class="description"><?php esc_html_e('These settings belong to this document. When both are configured, saving calls the model to generate this description and its tags. If the document title is empty, the same call generates it. When either setting is disabled, no metadata is generated.', 'wp-aigent'); ?></p>
    <div class="ai-chatbot-field"><label><?php esc_html_e('Description', 'wp-aigent'); ?></label><div class="ai-chatbot-readonly-value"><?php echo esc_html($card['description'] ?: '—'); ?></div></div>
    <div class="ai-chatbot-field"><label><?php esc_html_e('Generated Tags', 'wp-aigent'); ?></label><div class="ai-chatbot-readonly-value"><?php echo esc_html(implode(', ', $card['tags']) ?: '—'); ?></div></div>
    <p class="description"><?php printf(esc_html__('Status: %1$s. “Ready” means the latest description and tags are available for document selection; after selection, the full document is sent to the answer model.', 'wp-aigent'), esc_html($card['status'] ?: 'stale')); ?></p>
</div>
