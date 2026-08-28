<?php
defined('ABSPATH') || exit;
/**
 * Admin meta box for conversation details.
 * Variables set by AI_Chatbot_CPT_Conversation::render_meta_box().
 *
 * @var WP_Post $post
 * @var string  $visitor_id
 * @var int     $chatbot_id
 * @var array   $messages
 * @var int     $msg_count
 * @var string  $started_at
 * @var mixed   $lead_data
 * @var array   $attribution_view
 * @var string  $ip
 * @var string  $ua
 * @var string  $page_url
 * @var string  $bot_name
 */
?>
<div class="ai-conv-wrap">
    <!-- Overview (includes visitor info and summary) -->
    <div class="ai-conv-section">
        <h3><?php esc_html_e('Overview', 'wp-aigent'); ?></h3>
        <table class="widefat striped">
            <tr><th><?php esc_html_e('Conversation ID', 'wp-aigent'); ?></th><td>#<?php echo $post->ID; ?></td></tr>
            <tr><th><?php esc_html_e('Chatbot', 'wp-aigent'); ?></th><td><?php echo esc_html($bot_name); ?> (#<?php echo $chatbot_id; ?>)</td></tr>
            <tr><th><?php esc_html_e('Visitor ID', 'wp-aigent'); ?></th><td><code><?php echo esc_html($visitor_id); ?></code></td></tr>
            <tr><th><?php esc_html_e('Messages', 'wp-aigent'); ?></th><td><?php echo $msg_count; ?></td></tr>
            <?php
            $notify_log = get_post_meta($post->ID, 'conversation_notification_log', true);
            $notify_count = is_array($notify_log) ? count($notify_log) : 0;
            ?>
            <tr><th><?php esc_html_e('Notifications', 'wp-aigent'); ?></th><td><?php echo $notify_count; ?> <?php esc_html_e('time(s)', 'wp-aigent'); ?></td></tr>
            <tr><th><?php esc_html_e('Started', 'wp-aigent'); ?></th><td><?php echo esc_html($started_at); ?></td></tr>
            <?php
            $last_activity = get_post_meta($post->ID, 'conversation_last_activity', true);
            if (!empty($last_activity)):
            ?>
            <tr>
                <th><?php esc_html_e('Last Activity', 'wp-aigent'); ?></th>
                <td><?php echo esc_html(
                    is_numeric($last_activity)
                        ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $last_activity)
                        : $last_activity
                ); ?></td>
            </tr>
            <?php endif; ?>
            <?php
            $conv_summary = get_post_meta($post->ID, 'conversation_summary', true);
            if (!empty($conv_summary)):
            ?><tr><th><?php esc_html_e('Summary', 'wp-aigent'); ?></th><td><?php echo esc_html($conv_summary); ?></td></tr><?php
            endif;
            ?>
            <?php if ($ip): ?><tr><th><?php esc_html_e('IP', 'wp-aigent'); ?></th><td><?php echo esc_html($ip); ?></td></tr><?php endif; ?>
            <?php if ($ua): ?><tr><th><?php esc_html_e('User Agent', 'wp-aigent'); ?></th><td style="font-size:12px;word-break:break-all;"><?php echo esc_html($ua); ?></td></tr><?php endif; ?>
            <?php if ($page_url): ?><tr><th><?php esc_html_e('Page URL', 'wp-aigent'); ?></th><td><a href="<?php echo esc_url($page_url); ?>" target="_blank"><?php echo esc_html($page_url); ?></a></td></tr><?php endif; ?>
        </table>
    </div>

    <!-- Messages -->
    <div class="ai-conv-section">
        <h3><?php esc_html_e('Messages', 'wp-aigent'); ?></h3>
        <?php
        if (empty($messages)) {
            echo '<p style="color:#999;">' . esc_html__('No messages recorded.', 'wp-aigent') . '</p>';
        } else {
            echo '<div class="ai-conv-messages-scroll" style="max-height:420px;overflow-y:auto;">';
            echo '<table class="ai-conv-msg-table widefat">';
            $format_token_usage = static function (array $usage): array {
                $parts = [];
                if (isset($usage['total_tokens'])) $parts[] = 'Total:' . AI_Chatbot_CPT_Conversation::format_token_number((int) $usage['total_tokens']);
                if (isset($usage['prompt_tokens'])) $parts[] = 'Input:' . AI_Chatbot_CPT_Conversation::format_token_number((int) $usage['prompt_tokens']);
                if (isset($usage['completion_tokens'])) $parts[] = 'Output:' . AI_Chatbot_CPT_Conversation::format_token_number((int) $usage['completion_tokens']);
                if (!empty($usage['cached_tokens'])) $parts[] = 'Cache:' . AI_Chatbot_CPT_Conversation::format_token_number((int) $usage['cached_tokens']);
                return $parts;
            };
            $format_knowledge_trace = static function (array $trace) use ($format_token_usage): string {
                if (!$trace) return '';
                $parts = [sprintf(
                    __('Knowledge: %1$s · %2$s', 'wp-aigent'),
                    $trace['mode'] ?? '—',
                    $trace['status'] ?? '—'
                )];
                $titles_by_id = [];
                foreach ((array) ($trace['sources'] ?? []) as $source) {
                    $document_id = absint($source['document_id'] ?? 0);
                    if (!$document_id && preg_match('/^K(\d+)/', (string) ($source['id'] ?? ''), $matches)) {
                        $document_id = absint($matches[1]);
                    }
                    $title = sanitize_text_field($source['title'] ?? '');
                    if ($document_id && $title !== '') $titles_by_id[$document_id] = $title;
                }
                $document_labels = [];
                foreach (array_values(array_filter(array_map('absint', (array) ($trace['document_ids'] ?? [])))) as $document_id) {
                    $document_labels[] = '#' . $document_id . (isset($titles_by_id[$document_id]) ? ' (' . $titles_by_id[$document_id] . ')' : '');
                }
                if ($document_labels) $parts[] = __('Documents:', 'wp-aigent') . ' ' . implode(', ', array_unique($document_labels));
                if (!empty($trace['router'])) {
                    $router = $trace['router'];
                    $router_usage = $format_token_usage((array) ($router['token_usage'] ?? []));
                    if (!empty($router['model']) || !empty($router['duration_ms']) || $router_usage) {
                        $router_info = sprintf(
                            __('Model:%1$s · Duration:%2$d ms', 'wp-aigent'),
                            $router['model'] ?? '—',
                            (int) ($router['duration_ms'] ?? 0)
                        );
                        if ($router_usage) $router_info .= ' · ' . implode(' · ', $router_usage);
                        $parts[] = $router_info;
                    }
                }
                return implode(' | ', $parts);
            };
            foreach ($messages as $msg) {
                $role = $msg['role'];
                $css_class = 'ai-conv-msg-' . $role;
                $label = ($role === 'user') ? __('User', 'wp-aigent') : __('Assistant', 'wp-aigent');

                // Apply error class to row before rendering
                if ($role === 'assistant' && !empty($msg['error'])) {
                    $css_class .= ' ai-conv-msg-error';
                }

                echo '<tr class="' . $css_class . '">';
                echo '<td class="ai-conv-msg-label">' . esc_html($label) . '</td>';

                if ($role === 'assistant' && !empty($msg['error'])) {
                    // Error exchange — show error text in red
                    echo '<td class="ai-conv-msg-content">';
                    echo '<span class="ai-conv-msg-error-text">' . esc_html($msg['error']) . '</span>';
                    if (!empty($msg['model'])) {
                        echo '<div class="ai-conv-msg-info">Model: ' . esc_html($msg['model']) . '</div>';
                    }
                    if (!empty($msg['duration_ms'])) {
                        echo '<div class="ai-conv-msg-info">' . esc_html__('Duration:', 'wp-aigent') . ' ' . (int) $msg['duration_ms'] . ' ms</div>';
                    }
                    $knowledge_info = $format_knowledge_trace((array) ($msg['knowledge_trace'] ?? []));
                    if ($knowledge_info !== '') echo '<div class="ai-conv-msg-knowledge-info">' . esc_html($knowledge_info) . '</div>';
                    echo '</td>';
                } else {
                    // Normal message
                    $content = $msg['content'] ?? '';
                    echo '<td class="ai-conv-msg-content">';
                    if ($role === 'assistant') {
                        echo '<span class="ai-conv-msg-success-text">' . esc_html($content) . '</span>';
                        $info_parts = [];
                        // Token usage
                        $info_parts = $format_token_usage((array) ($msg['token_usage'] ?? []));
                        // Model name
                        if (!empty($msg['model'])) {
                            $info_parts[] = 'Model:' . $msg['model'];
                        }
                        // Effort level
                        if (!empty($msg['effort'])) {
                            $info_parts[] = 'Effort:' . ucfirst($msg['effort']);
                        }
                        // Time with timezone
                        if (!empty($msg['time'])) {
                            $info_parts[] = 'Time:' . $msg['time'] . ' ' . wp_timezone_string();
                        }
                        if (!empty($msg['duration_ms'])) {
                            $info_parts[] = 'Duration:' . (int) $msg['duration_ms'] . ' ms';
                        }
                        if (!empty($info_parts)) {
                            echo '<div class="ai-conv-msg-info">' . esc_html(implode(' | ', $info_parts)) . '</div>';
                        }
                        $knowledge_info = $format_knowledge_trace((array) ($msg['knowledge_trace'] ?? []));
                        if ($knowledge_info !== '') echo '<div class="ai-conv-msg-knowledge-info">' . esc_html($knowledge_info) . '</div>';
                    } else {
                        echo esc_html($content);
                    }
                    echo '</td>';
                }
                echo '</tr>';
            }
            echo '</table>';
            echo '</div>';
        }
        ?>
    </div>

    <!-- Lead Data -->
    <?php if (!empty($lead_data)): ?>
    <div class="ai-conv-section">
        <h3><?php esc_html_e('Lead Data', 'wp-aigent'); ?></h3>
        <table class="widefat striped">
            <?php foreach ($lead_data as $key => $value): ?>
            <tr>
                <th style="width:160px;"><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></th>
                <td><?php echo is_array($value) ? esc_html(wp_json_encode($value)) : esc_html($value); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <!-- Attribution -->
    <?php if (!empty($attribution_view['rows'])): ?>
    <div class="ai-conv-section">
        <h3><?php esc_html_e('Attribution', 'wp-aigent'); ?></h3>
        <table class="widefat striped">
            <thead><tr>
                <th><?php esc_html_e('Time', 'wp-aigent'); ?></th>
                <th><?php esc_html_e('Path', 'wp-aigent'); ?></th>
                <?php if ($attribution_view['show_source']): ?><th><?php esc_html_e('Source', 'wp-aigent'); ?></th><?php endif; ?>
                <?php if ($attribution_view['show_referrer']): ?><th><?php esc_html_e('Referrer', 'wp-aigent'); ?></th><?php endif; ?>
                <?php if ($attribution_view['show_event']): ?><th><?php esc_html_e('Event', 'wp-aigent'); ?></th><?php endif; ?>
                <?php if ($attribution_view['show_event_id']): ?><th><?php esc_html_e('Event ID', 'wp-aigent'); ?></th><?php endif; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($attribution_view['rows'] as $attribution_row): ?>
                <tr>
                    <td><?php echo esc_html($attribution_row['time']); ?></td>
                    <td style="word-break:break-word;"><code><?php echo esc_html($attribution_row['path']); ?></code></td>
                    <?php if ($attribution_view['show_source']): ?><td><?php echo esc_html($attribution_row['source']); ?></td><?php endif; ?>
                    <?php if ($attribution_view['show_referrer']): ?><td style="word-break:break-word;"><?php echo esc_html($attribution_row['referrer_url']); ?></td><?php endif; ?>
                    <?php if ($attribution_view['show_event']): ?><td><?php echo esc_html($attribution_row['event']); ?></td><?php endif; ?>
                    <?php if ($attribution_view['show_event_id']): ?><td><code><?php echo esc_html($attribution_row['event_id']); ?></code></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Notification History -->
    <div class="ai-conv-section">
        <h3><?php esc_html_e('Notification History', 'wp-aigent'); ?></h3>
        <?php
        $notify_log = get_post_meta($post->ID, 'conversation_notification_log', true);
        if (!is_array($notify_log)) {
            $notify_log = [];
        }

        // Check for pending WP Cron (inactivity timeout not yet evaluated)
        $next_cron = wp_next_scheduled('ai_chatbot_inactivity_notify', [$post->ID]);
        $has_pending = false;
        if ($next_cron && $next_cron > time()) {
            $has_pending = true;
            // Add a virtual pending entry for display
            array_unshift($notify_log, [
                'time'     => $next_cron,
                'reason'   => 'inactivity',
                'channels' => [],
                'status'   => 'pending',
                'rule'     => 'Awaiting rules evaluation',
            ]);
        }

        if (!empty($notify_log)):
            echo '<table class="ai-conv-msg-table widefat" style="margin-bottom:10px;">';
            echo '<thead><tr style="background:#f0f0f1;">';
            echo '<th style="padding:8px 12px;text-align:left;font-size:13px;">' . esc_html__('Time', 'wp-aigent') . '</th>';
            echo '<th style="padding:8px 12px;text-align:left;font-size:13px;">' . esc_html__('Reason', 'wp-aigent') . '</th>';
            echo '<th style="padding:8px 12px;text-align:left;font-size:13px;">' . esc_html__('Trigger Rule', 'wp-aigent') . '</th>';
            echo '<th style="padding:8px 12px;text-align:left;font-size:13px;">' . esc_html__('Channels', 'wp-aigent') . '</th>';
            echo '<th style="padding:8px 12px;text-align:left;font-size:13px;">' . esc_html__('Status', 'wp-aigent') . '</th>';
            echo '</tr></thead><tbody>';
            foreach ($notify_log as $entry):
                $time     = $entry['time'] ?? '—';
                $reason   = $entry['reason'] ?? '—';
                $channels = !empty($entry['channels']) ? implode(', ', $entry['channels']) : '—';
                $status   = $entry['status'] ?? '—';
                $rule     = $entry['rule'] ?? '';

                // Row class based on status (matching Messages section color scheme)
                $row_class = 'ai-notify-failed';
                if ($status === 'success') {
                    $row_class = 'ai-notify-success';
                } elseif ($status === 'pending') {
                    $row_class = 'ai-notify-pending';
                }

                // Format time for pending entries (show countdown)
                $time_display = esc_html(
                    is_numeric($time)
                        ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $time)
                        : $time
                );
                if ($status === 'pending' && is_numeric($entry['time'] ?? null)) {
                    $remaining = $entry['time'] - time();
                    if ($remaining > 0) {
                        $hours   = floor($remaining / 3600);
                        $minutes = floor(($remaining % 3600) / 60);
                        $time_display .= '<span class="ai-notify-pending-countdown">' . sprintf(esc_html__('~%dh %dm remaining', 'wp-aigent'), $hours, $minutes) . '</span>';
                    }
                }

                // Status display
                if ($status === 'success') {
                    $status_display = '<span style="color:#46b450;">✓ ' . esc_html__('Sent', 'wp-aigent') . '</span>';
                } elseif ($status === 'pending') {
                    $status_display = '<span style="color:#999;">⏳ ' . esc_html__('Pending', 'wp-aigent') . '</span>';
                } else {
                    $status_display = '<span style="color:#dc3232;">✗ ' . esc_html__('Failed', 'wp-aigent') . '</span>';
                }

                echo '<tr class="' . $row_class . '">';
                echo '<td style="padding:8px 12px;">' . $time_display . '</td>';
                echo '<td style="padding:8px 12px;">' . esc_html(ucfirst($reason)) . '</td>';
                echo '<td style="padding:8px 12px;font-size:12px;color:#555;">' . esc_html($rule) . '</td>';
                echo '<td style="padding:8px 12px;">' . esc_html($channels) . '</td>';
                echo '<td style="padding:8px 12px;">' . $status_display . '</td>';
                echo '</tr>';
            endforeach;
            echo '</tbody></table>';
        else:
            echo '<p style="color:#999;">' . esc_html__('No notifications sent yet.', 'wp-aigent') . '</p>';
        endif;
        ?>
        <button type="button" class="button button-primary" id="ai-trigger-notify" data-post-id="<?php echo (int) $post->ID; ?>">
            <?php esc_html_e('Send Notification Now', 'wp-aigent'); ?>
        </button>
        <span id="ai-notify-result" style="margin-left:10px;"></span>
    </div>

