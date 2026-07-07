<?php
defined('ABSPATH') || exit;
/**
 * @var int    $chatbot_id
 * @var array  $config
 * @var string $widget_id
 * @var string $session_id
 * @var string $session_token
 */

$container_id = 'ai-chatbot-container-' . $widget_id;
$layout = $config['chatbot_layout_mode'] ?? 'inline';
?>

<div id="<?php echo esc_attr($container_id); ?>"
     class="ai-chatbot-container"
     data-widget-id="<?php echo esc_attr($widget_id); ?>"
     data-layout="<?php echo esc_attr($layout); ?>">
</div>
