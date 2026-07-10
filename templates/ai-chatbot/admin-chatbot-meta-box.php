<?php
defined('ABSPATH') || exit;
/**
 * @var WP_Post $post
 */

$meta = AI_Chatbot_CPT_Chatbot::get_meta($post->ID);
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
        <div class="ai-chatbot-field">
            <label for="chatbot_platform"><?php esc_html_e('Platform', 'wp-aigent'); ?></label>
            <select id="chatbot_platform" name="chatbot_platform">
                <option value="openai" <?php selected($meta['chatbot_platform'], 'openai'); ?>>OpenAI</option>
                <option value="anthropic" <?php selected($meta['chatbot_platform'], 'anthropic'); ?>>Anthropic</option>
            </select>
            <div class="description"><?php esc_html_e('OpenAI-compatible and Anthropic-compatible APIs cover most providers (OpenRouter, DeepSeek, Azure, etc.). Select "OpenAI" for any API that uses the ChatGPT message format; set the API Base URL and Model below accordingly.', 'wp-aigent'); ?></div>
        </div>
        <div class="ai-chatbot-field-row">
            <div class="ai-chatbot-field">
                <label for="chatbot_api_base_url"><?php esc_html_e('API Base URL', 'wp-aigent'); ?></label>
                <input type="url" id="chatbot_api_base_url" name="chatbot_api_base_url" value="<?php echo esc_attr($meta['chatbot_api_base_url']); ?>" />
                <div class="description"><?php esc_html_e('e.g., https://api.openai.com/v1', 'wp-aigent'); ?></div>
            </div>
            <div class="ai-chatbot-field">
                <label for="chatbot_api_key"><?php esc_html_e('API Key', 'wp-aigent'); ?></label>
                <input type="text" id="chatbot_api_key" name="chatbot_api_key" value="" placeholder="<?php esc_attr_e('Leave blank to keep current key', 'wp-aigent'); ?>" />
                <div class="description"><?php esc_html_e('Leave blank to keep current key. New value will be encrypted.', 'wp-aigent'); ?></div>
            </div>
        </div>
        <div class="ai-chatbot-field-row">
            <div class="ai-chatbot-field">
                <label for="chatbot_model"><?php esc_html_e('Primary Model', 'wp-aigent'); ?></label>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                    <select id="chatbot_model" style="flex:1;min-width:120px;">
                        <option value=""><?php esc_html_e('— Select model —', 'wp-aigent'); ?></option>
                        <option value="__custom__"><?php esc_html_e('Custom...', 'wp-aigent'); ?></option>
                    </select>
                </div>
                <input type="hidden" id="chatbot_model_hidden" name="chatbot_model" value="<?php echo esc_attr($meta['chatbot_model']); ?>" />
                <div id="chatbot-model-custom-wrap" style="margin-top:6px;display:none;">
                    <input type="text" id="chatbot_model_custom" value="" placeholder="<?php esc_attr_e('Enter custom model name...', 'wp-aigent'); ?>" style="width:100%;" />
                </div>
                <div class="description"><?php esc_html_e('Available models are fetched automatically from the API. If fetching fails, use Custom... to enter the model name manually.', 'wp-aigent'); ?></div>
            </div>
            <div class="ai-chatbot-field">
                <label for="chatbot_fallback_model"><?php esc_html_e('Fallback Model', 'wp-aigent'); ?></label>
                <select id="chatbot_fallback_model" style="width:100%;">
                    <option value=""><?php esc_html_e('— None (disabled) —', 'wp-aigent'); ?></option>
                    <option value="__custom__"><?php esc_html_e('Custom...', 'wp-aigent'); ?></option>
                </select>
                <input type="hidden" id="chatbot_fallback_model_hidden" name="chatbot_fallback_model" value="<?php echo esc_attr($meta['chatbot_fallback_model']); ?>" />
                <div id="chatbot-fallback-model-custom-wrap" style="margin-top:6px;display:none;">
                    <input type="text" id="chatbot_fallback_model_custom" value="" placeholder="<?php esc_attr_e('Enter custom model name...', 'wp-aigent'); ?>" style="width:100%;" />
                </div>
                <div class="description"><?php esc_html_e('Leave empty to disable fallback. When the primary model fails, the fallback is tried automatically.', 'wp-aigent'); ?></div>
            </div>
        </div>
        <div class="ai-chatbot-field-row" style="margin-top:12px;">
            <div class="ai-chatbot-field">
                <label for="chatbot_input_tokens"><?php esc_html_e('Input Tokens', 'wp-aigent'); ?></label>
                <input type="number" id="chatbot_input_tokens" name="chatbot_input_tokens" value="<?php echo esc_attr($meta['chatbot_input_tokens']); ?>" min="1" max="1000000" />
                <div class="description"><?php esc_html_e('Maximum input (context) window for reference. Not sent to the API.', 'wp-aigent'); ?></div>
            </div>
            <div class="ai-chatbot-field">
                <label for="chatbot_max_tokens"><?php esc_html_e('Output Tokens', 'wp-aigent'); ?></label>
                <input type="number" id="chatbot_max_tokens" name="chatbot_max_tokens" value="<?php echo esc_attr($meta['chatbot_max_tokens']); ?>" min="1" max="128000" />
                <div class="description"><?php esc_html_e('Maximum output tokens for the response. 1 token ≈ 0.75 words.', 'wp-aigent'); ?></div>
            </div>
        </div>

        <h4 style="margin:16px 0 8px;">Optional Parameters</h4>

        <!-- Temperature -->
        <div class="ai-chatbot-optional-row" style="margin-bottom:10px;">
            <div style="display:flex;align-items:center;flex-wrap:wrap;gap:6px;">
                <label style="display:flex;align-items:center;gap:4px;cursor:pointer;white-space:nowrap;">
                    <input type="checkbox" name="chatbot_temperature_enabled" value="1" <?php checked($meta['chatbot_temperature_enabled'], '1'); ?> />
                    <strong>Temperature</strong>
                </label>
                <div class="ai-chatbot-optional-body" style="display:<?php echo $meta['chatbot_temperature_enabled'] === '1' ? 'inline-flex' : 'none'; ?>;align-items:center;gap:6px;">
                    <input type="range" id="chatbot_temperature" name="chatbot_temperature" value="<?php echo esc_attr($meta['chatbot_temperature']); ?>" step="0.1" min="0" max="2" style="width:120px;vertical-align:middle;" />
                    <span id="ai-chatbot-temp-val" style="font-size:13px;min-width:18px;"><?php echo esc_html($meta['chatbot_temperature']); ?></span>
                </div>
            </div>
            <div class="description" style="margin-top:2px;">Controls the randomness of the output. Lower values are more deterministic, higher values more creative. If the request fails, try disabling this.</div>
        </div>

        <!-- Extended Thinking / Reasoning -->
        <div class="ai-chatbot-optional-row" style="margin-bottom:10px;">
            <div style="display:flex;align-items:center;flex-wrap:wrap;gap:6px;">
                <label style="display:flex;align-items:center;gap:4px;cursor:pointer;white-space:nowrap;">
                    <input type="checkbox" name="chatbot_thinking_enabled" value="1" <?php checked($meta['chatbot_thinking_enabled'], '1'); ?> />
                    <strong>Extended Thinking / Reasoning</strong>
                </label>
                <div class="ai-chatbot-optional-body" style="display:<?php echo $meta['chatbot_thinking_enabled'] === '1' ? 'inline-flex' : 'none'; ?>;align-items:center;gap:6px;">
                    <?php
                    $effort_labels = ['low', 'medium', 'high', 'xhigh', 'max'];
                    $effort_index = array_search($meta['chatbot_reasoning_effort'], $effort_labels, true);
                    $effort_index = $effort_index !== false ? $effort_index : 1;
                    ?>
                    <input type="range" id="chatbot_reasoning_effort_slider" value="<?php echo esc_attr($effort_index); ?>" step="1" min="0" max="4" style="width:120px;vertical-align:middle;" />
                    <input type="hidden" id="chatbot_reasoning_effort" name="chatbot_reasoning_effort" value="<?php echo esc_attr($meta['chatbot_reasoning_effort']); ?>" />
                    <span id="ai-chatbot-effort-val" style="font-size:13px;min-width:60px;"><?php echo esc_html(ucfirst($meta['chatbot_reasoning_effort'])); ?></span>
                </div>
            </div>
            <div class="description" style="margin-top:2px;">Enables extended thinking and reasoning (Anthropic: sends <code>thinking</code> + <code>output_config.effort</code>; OpenAI-compatible: sends <code>reasoning_effort</code>). Not supported by standard models (gpt-4o etc.). If the request fails, try disabling this.</div>
        </div>
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
                    echo '<span>' . esc_html($doc->post_title) . '</span>';
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