</div>
<script>
(function() {
    var el = document.querySelector('.ai-conv-messages-scroll');
    if (el) el.scrollTop = el.scrollHeight;

    var btn = document.getElementById('ai-trigger-notify');
    var result = document.getElementById('ai-notify-result');
    if (btn) {
        btn.addEventListener('click', function() {
            if (btn.disabled) return;
            btn.disabled = true;
            btn.textContent = '<?php echo esc_js(__('Sending...', 'wp-aigent')); ?>';
            result.textContent = '';
            result.style.color = '';

            var data = new FormData();
            data.append('action', 'ai_chatbot_trigger_notify');
            data.append('nonce', '<?php echo wp_create_nonce('ai_chatbot_trigger_notify'); ?>');
            data.append('post_id', btn.getAttribute('data-post-id'));

            fetch(ajaxurl, { method: 'POST', body: data })
                .then(function(r) { return r.json(); })
                .then(function(json) {
                    if (json.success) {
                        location.reload();
                    } else {
                        result.textContent = json.data && json.data.message
                            ? json.data.message
                            : '<?php echo esc_js(__('Failed', 'wp-aigent')); ?>';
                        result.style.color = '#dc3232';
                    }
                })
                .catch(function() {
                    result.textContent = '<?php echo esc_js(__('Request failed', 'wp-aigent')); ?>';
                    result.style.color = '#dc3232';
                })
                .then(function() {
                    btn.disabled = false;
                    btn.textContent = '<?php echo esc_js(__('Send Notification Now', 'wp-aigent')); ?>';
                });
        });
    }
})();
</script>
