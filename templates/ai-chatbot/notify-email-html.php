<?php
defined('ABSPATH') || exit;
/**
 * Email HTML 通知模板
 *
 * @var array $data             Lead 字段 + 'visitor' / 'conversation' 子数组
 * @var int   $conversation_id  会话 ID，用于生成后台链接
 */
$score   = $data['lead_score'] ?? 'N/A';
$conv    = $data['conversation'] ?? [];
$visitor = $data['visitor'] ?? [];

$border = '1px solid #ddd';
$th_bg  = '#f2f2f2';
?>
<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;color:#333;">
    <h2 style="margin:0 0 4px;">New Lead Notification</h2>
    <p style="color:#666;font-size:13px;margin:0 0 16px;">AI Chatbot Plugin</p>

    <p><strong>Lead Score:</strong> <?= esc_html($score) ?></p>

    <!-- Overview -->
    <table style="width:100%;border-collapse:collapse;margin:12px 0;">
        <thead>
            <tr>
                <th colspan="2" style="padding:8px 10px;border:<?= $border ?>;background:<?= $th_bg ?>;text-align:left;">Conversation</th>
            </tr>
        </thead>
        <tbody>
            <tr><td style="padding:6px 10px;border:<?= $border ?>;background:#f9f9f9;width:120px;">Chatbot</td>
                <td style="padding:6px 10px;border:<?= $border ?>;"><?= esc_html(($conv['chatbot_name'] ?? '—') . ' (#' . ($conv['chatbot_id'] ?? 0) . ')') ?></td></tr>
            <tr><td style="padding:6px 10px;border:<?= $border ?>;background:#f9f9f9;">Session</td>
                <td style="padding:6px 10px;border:<?= $border ?>;"><?= esc_html($conv['session_id'] ?? '—') ?></td></tr>
            <tr><td style="padding:6px 10px;border:<?= $border ?>;background:#f9f9f9;">Messages</td>
                <td style="padding:6px 10px;border:<?= $border ?>;"><?= (int) ($conv['message_count'] ?? 0) ?></td></tr>
            <tr><td style="padding:6px 10px;border:<?= $border ?>;background:#f9f9f9;">Started</td>
                <td style="padding:6px 10px;border:<?= $border ?>;"><?= esc_html($conv['started_at'] ?? '—') ?></td></tr>
<?php if (!empty($conv['summary'])): ?>
            <tr><td style="padding:6px 10px;border:<?= $border ?>;background:#f9f9f9;">Summary</td>
                <td style="padding:6px 10px;border:<?= $border ?>;"><?= esc_html($conv['summary']) ?></td></tr>
<?php endif; ?>
        </tbody>
    </table>

    <!-- Lead Data -->
<?php
$rows = '';
foreach ($data as $key => $value) {
    if (in_array($key, ['visitor', 'conversation'], true) || is_array($value)) {
        continue;
    }
    $label = ucwords(str_replace(['_', '-'], ' ', $key));
    $val   = is_string($value) ? esc_html($value) : '';
    $rows .= '<tr><td style="padding:6px 10px;border:' . $border . ';background:#f9f9f9;width:120px;">' . esc_html($label) . '</td>'
           . '<td style="padding:6px 10px;border:' . $border . ';">' . $val . '</td></tr>';
}

if (!empty($rows)):
?>
    <table style="width:100%;border-collapse:collapse;margin:12px 0;">
        <thead>
            <tr>
                <th colspan="2" style="padding:8px 10px;border:<?= $border ?>;background:<?= $th_bg ?>;text-align:left;">Lead Data</th>
            </tr>
        </thead>
        <tbody><?= $rows ?></tbody>
    </table>
<?php endif; ?>

    <!-- Visitor -->
    <table style="width:100%;border-collapse:collapse;margin:12px 0;">
        <thead>
            <tr>
                <th colspan="2" style="padding:8px 10px;border:<?= $border ?>;background:<?= $th_bg ?>;text-align:left;">Visitor</th>
            </tr>
        </thead>
        <tbody>
            <tr><td style="padding:6px 10px;border:<?= $border ?>;background:#f9f9f9;width:120px;">IP</td>
                <td style="padding:6px 10px;border:<?= $border ?>;"><?= esc_html($visitor['ip'] ?? 'N/A') ?></td></tr>
            <tr><td style="padding:6px 10px;border:<?= $border ?>;background:#f9f9f9;">UA</td>
                <td style="padding:6px 10px;border:<?= $border ?>;font-size:12px;word-break:break-all;"><?= esc_html($visitor['ua'] ?? 'N/A') ?></td></tr>
            <tr><td style="padding:6px 10px;border:<?= $border ?>;background:#f9f9f9;">Page</td>
                <td style="padding:6px 10px;border:<?= $border ?>;"><?= esc_html($visitor['page_url'] ?? 'N/A') ?></td></tr>
        </tbody>
    </table>

<?php if ($conversation_id > 0):
    $url = admin_url('post.php?post=' . $conversation_id . '&action=edit');
?>
    <p style="text-align:center;margin:16px 0;">
        <a href="<?= esc_url($url) ?>" style="display:inline-block;padding:10px 20px;background:#333;color:#fff;text-decoration:none;border-radius:4px;font-size:14px;">View Conversation</a>
    </p>
<?php endif; ?>

    <hr style="border:none;border-top:1px solid #e0e0e0;">
    <p style="font-size:11px;color:#999;text-align:center;">This notification was sent automatically by the AI Chatbot plugin.</p>
</div>
