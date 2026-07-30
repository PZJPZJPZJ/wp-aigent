<?php
defined('ABSPATH') || exit;
/**
 * @var WP_Post $post
 */

$meta = AI_Chatbot_CPT_Chatbot::get_meta($post->ID);
$providers = get_posts([
    'post_type'      => 'ai_provider',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
    'no_found_rows'  => true,
]);
$provider_meta_by_id = [];
$provider_models_by_id = [];
foreach ($providers as $provider) {
    $provider_meta_by_id[$provider->ID] = AI_Chatbot_CPT_Provider::get_meta($provider->ID);
    $provider_models_by_id[$provider->ID] = is_array($provider_meta_by_id[$provider->ID]['api_provider_model_list'] ?? null)
        ? $provider_meta_by_id[$provider->ID]['api_provider_model_list']
        : [];
}
?>

<div class="ai-chatbot-meta-tabs">
    <nav class="ai-chatbot-tab-nav">
        <button type="button" class="ai-chatbot-tab-btn active" data-tab="api"><?php esc_html_e('API Provider', 'wp-aigent'); ?></button>
        <button type="button" class="ai-chatbot-tab-btn" data-tab="system"><?php esc_html_e('System Prompt', 'wp-aigent'); ?></button>
        <button type="button" class="ai-chatbot-tab-btn" data-tab="knowledge"><?php esc_html_e('Knowledge', 'wp-aigent'); ?></button>
        <button type="button" class="ai-chatbot-tab-btn" data-tab="memory"><?php esc_html_e('Memory', 'wp-aigent'); ?></button>
        <button type="button" class="ai-chatbot-tab-btn" data-tab="capture"><?php esc_html_e('Lead Capture', 'wp-aigent'); ?></button>
        <button type="button" class="ai-chatbot-tab-btn" data-tab="notify"><?php esc_html_e('Notifications', 'wp-aigent'); ?></button>
    </nav>

    <!-- API Provider -->
    <div class="ai-chatbot-tab-panel active" data-tab="api">
        <div class="ai-chatbot-provider-config-grid">
            <?php
            $cards = [
                'primary' => [
                    'title' => __('Primary', 'wp-aigent'),
                    'provider_id' => 'chatbot_primary_api_provider_id',
                    'model' => 'chatbot_primary_api_model',
                    'effort' => 'chatbot_primary_reasoning_effort',
                    'output_tokens' => 'chatbot_primary_output_tokens',
                    'empty' => __('— Select a provider —', 'wp-aigent'),
                    'description' => __('Used for every request.', 'wp-aigent'),
                ],
                'fallback' => [
                    'title' => __('Fallback', 'wp-aigent'),
                    'provider_id' => 'chatbot_fallback_api_provider_id',
                    'model' => 'chatbot_fallback_api_model',
                    'effort' => 'chatbot_fallback_reasoning_effort',
                    'output_tokens' => 'chatbot_fallback_output_tokens',
                    'empty' => __('— None (disabled) —', 'wp-aigent'),
                    'description' => __('Used only when the primary request fails.', 'wp-aigent'),
                ],
            ];
            foreach ($cards as $card):
                $selected_provider_id = (int) $meta[$card['provider_id']];
                $selected_model = (string) $meta[$card['model']];
                $models = $provider_models_by_id[$selected_provider_id] ?? [];
            ?>
                <section class="ai-chatbot-provider-config-card">
                    <h3><?php echo esc_html($card['title']); ?></h3>
                    <p><?php echo esc_html($card['description']); ?></p>
                    <div class="ai-chatbot-field">
                        <label for="<?php echo esc_attr($card['provider_id']); ?>"><?php esc_html_e('API Provider', 'wp-aigent'); ?></label>
                        <select id="<?php echo esc_attr($card['provider_id']); ?>" name="<?php echo esc_attr($card['provider_id']); ?>">
                            <option value="0"><?php echo esc_html($card['empty']); ?></option>
                            <?php foreach ($providers as $provider): ?>
                                <option value="<?php echo esc_attr($provider->ID); ?>" <?php selected((int) $meta[$card['provider_id']], $provider->ID); ?>>
                                    <?php echo esc_html($provider->post_title . ' · ' . strtoupper(str_replace('_', ' ', $provider_meta_by_id[$provider->ID]['api_provider_protocol']))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="description"><?php esc_html_e('Select the saved connection and protocol used by this request path.', 'wp-aigent'); ?></div>
                    </div>
                    <div class="ai-chatbot-field">
                        <label for="<?php echo esc_attr($card['model']); ?>"><?php esc_html_e('Model', 'wp-aigent'); ?></label>
                        <select id="<?php echo esc_attr($card['model']); ?>" name="<?php echo esc_attr($card['model']); ?>">
                            <option value=""><?php esc_html_e('— Select model —', 'wp-aigent'); ?></option>
                            <?php foreach ($models as $model): ?>
                                <option value="<?php echo esc_attr($model); ?>" <?php selected($selected_model, $model); ?>><?php echo esc_html($model); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="description"><?php esc_html_e('Models are loaded from the selected API Provider.', 'wp-aigent'); ?></div>
                    </div>
                    <div class="ai-chatbot-field">
                        <label for="<?php echo esc_attr($card['effort']); ?>"><?php esc_html_e('Thinking / Reasoning Effort', 'wp-aigent'); ?></label>
                        <select id="<?php echo esc_attr($card['effort']); ?>" name="<?php echo esc_attr($card['effort']); ?>">
                            <?php foreach (['off', 'low', 'medium', 'high', 'xhigh'] as $effort): ?>
                                <option value="<?php echo esc_attr($effort); ?>" <?php selected($meta[$card['effort']], $effort); ?>><?php echo esc_html(ucfirst($effort)); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="description"><?php esc_html_e('Off disables Thinking for this configuration.', 'wp-aigent'); ?></div>
                    </div>
                    <div class="ai-chatbot-field">
                        <label for="<?php echo esc_attr($card['output_tokens']); ?>"><?php esc_html_e('Output Tokens', 'wp-aigent'); ?></label>
                        <input type="number" id="<?php echo esc_attr($card['output_tokens']); ?>" name="<?php echo esc_attr($card['output_tokens']); ?>" value="<?php echo esc_attr($meta[$card['output_tokens']]); ?>" min="1" max="128000" step="1" />
                        <div class="description"><?php esc_html_e('Maximum tokens for the generated reply. Default: 4096.', 'wp-aigent'); ?></div>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
        <?php if (empty($providers)): ?>
            <div class="description ai-chatbot-provider-warning"><?php esc_html_e('Create and publish an API Provider before this chatbot can send messages.', 'wp-aigent'); ?></div>
        <?php endif; ?>
    </div>
    <div class="ai-chatbot-tab-panel" data-tab="system">
        <div class="ai-chatbot-field">
            <label for="chatbot_system_prompt"><?php esc_html_e('① Background Info', 'wp-aigent'); ?></label>
            <div class="description" style="margin-bottom:4px;"><?php esc_html_e('Company/product background. The AI will answer visitor questions based on this information.', 'wp-aigent'); ?></div>
            <textarea id="chatbot_system_prompt" name="chatbot_system_prompt" rows="12" style="font-family:monospace;"><?php echo esc_textarea($meta['chatbot_system_prompt']); ?></textarea>
        </div>
        <div class="ai-chatbot-field">
            <label for="chatbot_ai_rules"><?php esc_html_e('② AI Behavior Rules', 'wp-aigent'); ?></label>
            <div class="description" style="margin-bottom:4px;"><?php esc_html_e('Security rules to prevent abuse and prompt injection. The AI always follows these rules over any conflicting user instructions.', 'wp-aigent'); ?></div>
            <textarea id="chatbot_ai_rules" name="chatbot_ai_rules" rows="8" style="font-family:monospace;"><?php echo esc_textarea($meta['chatbot_ai_rules'] ?? AI_Chatbot_CPT_Chatbot::default_ai_rules()); ?></textarea>
        </div>
        <div class="ai-chatbot-field">
            <label><?php esc_html_e('③ Lead Collection Items', 'wp-aigent'); ?></label>
            <div class="description" style="margin-bottom:8px;"><?php esc_html_e('Define what visitor information the AI should collect. The field name is auto-prefixed with "lead." when sent to the AI.', 'wp-aigent'); ?></div>
            <input type="hidden" name="chatbot_json_schema_sentinel" value="1" />
            <div id="js-schema-fields">
                <?php
                $schema_items = $meta['chatbot_json_schema'] ?? [];
                if (is_string($schema_items)) {
                    // Backward compat: convert old string to array
                    $schema_items = AI_Chatbot_CPT_Chatbot::get_defaults()['chatbot_json_schema'];
                } elseif (empty($schema_items)) {
                    // Only use defaults when no meta has ever been saved
                    $stored = get_post_meta($post->ID, 'chatbot_json_schema', true);
                    if ($stored === '') {
                        $schema_items = AI_Chatbot_CPT_Chatbot::get_defaults()['chatbot_json_schema'];
                    }
                }
                // Filter out auto-managed fields (answer and summary are always injected; should_notify_sales is deprecated)
                $schema_items = array_values(array_filter($schema_items, function($item) {
                    $path = is_array($item) ? ($item['path'] ?? '') : '';
                    return $path !== 'should_notify_sales' && $path !== 'answer' && $path !== 'summary';
                }));
                $idx = 0;
                foreach ($schema_items as $item):
                    $item = (array) $item;
                ?>
                <div class="js-schema-row" data-index="<?php echo $idx; ?>">
                    <div class="js-schema-fields-row">
                        <div class="js-schema-field-path">
                            <label><?php esc_html_e('Field Name', 'wp-aigent'); ?></label>
                            <div style="display:flex;align-items:center;">
                                <code style="margin-right:4px;flex-shrink:0;">lead.</code>
                                <input type="text" name="chatbot_json_schema[<?php echo $idx; ?>][path]" value="<?php echo esc_attr(preg_replace('/^lead\./', '', $item['path'] ?? '')); ?>" placeholder="e.g. email" style="flex:1;min-width:0;" />
                            </div>
                        </div>
                        <div class="js-schema-field-type">
                            <label><?php esc_html_e('Type', 'wp-aigent'); ?></label>
                            <select name="chatbot_json_schema[<?php echo $idx; ?>][type]" style="width:100%;">
                                <option value="string" <?php selected($item['type'] ?? '', 'string'); ?>><?php esc_html_e('String', 'wp-aigent'); ?></option>
                                <option value="boolean" <?php selected($item['type'] ?? '', 'boolean'); ?>><?php esc_html_e('Boolean', 'wp-aigent'); ?></option>
                                <option value="number" <?php selected($item['type'] ?? '', 'number'); ?>><?php esc_html_e('Number', 'wp-aigent'); ?></option>
                                <option value="enum" <?php selected($item['type'] ?? '', 'enum'); ?>><?php esc_html_e('Enum', 'wp-aigent'); ?></option>
                            </select>
                        </div>
                        <div class="js-schema-field-enum js-schema-dependent" data-dep-type="enum" style="<?php echo ($item['type'] ?? '') === 'enum' ? '' : 'display:none;'; ?>">
                            <label><?php esc_html_e('Enum Values', 'wp-aigent'); ?></label>
                            <input type="text" name="chatbot_json_schema[<?php echo $idx; ?>][enum_values]" value="<?php echo esc_attr($item['enum_values'] ?? ''); ?>" placeholder="A|B|C|D" style="width:100%;" />
                        </div>
                        <div class="js-schema-field-desc">
                            <label><?php esc_html_e('Description', 'wp-aigent'); ?></label>
                            <input type="text" name="chatbot_json_schema[<?php echo $idx; ?>][description]" value="<?php echo esc_attr($item['description'] ?? ''); ?>" placeholder="What this field represents" style="width:100%;" />
                        </div>
                        <div class="js-schema-field-req">
                            <label>&nbsp;</label>
                            <label class="js-schema-req-label">
                                <input type="checkbox" name="chatbot_json_schema[<?php echo $idx; ?>][required]" value="1" <?php checked(!empty($item['required'])); ?> />
                                <?php esc_html_e('Required', 'wp-aigent'); ?>
                            </label>
                        </div>
                        <div class="js-schema-field-actions">
                            <label>&nbsp;</label>
                            <button type="button" class="js-schema-remove-row button button-small" title="<?php esc_attr_e('Remove field', 'wp-aigent'); ?>">✕</button>
                        </div>
                    </div>
                </div>
                <?php $idx++; endforeach; ?>
            </div>
            <template id="js-schema-row-tpl">
                <div class="js-schema-row" data-index="__IDX__">
                    <div class="js-schema-fields-row">
                        <div class="js-schema-field-path">
                            <label><?php esc_html_e('Field Name', 'wp-aigent'); ?></label>
                            <div style="display:flex;align-items:center;">
                                <code style="margin-right:4px;flex-shrink:0;">lead.</code>
                                <input type="text" name="chatbot_json_schema[__IDX__][path]" value="" placeholder="e.g. email" style="flex:1;min-width:0;" />
                            </div>
                        </div>
                        <div class="js-schema-field-type">
                            <label><?php esc_html_e('Type', 'wp-aigent'); ?></label>
                            <select name="chatbot_json_schema[__IDX__][type]" style="width:100%;">
                                <option value="string"><?php esc_html_e('String', 'wp-aigent'); ?></option>
                                <option value="boolean"><?php esc_html_e('Boolean', 'wp-aigent'); ?></option>
                                <option value="number"><?php esc_html_e('Number', 'wp-aigent'); ?></option>
                                <option value="enum"><?php esc_html_e('Enum', 'wp-aigent'); ?></option>
                            </select>
                        </div>
                        <div class="js-schema-field-enum js-schema-dependent" data-dep-type="enum" style="display:none;">
                            <label><?php esc_html_e('Enum Values', 'wp-aigent'); ?></label>
                            <input type="text" name="chatbot_json_schema[__IDX__][enum_values]" value="" placeholder="A|B|C|D" style="width:100%;" />
                        </div>
                        <div class="js-schema-field-desc">
                            <label><?php esc_html_e('Description', 'wp-aigent'); ?></label>
                            <input type="text" name="chatbot_json_schema[__IDX__][description]" value="" placeholder="What this field represents" style="width:100%;" />
                        </div>
                        <div class="js-schema-field-req">
                            <label>&nbsp;</label>
                            <label class="js-schema-req-label">
                                <input type="checkbox" name="chatbot_json_schema[__IDX__][required]" value="1" />
                                <?php esc_html_e('Required', 'wp-aigent'); ?>
                            </label>
                        </div>
                        <div class="js-schema-field-actions">
                            <label>&nbsp;</label>
                            <button type="button" class="js-schema-remove-row button button-small" title="<?php esc_attr_e('Remove field', 'wp-aigent'); ?>">✕</button>
                        </div>
                    </div>
                </div>
            </template>
            <div style="margin-top:8px;">
                <button type="button" class="js-schema-add-row button">+ <?php esc_html_e('Add Field', 'wp-aigent'); ?></button>
            </div>
        </div>
    </div>

    <!-- Knowledge -->
    <div class="ai-chatbot-tab-panel" data-tab="knowledge">
        <div class="ai-chatbot-field-row">
            <div class="ai-chatbot-field"><label for="chatbot_knowledge_mode"><?php esc_html_e('Retrieval Mode', 'wp-aigent'); ?></label><select id="chatbot_knowledge_mode" name="chatbot_knowledge_mode"><option value="llm_router" <?php selected($meta['chatbot_knowledge_mode'], 'llm_router'); ?>><?php esc_html_e('LLM Router', 'wp-aigent'); ?></option><option value="local" <?php selected($meta['chatbot_knowledge_mode'], 'local'); ?>><?php esc_html_e('Local Retrieval', 'wp-aigent'); ?></option><option value="full_text_legacy" <?php selected($meta['chatbot_knowledge_mode'], 'full_text_legacy'); ?>><?php esc_html_e('Full-text Compatibility', 'wp-aigent'); ?></option><option value="off" <?php selected($meta['chatbot_knowledge_mode'], 'off'); ?>><?php esc_html_e('Off', 'wp-aigent'); ?></option></select><div class="description"><?php esc_html_e('LLM Router discovers a document from cards before loading indexed chunks. Local Retrieval skips the extra model call.', 'wp-aigent'); ?></div></div>
            <div class="ai-chatbot-field"><label for="chatbot_knowledge_route_failure_mode"><?php esc_html_e('Router Failure', 'wp-aigent'); ?></label><select id="chatbot_knowledge_route_failure_mode" name="chatbot_knowledge_route_failure_mode"><option value="local_fallback" <?php selected($meta['chatbot_knowledge_route_failure_mode'], 'local_fallback'); ?>><?php esc_html_e('Use local retrieval', 'wp-aigent'); ?></option><option value="answer_without_knowledge" <?php selected($meta['chatbot_knowledge_route_failure_mode'], 'answer_without_knowledge'); ?>><?php esc_html_e('Answer without knowledge', 'wp-aigent'); ?></option></select><label style="margin-top:10px;display:block;"><input type="checkbox" name="chatbot_knowledge_show_citations" value="1" <?php checked($meta['chatbot_knowledge_show_citations'], '1'); ?> /> <?php esc_html_e('Return source citations to the visitor', 'wp-aigent'); ?></label></div>
        </div>
        <h3><?php esc_html_e('Dedicated Knowledge Router', 'wp-aigent'); ?></h3>
        <p class="description"><?php esc_html_e('This request only selects document IDs. Choose a fast, reliable model. Provider defaults can be configured in API Providers; these values override them for this chatbot.', 'wp-aigent'); ?></p>
        <div class="ai-chatbot-field-row">
            <div class="ai-chatbot-field"><label for="chatbot_knowledge_router_provider_id"><?php esc_html_e('Router Provider', 'wp-aigent'); ?></label><select id="chatbot_knowledge_router_provider_id" name="chatbot_knowledge_router_provider_id"><option value="0"><?php esc_html_e('Inherit answer provider', 'wp-aigent'); ?></option><?php foreach ($providers as $provider): ?><option value="<?php echo $provider->ID; ?>" <?php selected((int) $meta['chatbot_knowledge_router_provider_id'], $provider->ID); ?>><?php echo esc_html($provider->post_title); ?></option><?php endforeach; ?></select></div>
            <?php $router_provider_id = (int) ($meta['chatbot_knowledge_router_provider_id'] ?: $meta['chatbot_primary_api_provider_id']); $router_models = $provider_models_by_id[$router_provider_id] ?? []; ?>
            <div class="ai-chatbot-field"><label for="chatbot_knowledge_router_model"><?php esc_html_e('Router Model Override', 'wp-aigent'); ?></label><select id="chatbot_knowledge_router_model" name="chatbot_knowledge_router_model"><option value=""><?php esc_html_e('Inherit answer model', 'wp-aigent'); ?></option><?php foreach ($router_models as $model): ?><option value="<?php echo esc_attr($model); ?>" <?php selected($meta['chatbot_knowledge_router_model'], $model); ?>><?php echo esc_html($model); ?></option><?php endforeach; ?></select><div class="description"><?php esc_html_e('Models are loaded from the selected Router Provider.', 'wp-aigent'); ?></div></div>
            <div class="ai-chatbot-field"><label for="chatbot_knowledge_router_fallback_provider_id"><?php esc_html_e('Router Fallback Provider', 'wp-aigent'); ?></label><select id="chatbot_knowledge_router_fallback_provider_id" name="chatbot_knowledge_router_fallback_provider_id"><option value="0"><?php esc_html_e('— Disabled —', 'wp-aigent'); ?></option><?php foreach ($providers as $provider): ?><option value="<?php echo $provider->ID; ?>" <?php selected((int) $meta['chatbot_knowledge_router_fallback_provider_id'], $provider->ID); ?>><?php echo esc_html($provider->post_title); ?></option><?php endforeach; ?></select></div>
            <?php $router_fallback_models = $provider_models_by_id[(int) $meta['chatbot_knowledge_router_fallback_provider_id']] ?? []; ?>
            <div class="ai-chatbot-field"><label for="chatbot_knowledge_router_fallback_model"><?php esc_html_e('Router Fallback Model', 'wp-aigent'); ?></label><select id="chatbot_knowledge_router_fallback_model" name="chatbot_knowledge_router_fallback_model"><option value=""><?php esc_html_e('— Disabled —', 'wp-aigent'); ?></option><?php foreach ($router_fallback_models as $model): ?><option value="<?php echo esc_attr($model); ?>" <?php selected($meta['chatbot_knowledge_router_fallback_model'], $model); ?>><?php echo esc_html($model); ?></option><?php endforeach; ?></select></div>
            <div class="ai-chatbot-field"><label for="chatbot_knowledge_router_max_tokens"><?php esc_html_e('Router Output Tokens', 'wp-aigent'); ?></label><input type="number" id="chatbot_knowledge_router_max_tokens" name="chatbot_knowledge_router_max_tokens" value="<?php echo esc_attr($meta['chatbot_knowledge_router_max_tokens']); ?>" min="32" max="500" /></div>
            <div class="ai-chatbot-field"><label for="chatbot_knowledge_router_timeout"><?php esc_html_e('Router Timeout (seconds)', 'wp-aigent'); ?></label><input type="number" id="chatbot_knowledge_router_timeout" name="chatbot_knowledge_router_timeout" value="<?php echo esc_attr($meta['chatbot_knowledge_router_timeout']); ?>" min="1" max="30" /></div>
        </div>
        <h3><?php esc_html_e('Discovery & Context Budget', 'wp-aigent'); ?></h3>
        <div class="ai-chatbot-field-row">
            <?php foreach (['chatbot_knowledge_catalog_budget' => ['Catalog Characters', 500, 8000], 'chatbot_knowledge_max_candidates' => ['Maximum Candidates', 1, 20], 'chatbot_knowledge_max_documents' => ['Maximum Documents', 1, 3], 'chatbot_knowledge_context_budget' => ['Knowledge Token Budget', 200, 16000], 'chatbot_knowledge_max_chunks_per_document' => ['Chunks per Document', 1, 5]] as $field => $details): ?>
            <div class="ai-chatbot-field"><label for="<?php echo esc_attr($field); ?>"><?php echo esc_html__($details[0], 'wp-aigent'); ?></label><input type="number" id="<?php echo esc_attr($field); ?>" name="<?php echo esc_attr($field); ?>" value="<?php echo esc_attr($meta[$field]); ?>" min="<?php echo $details[1]; ?>" max="<?php echo $details[2]; ?>" /></div>
            <?php endforeach; ?>
        </div>
        <div class="ai-chatbot-field">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                <label style="margin:0;"><?php esc_html_e('Knowledge Documents', 'wp-aigent'); ?></label>
                <?php
                $docs = get_posts(['post_type' => 'ai_knowledge', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true]);
                $selected_ids = (array) ($meta['chatbot_knowledge_ids'] ?? []);
                $total_docs = count($docs);
                $selected_count = count(array_intersect($selected_ids, wp_list_pluck($docs, 'ID')));
                if ($total_docs > 0) {
                    echo '<span class="ai-chatbot-badge" style="font-size:11px;background:#e0e0e0;padding:2px 8px;border-radius:10px;color:#555;">' . esc_html($selected_count) . ' / ' . esc_html($total_docs) . ' ' . esc_html__('selected', 'wp-aigent') . '</span>';
                }
                ?>
            </div>
            <div class="ai-chatbot-checkbox-list" style="border:1px solid #e0e0e0;border-radius:4px;padding:4px 0;max-height:320px;overflow-y:auto;">
                <?php
                if (empty($docs)) {
                    echo '<div style="padding:16px;text-align:center;color:#999;">' . esc_html__('No knowledge documents yet. Create one under Knowledge Base.', 'wp-aigent') . '</div>';
                }
                foreach ($docs as $doc) {
                    echo '<label class="ai-chatbot-checkbox-item" style="display:flex;align-items:center;padding:6px 10px;margin:2px 4px;border-radius:3px;cursor:pointer;transition:background 0.1s;">';
                    echo '<input type="checkbox" name="chatbot_knowledge_ids[]" value="' . esc_attr($doc->ID) . '" ' . checked(in_array($doc->ID, $selected_ids), true, false) . ' style="margin-right:8px;">';
                    $card = (new AI_Chatbot_Knowledge_Card_Service())->get_card($doc->ID);
                    echo '<span><strong>' . esc_html($doc->post_title) . '</strong><br><small>' . esc_html($card['description']) . ' · ' . esc_html($card['status']) . '</small></span>';
                    echo '</label>';
                }
                ?>
            </div>
            <div class="description" style="margin-top:8px;"><?php esc_html_e('Tick the documents you want the AI to reference during conversations.', 'wp-aigent'); ?></div>
        </div>
    </div>

    <!-- Memory -->
    <div class="ai-chatbot-tab-panel" data-tab="memory">
        <div class="ai-chatbot-field-row">
            <div class="ai-chatbot-field">
                <label for="chatbot_max_history"><?php esc_html_e('Max History Rounds', 'wp-aigent'); ?></label>
                <input type="number" id="chatbot_max_history" name="chatbot_max_history" value="<?php echo esc_attr($meta['chatbot_max_history']); ?>" min="0" max="100" />
                <div class="description"><?php esc_html_e('Number of past conversation rounds sent to AI as context (0 = no history).', 'wp-aigent'); ?></div>
            </div>
            <div class="ai-chatbot-field">
                <label for="chatbot_session_ttl"><?php esc_html_e('Session TTL (hours)', 'wp-aigent'); ?></label>
                <div style="display:flex;align-items:center;gap:6px;">
                    <input type="number" id="chatbot_session_ttl" name="chatbot_session_ttl" value="<?php echo esc_attr($meta['chatbot_session_ttl']); ?>" min="1" max="720" style="width:80px;" />
                    <span><?php esc_html_e('hours', 'wp-aigent'); ?></span>
                </div>
                <div class="description"><?php esc_html_e('Inactivity timeout. After this period a new conversation starts (old one kept as history).', 'wp-aigent'); ?></div>
            </div>
        </div>
        <div class="ai-chatbot-variables" style="margin-top:12px;">
            <strong><?php esc_html_e('Session Info', 'wp-aigent'); ?></strong><br>
            <?php esc_html_e('Each visitor gets a unique UUID stored in browser localStorage. Session ID format:', 'wp-aigent'); ?>
            <code>sess_{md5(visitor_id + chatbot_id)}</code><br>
            <?php esc_html_e('When a session expires, a new conversation is created automatically.', 'wp-aigent'); ?>
        </div>
    </div>

    <!-- Lead Capture -->
    <div class="ai-chatbot-tab-panel" data-tab="capture">
        <div class="ai-chatbot-field">
            <label>
                <input type="checkbox" name="chatbot_lead_capture_enabled" value="1" <?php checked($meta['chatbot_lead_capture_enabled'] ?? '1', '1'); ?> />
                <?php esc_html_e('Enable Lead Capture Form', 'wp-aigent'); ?>
            </label>
            <div class="description" style="margin-top:4px;"><?php esc_html_e('When enabled, a contact form popup appears when all rules below are met. The visitor\'s submission is sent to the AI as a chat message.', 'wp-aigent'); ?></div>
        </div>
        <div class="ai-chatbot-field">
            <label><?php esc_html_e('Form Fields', 'wp-aigent'); ?></label>
            <div class="description" style="margin-bottom:8px;"><?php esc_html_e('Define the input fields shown in the contact form. The "name" value is used as the field key when sending to AI.', 'wp-aigent'); ?></div>
            <input type="hidden" name="chatbot_lead_fields_sentinel" value="1" />
            <div id="js-lead-fields">
                <?php
                $lead_fields = $meta['chatbot_lead_fields'] ?? [];
                if (is_string($lead_fields)) {
                    $lead_fields = AI_Chatbot_CPT_Chatbot::get_defaults()['chatbot_lead_fields'];
                }
                if (empty($lead_fields)) {
                    $lead_fields = AI_Chatbot_CPT_Chatbot::get_defaults()['chatbot_lead_fields'];
                }
                $lidx = 0;
                foreach ($lead_fields as $lf):
                    $lf = (array) $lf;
                ?>
                <div class="js-lead-field-row" data-index="<?php echo $lidx; ?>">
                    <div class="js-notify-fields-row">
                        <div class="js-schema-field-path" style="flex:1;">
                            <label><?php esc_html_e('Name', 'wp-aigent'); ?></label>
                            <input type="text" name="chatbot_lead_fields[<?php echo $lidx; ?>][name]" value="<?php echo esc_attr($lf['name'] ?? ''); ?>" placeholder="email" style="width:100%;" />
                        </div>
                        <div class="js-schema-field-desc" style="flex:1.5;">
                            <label><?php esc_html_e('Placeholder', 'wp-aigent'); ?></label>
                            <input type="text" name="chatbot_lead_fields[<?php echo $lidx; ?>][placeholder]" value="<?php echo esc_attr($lf['placeholder'] ?? ''); ?>" placeholder="Email Address" style="width:100%;" />
                        </div>
                        <div class="js-notify-field-actions" style="flex:0 0 auto;">
                            <label>&nbsp;</label>
                            <button type="button" class="js-lead-field-remove button button-small" title="<?php esc_attr_e('Remove field', 'wp-aigent'); ?>">✕</button>
                        </div>
                    </div>
                </div>
                <?php $lidx++; endforeach; ?>
            </div>
            <template id="js-lead-field-tpl">
                <div class="js-lead-field-row" data-index="__LIDX__">
                    <div class="js-notify-fields-row">
                        <div class="js-schema-field-path" style="flex:1;">
                            <label><?php esc_html_e('Name', 'wp-aigent'); ?></label>
                            <input type="text" name="chatbot_lead_fields[__LIDX__][name]" value="" placeholder="email" style="width:100%;" />
                        </div>
                        <div class="js-schema-field-desc" style="flex:1.5;">
                            <label><?php esc_html_e('Placeholder', 'wp-aigent'); ?></label>
                            <input type="text" name="chatbot_lead_fields[__LIDX__][placeholder]" value="" placeholder="Email Address" style="width:100%;" />
                        </div>
                        <div class="js-notify-field-actions" style="flex:0 0 auto;">
                            <label>&nbsp;</label>
                            <button type="button" class="js-lead-field-remove button button-small" title="<?php esc_attr_e('Remove field', 'wp-aigent'); ?>">✕</button>
                        </div>
                    </div>
                </div>
            </template>
            <div style="margin-top:8px;">
                <button type="button" class="js-lead-field-add button">+ <?php esc_html_e('Add Field', 'wp-aigent'); ?></button>
            </div>
        </div>
        <div class="ai-chatbot-field">
            <label><?php esc_html_e('Trigger Rules (OR between groups, AND within each group)', 'wp-aigent'); ?></label>
            <div class="description" style="margin-bottom:8px;"><?php esc_html_e('Define rule groups. Rules are OR\'d — any matching group triggers the form. Conditions within a group are AND\'d — all must match for that group to fire.', 'wp-aigent'); ?></div>
            <input type="hidden" name="chatbot_lead_capture_rules_sentinel" value="1" />
            <div id="js-capture-rules-fields">
                <?php
                $capture_groups = $meta['chatbot_lead_capture_rules'] ?? [];
                if (is_string($capture_groups)) {
                    $capture_groups = AI_Chatbot_CPT_Chatbot::get_defaults()['chatbot_lead_capture_rules'];
                }
                if (empty($capture_groups)) {
                    $capture_groups = AI_Chatbot_CPT_Chatbot::get_defaults()['chatbot_lead_capture_rules'];
                }
                $gidx = 0;
                foreach ($capture_groups as $group):
                    $group = (array) $group;
                ?>
                <div class="ai-chatbot-rule-group" data-group-index="<?php echo $gidx; ?>">
                    <?php if ($gidx > 0): ?>
                    <div class="ai-chatbot-rule-group-or"><?php esc_html_e('OR', 'wp-aigent'); ?></div>
                    <?php endif; ?>
                    <div class="ai-chatbot-rule-group-body">
                        <div class="ai-chatbot-rule-group-header">
                            <strong><?php printf(esc_html__('Rule Group %d', 'wp-aigent'), $gidx + 1); ?></strong>
                        </div>
                        <div class="ai-chatbot-rule-group-conditions">
                            <?php $cidx = 0; foreach ($group as $condition):
                                $condition = (array) $condition;
                            ?>
                            <div class="ai-chatbot-condition-row" data-cond-index="<?php echo $cidx; ?>">
                                <div class="js-notify-fields-row">
                                    <div class="js-notify-field-path">
                                        <label><?php esc_html_e('Field', 'wp-aigent'); ?></label>
                                        <div style="display:flex;align-items:center;">
                                            <code style="margin-right:4px;flex-shrink:0;">lead.</code>
                                            <input type="text" name="chatbot_lead_capture_rules[<?php echo $gidx; ?>][<?php echo $cidx; ?>][field]" value="<?php echo esc_attr(preg_replace('/^lead\./', '', $condition['field'] ?? '')); ?>" placeholder="e.g. lead_score" style="flex:1;min-width:0;" />
                                        </div>
                                    </div>
                                    <div class="js-notify-field-operator">
                                        <label><?php esc_html_e('Operator', 'wp-aigent'); ?></label>
                                        <select name="chatbot_lead_capture_rules[<?php echo $gidx; ?>][<?php echo $cidx; ?>][operator]" style="width:100%;">
                                            <option value="eq" <?php selected($condition['operator'] ?? '', 'eq'); ?>><?php esc_html_e('equals (=)', 'wp-aigent'); ?></option>
                                            <option value="neq" <?php selected($condition['operator'] ?? '', 'neq'); ?>><?php esc_html_e('not equals (!=)', 'wp-aigent'); ?></option>
                                            <option value="in" <?php selected($condition['operator'] ?? '', 'in'); ?>><?php esc_html_e('in (comma-separated)', 'wp-aigent'); ?></option>
                                            <option value="contains" <?php selected($condition['operator'] ?? '', 'contains'); ?>><?php esc_html_e('contains', 'wp-aigent'); ?></option>
                                            <option value="gt" <?php selected($condition['operator'] ?? '', 'gt'); ?>><?php esc_html_e('greater than (>)', 'wp-aigent'); ?></option>
                                            <option value="lt" <?php selected($condition['operator'] ?? '', 'lt'); ?>><?php esc_html_e('less than (<)', 'wp-aigent'); ?></option>
                                            <option value="gte" <?php selected($condition['operator'] ?? '', 'gte'); ?>><?php esc_html_e('>=', 'wp-aigent'); ?></option>
                                            <option value="lte" <?php selected($condition['operator'] ?? '', 'lte'); ?>><?php esc_html_e('<=', 'wp-aigent'); ?></option>
                                            <option value="empty" <?php selected($condition['operator'] ?? '', 'empty'); ?>><?php esc_html_e('is empty', 'wp-aigent'); ?></option>
                                            <option value="not_empty" <?php selected($condition['operator'] ?? '', 'not_empty'); ?>><?php esc_html_e('is not empty', 'wp-aigent'); ?></option>
                                            <option value="changed" <?php selected($condition['operator'] ?? '', 'changed'); ?>><?php esc_html_e('changed (changes to any specified value)', 'wp-aigent'); ?></option>
                                        </select>
                                    </div>
                                    <div class="js-notify-field-value">
                                        <label><?php esc_html_e('Value', 'wp-aigent'); ?></label>
                                        <input type="text" name="chatbot_lead_capture_rules[<?php echo $gidx; ?>][<?php echo $cidx; ?>][value]" value="<?php echo esc_attr($condition['value'] ?? ''); ?>" style="width:100%;" />
                                    </div>
                                    <div class="js-notify-field-actions">
                                        <label>&nbsp;</label>
                                        <button type="button" class="js-capture-remove-condition button button-small" title="<?php esc_attr_e('Remove condition', 'wp-aigent'); ?>">✕</button>
                                    </div>
                                </div>
                            </div>
                            <?php $cidx++; endforeach; ?>
                        </div>
                        <div class="ai-chatbot-rule-group-actions">
                            <button type="button" class="js-capture-add-condition button button-small">+ <?php esc_html_e('Add Condition', 'wp-aigent'); ?></button>
                            <button type="button" class="js-capture-remove-group button button-small"><?php esc_html_e('Remove Group', 'wp-aigent'); ?></button>
                        </div>
                    </div>
                </div>
                <?php $gidx++; endforeach; ?>
            </div>
            <template id="js-capture-group-tpl">
                <div class="ai-chatbot-rule-group" data-group-index="__GIDX__">
                    <div class="ai-chatbot-rule-group-or"><?php esc_html_e('OR', 'wp-aigent'); ?></div>
                    <div class="ai-chatbot-rule-group-body">
                        <div class="ai-chatbot-rule-group-header">
                            <strong><?php esc_html_e('New Rule Group', 'wp-aigent'); ?></strong>
                        </div>
                        <div class="ai-chatbot-rule-group-conditions"></div>
                        <div class="ai-chatbot-rule-group-actions">
                            <button type="button" class="js-capture-add-condition button button-small">+ <?php esc_html_e('Add Condition', 'wp-aigent'); ?></button>
                            <button type="button" class="js-capture-remove-group button button-small"><?php esc_html_e('Remove Group', 'wp-aigent'); ?></button>
                        </div>
                    </div>
                </div>
            </template>
            <template id="js-capture-condition-tpl">
                <div class="ai-chatbot-condition-row" data-cond-index="__CIDX__">
                    <div class="js-notify-fields-row">
                        <div class="js-notify-field-path">
                            <label><?php esc_html_e('Field', 'wp-aigent'); ?></label>
                            <div style="display:flex;align-items:center;">
                                <code style="margin-right:4px;flex-shrink:0;">lead.</code>
                                <input type="text" name="chatbot_lead_capture_rules[__GIDX__][__CIDX__][field]" value="" placeholder="e.g. lead_score" style="flex:1;min-width:0;" />
                            </div>
                        </div>
                        <div class="js-notify-field-operator">
                            <label><?php esc_html_e('Operator', 'wp-aigent'); ?></label>
                            <select name="chatbot_lead_capture_rules[__GIDX__][__CIDX__][operator]" style="width:100%;">
                                <option value="eq"><?php esc_html_e('equals (=)', 'wp-aigent'); ?></option>
                                <option value="neq"><?php esc_html_e('not equals (!=)', 'wp-aigent'); ?></option>
                                <option value="in"><?php esc_html_e('in (comma-separated)', 'wp-aigent'); ?></option>
                                <option value="contains"><?php esc_html_e('contains', 'wp-aigent'); ?></option>
                                <option value="gt"><?php esc_html_e('greater than (>)', 'wp-aigent'); ?></option>
                                <option value="lt"><?php esc_html_e('less than (<)', 'wp-aigent'); ?></option>
                                <option value="gte"><?php esc_html_e('>=', 'wp-aigent'); ?></option>
                                <option value="lte"><?php esc_html_e('<=', 'wp-aigent'); ?></option>
                                <option value="empty"><?php esc_html_e('is empty', 'wp-aigent'); ?></option>
                                <option value="not_empty"><?php esc_html_e('is not empty', 'wp-aigent'); ?></option>
                                <option value="changed"><?php esc_html_e('changed (changes to any specified value)', 'wp-aigent'); ?></option>
                            </select>
                        </div>
                        <div class="js-notify-field-value">
                            <label><?php esc_html_e('Value', 'wp-aigent'); ?></label>
                            <input type="text" name="chatbot_lead_capture_rules[__GIDX__][__CIDX__][value]" value="" style="width:100%;" />
                        </div>
                        <div class="js-notify-field-actions">
                            <label>&nbsp;</label>
                            <button type="button" class="js-capture-remove-condition button button-small" title="<?php esc_attr_e('Remove condition', 'wp-aigent'); ?>">✕</button>
                        </div>
                    </div>
                </div>
            </template>
            <div style="margin-top:8px;">
                <button type="button" class="js-capture-add-group button">+ <?php esc_html_e('Add Rule Group', 'wp-aigent'); ?></button>
            </div>
        </div>
    </div>

    <!-- Notifications -->
    <div class="ai-chatbot-tab-panel" data-tab="notify">
        <div class="ai-chatbot-field">
            <label>
                <input type="checkbox" name="chatbot_notify_enabled" value="1" <?php checked($meta['chatbot_notify_enabled'], '1'); ?> />
                <?php esc_html_e('Enable Notifications', 'wp-aigent'); ?>
            </label>
        </div>
        <div class="ai-chatbot-field">
            <label for="chatbot_notify_email"><?php esc_html_e('Notification Email', 'wp-aigent'); ?></label>
            <input type="email" id="chatbot_notify_email" name="chatbot_notify_email" value="<?php echo esc_attr($meta['chatbot_notify_email']); ?>" />
            <div class="description"><?php esc_html_e('Email address that receives lead notifications. Supports any WordPress mailer (SMTP, FluentSMTP, etc.).', 'wp-aigent'); ?></div>
        </div>
        <div class="ai-chatbot-field">
            <label for="chatbot_notify_webhook"><?php esc_html_e('Wecom Webhook', 'wp-aigent'); ?></label>
            <input type="url" id="chatbot_notify_webhook" name="chatbot_notify_webhook" value="<?php echo esc_attr($meta['chatbot_notify_webhook']); ?>" />
            <p class="description" style="margin-top:6px;">
                <?php esc_html_e('Push lead notifications to a WeCom (企业微信) group chat.', 'wp-aigent'); ?>
                <span style="color:#d63638;"><?php esc_html_e('Keep the Webhook URL private — anyone with it can send messages to the group.', 'wp-aigent'); ?></span>
            </p>
        </div>
        <div class="ai-chatbot-field">
            <label><?php esc_html_e('Notification Rules (OR between groups, AND within each group)', 'wp-aigent'); ?></label>
            <div class="description" style="margin-bottom:8px;"><?php esc_html_e('Define rule groups. Any matching group triggers the notification (send every time rules are met). Conditions within a group all must match.', 'wp-aigent'); ?></div>
            <input type="hidden" name="chatbot_notify_rules_sentinel" value="1" />
            <div id="js-notify-rules-fields">
                <?php
                $notify_groups = $meta['chatbot_notify_rules'] ?? [];
                if (is_string($notify_groups)) {
                    $notify_groups = AI_Chatbot_CPT_Chatbot::get_defaults()['chatbot_notify_rules'];
                }
                if (empty($notify_groups)) {
                    $notify_groups = AI_Chatbot_CPT_Chatbot::get_defaults()['chatbot_notify_rules'];
                }
                $ngidx = 0;
                foreach ($notify_groups as $group):
                    $group = (array) $group;
                ?>
                <div class="ai-chatbot-rule-group" data-group-index="<?php echo $ngidx; ?>">
                    <?php if ($ngidx > 0): ?>
                    <div class="ai-chatbot-rule-group-or"><?php esc_html_e('OR', 'wp-aigent'); ?></div>
                    <?php endif; ?>
                    <div class="ai-chatbot-rule-group-body">
                        <div class="ai-chatbot-rule-group-header">
                            <strong><?php printf(esc_html__('Rule Group %d', 'wp-aigent'), $ngidx + 1); ?></strong>
                        </div>
                        <div class="ai-chatbot-rule-group-conditions">
                            <?php $ncidx = 0; foreach ($group as $condition):
                                $condition = (array) $condition;
                            ?>
                            <div class="ai-chatbot-condition-row" data-cond-index="<?php echo $ncidx; ?>">
                                <div class="js-notify-fields-row">
                                    <div class="js-notify-field-path">
                                        <label><?php esc_html_e('Field', 'wp-aigent'); ?></label>
                                        <div style="display:flex;align-items:center;">
                                            <code style="margin-right:4px;flex-shrink:0;">lead.</code>
                                            <input type="text" name="chatbot_notify_rules[<?php echo $ngidx; ?>][<?php echo $ncidx; ?>][field]" value="<?php echo esc_attr(preg_replace('/^lead\./', '', $condition['field'] ?? '')); ?>" placeholder="e.g. lead_score" style="flex:1;min-width:0;" />
                                        </div>
                                    </div>
                                    <div class="js-notify-field-operator">
                                        <label><?php esc_html_e('Operator', 'wp-aigent'); ?></label>
                                        <select name="chatbot_notify_rules[<?php echo $ngidx; ?>][<?php echo $ncidx; ?>][operator]" style="width:100%;">
                                            <option value="eq" <?php selected($condition['operator'] ?? '', 'eq'); ?>><?php esc_html_e('equals (=)', 'wp-aigent'); ?></option>
                                            <option value="neq" <?php selected($condition['operator'] ?? '', 'neq'); ?>><?php esc_html_e('not equals (!=)', 'wp-aigent'); ?></option>
                                            <option value="in" <?php selected($condition['operator'] ?? '', 'in'); ?>><?php esc_html_e('in (comma-separated)', 'wp-aigent'); ?></option>
                                            <option value="contains" <?php selected($condition['operator'] ?? '', 'contains'); ?>><?php esc_html_e('contains', 'wp-aigent'); ?></option>
                                            <option value="gt" <?php selected($condition['operator'] ?? '', 'gt'); ?>><?php esc_html_e('greater than (>)', 'wp-aigent'); ?></option>
                                            <option value="lt" <?php selected($condition['operator'] ?? '', 'lt'); ?>><?php esc_html_e('less than (<)', 'wp-aigent'); ?></option>
                                            <option value="gte" <?php selected($condition['operator'] ?? '', 'gte'); ?>><?php esc_html_e('>=', 'wp-aigent'); ?></option>
                                            <option value="lte" <?php selected($condition['operator'] ?? '', 'lte'); ?>><?php esc_html_e('<=', 'wp-aigent'); ?></option>
                                            <option value="empty" <?php selected($condition['operator'] ?? '', 'empty'); ?>><?php esc_html_e('is empty', 'wp-aigent'); ?></option>
                                            <option value="not_empty" <?php selected($condition['operator'] ?? '', 'not_empty'); ?>><?php esc_html_e('is not empty', 'wp-aigent'); ?></option>
                                            <option value="changed" <?php selected($condition['operator'] ?? '', 'changed'); ?>><?php esc_html_e('changed (changes to any specified value)', 'wp-aigent'); ?></option>
                                        </select>
                                    </div>
                                    <div class="js-notify-field-value">
                                        <label><?php esc_html_e('Value', 'wp-aigent'); ?></label>
                                        <input type="text" name="chatbot_notify_rules[<?php echo $ngidx; ?>][<?php echo $ncidx; ?>][value]" value="<?php echo esc_attr(is_array($condition['value'] ?? '') ? implode(',', $condition['value']) : ($condition['value'] ?? '')); ?>" style="width:100%;" />
                                    </div>
                                    <div class="js-notify-field-actions">
                                        <label>&nbsp;</label>
                                        <button type="button" class="js-notify-remove-condition button button-small" title="<?php esc_attr_e('Remove condition', 'wp-aigent'); ?>">✕</button>
                                    </div>
                                </div>
                            </div>
                            <?php $ncidx++; endforeach; ?>
                        </div>
                        <div class="ai-chatbot-rule-group-actions">
                            <button type="button" class="js-notify-add-condition button button-small">+ <?php esc_html_e('Add Condition', 'wp-aigent'); ?></button>
                            <button type="button" class="js-notify-remove-group button button-small"><?php esc_html_e('Remove Group', 'wp-aigent'); ?></button>
                        </div>
                    </div>
                </div>
                <?php $ngidx++; endforeach; ?>
            </div>
            <template id="js-notify-group-tpl">
                <div class="ai-chatbot-rule-group" data-group-index="__NGIDX__">
                    <div class="ai-chatbot-rule-group-or"><?php esc_html_e('OR', 'wp-aigent'); ?></div>
                    <div class="ai-chatbot-rule-group-body">
                        <div class="ai-chatbot-rule-group-header">
                            <strong><?php esc_html_e('New Rule Group', 'wp-aigent'); ?></strong>
                        </div>
                        <div class="ai-chatbot-rule-group-conditions"></div>
                        <div class="ai-chatbot-rule-group-actions">
                            <button type="button" class="js-notify-add-condition button button-small">+ <?php esc_html_e('Add Condition', 'wp-aigent'); ?></button>
                            <button type="button" class="js-notify-remove-group button button-small"><?php esc_html_e('Remove Group', 'wp-aigent'); ?></button>
                        </div>
                    </div>
                </div>
            </template>
            <template id="js-notify-condition-tpl">
                <div class="ai-chatbot-condition-row" data-cond-index="__NCIDX__">
                    <div class="js-notify-fields-row">
                        <div class="js-notify-field-path">
                            <label><?php esc_html_e('Field', 'wp-aigent'); ?></label>
                            <div style="display:flex;align-items:center;">
                                <code style="margin-right:4px;flex-shrink:0;">lead.</code>
                                <input type="text" name="chatbot_notify_rules[__NGIDX__][__NCIDX__][field]" value="" placeholder="e.g. lead_score" style="flex:1;min-width:0;" />
                            </div>
                        </div>
                        <div class="js-notify-field-operator">
                            <label><?php esc_html_e('Operator', 'wp-aigent'); ?></label>
                            <select name="chatbot_notify_rules[__NGIDX__][__NCIDX__][operator]" style="width:100%;">
                                <option value="eq"><?php esc_html_e('equals (=)', 'wp-aigent'); ?></option>
                                <option value="neq"><?php esc_html_e('not equals (!=)', 'wp-aigent'); ?></option>
                                <option value="in"><?php esc_html_e('in (comma-separated)', 'wp-aigent'); ?></option>
                                <option value="contains"><?php esc_html_e('contains', 'wp-aigent'); ?></option>
                                <option value="gt"><?php esc_html_e('greater than (>)', 'wp-aigent'); ?></option>
                                <option value="lt"><?php esc_html_e('less than (<)', 'wp-aigent'); ?></option>
                                <option value="gte"><?php esc_html_e('>=', 'wp-aigent'); ?></option>
                                <option value="lte"><?php esc_html_e('<=', 'wp-aigent'); ?></option>
                                <option value="empty"><?php esc_html_e('is empty', 'wp-aigent'); ?></option>
                                <option value="not_empty"><?php esc_html_e('is not empty', 'wp-aigent'); ?></option>
                                <option value="changed"><?php esc_html_e('changed (changes to any specified value)', 'wp-aigent'); ?></option>
                            </select>
                        </div>
                        <div class="js-notify-field-value">
                            <label><?php esc_html_e('Value', 'wp-aigent'); ?></label>
                            <input type="text" name="chatbot_notify_rules[__NGIDX__][__NCIDX__][value]" value="" style="width:100%;" />
                        </div>
                        </div>
                        <div class="js-notify-field-actions">
                            <label>&nbsp;</label>
                            <button type="button" class="js-notify-remove-condition button button-small" title="<?php esc_attr_e('Remove condition', 'wp-aigent'); ?>">✕</button>
                        </div>
                    </div>
                </div>
            </template>
            <div style="margin-top:8px;">
                <button type="button" class="js-notify-add-group button">+ <?php esc_html_e('Add Rule Group', 'wp-aigent'); ?></button>
            </div>
        </div>
        <div class="ai-chatbot-field">
            <label>
                <input type="checkbox" name="chatbot_notify_inactivity_enabled" value="1" <?php checked($meta['chatbot_notify_inactivity_enabled'], '1'); ?> id="chatbot_notify_inactivity_enabled" />
                <?php esc_html_e('Enable inactivity timeout — only evaluate Notification Rules when conversation has had no new messages for a period', 'wp-aigent'); ?>
            </label>
        </div>
        <div id="js-notify-inactivity-settings"
             style="margin-top:8px;padding:12px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;font-size:13px;line-height:1.6;opacity:<?php echo $meta['chatbot_notify_inactivity_enabled'] === '1' ? '1' : '0.5'; ?>;<?php echo $meta['chatbot_notify_inactivity_enabled'] === '1' ? '' : 'pointer-events:none;'; ?>">
            <label for="chatbot_notify_inactivity_timeout" style="display:block;font-weight:600;margin-bottom:4px;">
                <?php esc_html_e('Inactivity threshold', 'wp-aigent'); ?>
            </label>
            <div style="display:flex;align-items:center;gap:6px;">
                <input type="number" id="chatbot_notify_inactivity_timeout"
                       name="chatbot_notify_inactivity_timeout"
                       value="<?php echo esc_attr($meta['chatbot_notify_inactivity_timeout'] ?? '1'); ?>"
                       min="1" max="720" step="1" style="width:80px;" />
                <span><?php esc_html_e('hours', 'wp-aigent'); ?></span>
            </div>
            <p style="margin:6px 0 0 0;font-size:12px;color:#666;line-height:1.5;">
                <?php esc_html_e('When enabled, all Notification Rule evaluation is deferred until the conversation has been idle for this period. If rules then match, a notification is sent. Each inactivity cycle is independent — if the visitor returns and leaves again, another notification may be sent after the same timeout.', 'wp-aigent'); ?>
            </p>
            <p style="margin:3px 0 0 0;font-size:11px;color:#888;line-height:1.5;">
                <em><?php esc_html_e('Requires WordPress Cron (triggered by site traffic). Scheduled checks may be delayed on low-traffic sites.', 'wp-aigent'); ?></em>
            </p>
        </div>
    </div>
