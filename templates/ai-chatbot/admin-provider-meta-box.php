<?php
defined('ABSPATH') || exit;
/** @var WP_Post $post */
$meta = AI_Chatbot_CPT_Provider::get_meta($post->ID);
$models = is_array($meta['provider_model_list'] ?? null) ? $meta['provider_model_list'] : [];
?>

<div class="ai-provider-editor">
    <div class="ai-provider-grid">
        <div class="ai-provider-card">
            <h3><?php esc_html_e('Connection', 'wp-aigent'); ?></h3>
            <div class="ai-chatbot-field">
                <label for="provider_platform"><?php esc_html_e('Provider Type', 'wp-aigent'); ?></label>
                <select id="provider_platform" name="provider_platform">
                    <option value="openai" <?php selected($meta['provider_platform'], 'openai'); ?>><?php esc_html_e('OpenAI-compatible', 'wp-aigent'); ?></option>
                    <option value="anthropic" <?php selected($meta['provider_platform'], 'anthropic'); ?>>Anthropic</option>
                </select>
            </div>
            <div class="ai-chatbot-field">
                <label for="provider_api_base_url"><?php esc_html_e('API Base URL', 'wp-aigent'); ?></label>
                <input type="url" id="provider_api_base_url" name="provider_api_base_url" value="<?php echo esc_attr($meta['provider_api_base_url']); ?>" />
                <div class="description"><?php esc_html_e('Use the provider default or the OpenAI-compatible endpoint supplied by your gateway.', 'wp-aigent'); ?></div>
            </div>
            <div class="ai-chatbot-field">
                <label for="provider_api_key"><?php esc_html_e('API Key', 'wp-aigent'); ?></label>
                <input type="password" id="provider_api_key" name="provider_api_key" value="" autocomplete="new-password" placeholder="<?php echo !empty($meta['provider_api_key']) ? esc_attr__('Saved securely — leave blank to keep it', 'wp-aigent') : esc_attr__('Paste API key', 'wp-aigent'); ?>" />
                <div class="description"><?php esc_html_e('Encrypted with your WordPress salts. It is never shown again after saving.', 'wp-aigent'); ?></div>
            </div>
        </div>

        <div class="ai-provider-card ai-provider-model-card">
            <div class="ai-provider-card-heading">
                <h3><?php esc_html_e('Available models', 'wp-aigent'); ?></h3>
                <div class="ai-provider-model-actions">
                    <button type="button" class="button" id="js-provider-edit-models" <?php disabled(!$post->ID); ?>><?php esc_html_e('Edit Models', 'wp-aigent'); ?></button>
                    <button type="button" class="button button-primary" id="js-provider-fetch-models" <?php disabled(!$post->ID || $meta['provider_platform'] === 'anthropic'); ?>>
                        <?php esc_html_e('Fetch Models', 'wp-aigent'); ?>
                    </button>
                </div>
                <p><?php esc_html_e('Manage the model list here. Fetch Models replaces the entire saved list.', 'wp-aigent'); ?></p>
            </div>
            <?php if (!$post->ID): ?>
                <div class="ai-provider-empty"><?php esc_html_e('Save this provider first, then fetch its model list.', 'wp-aigent'); ?></div>
            <?php else: ?>
                <div class="ai-provider-model-list" id="js-provider-model-list">
                    <?php foreach ($models as $model): ?>
                        <span class="ai-provider-model-chip" data-model="<?php echo esc_attr($model); ?>">
                            <code><?php echo esc_html($model); ?></code>
                            <button type="button" class="ai-provider-model-delete" aria-label="<?php esc_attr_e('Delete model', 'wp-aigent'); ?>" hidden>×</button>
                        </span>
                    <?php endforeach; ?>
                    <span class="ai-provider-model-input-wrap" id="js-provider-model-input-wrap" hidden>
                        <input type="text" id="js-provider-model-input" maxlength="191" placeholder="<?php esc_attr_e('Enter model ID and press Enter', 'wp-aigent'); ?>" />
                    </span>
                </div>
                <?php if ($meta['provider_platform'] === 'anthropic'): ?>
                    <p class="description"><?php esc_html_e('Anthropic does not support model discovery in this plugin. Add its model IDs manually.', 'wp-aigent'); ?></p>
                <?php endif; ?>
            <?php endif; ?>
            <p class="description" id="js-provider-fetch-status" aria-live="polite"></p>
        </div>
    </div>
</div>
