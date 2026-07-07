<?php
defined('ABSPATH') || exit;
/**
 * 企业微信 Markdown 通知模板
 *
 * @var array $data             Lead 字段 + 'visitor' / 'conversation' 子数组
 * @var int   $conversation_id  会话 ID，用于生成后台链接
 */
$score  = $data['lead_score'] ?? 'N/A';
$conv   = $data['conversation'] ?? [];
$visitor = $data['visitor'] ?? [];
?>
# 新 AI 线索通知

**线索评分:** **<?= esc_html($score) ?>**
---

## 会话信息

| 字段 | 内容 |
| :--- | :--- |
| Chatbot | <?= esc_html(($conv['chatbot_name'] ?? '—') . ' (#' . ($conv['chatbot_id'] ?? 0) . ')') ?> |
| Session | <?= esc_html($conv['session_id'] ?? '—') ?> |
| 消息数 | <?= (int) ($conv['message_count'] ?? 0) ?> |
| 时间 | <?= esc_html($conv['started_at'] ?? '—') ?> |
<?php if (!empty($conv['summary'])): ?>
| 摘要 | <?= esc_html($conv['summary']) ?> |
<?php endif; ?>

---

## 线索数据

<?php
$rows = [];
foreach ($data as $key => $value) {
    if (in_array($key, ['visitor', 'conversation'], true) || is_array($value)) {
        continue;
    }
    $label = ucwords(str_replace(['_', '-'], ' ', $key));
    $rows[] = '| ' . $label . ' | ' . (is_string($value) ? esc_html($value) : '') . ' |';
}

if (!empty($rows)):
?>
| 字段 | 内容 |
| :--- | :--- |
<?= implode("\n", $rows) ?>

---
<?php endif; ?>

## 访问者信息

| 字段 | 内容 |
| :--- | :--- |
| IP | <?= esc_html($visitor['ip'] ?? 'N/A') ?> |
| 页面 | <?= esc_html($visitor['page_url'] ?? 'N/A') ?> |
| UA | <?= esc_html($visitor['ua'] ?? 'N/A') ?> |

---

<?php if ($conversation_id > 0):
    $url = admin_url('post.php?post=' . $conversation_id . '&action=edit');
?>

> [查看对话历史](<?= esc_url($url) ?>)

<?php endif; ?>

> *此通知由 AI Chatbot 插件自动发送*
