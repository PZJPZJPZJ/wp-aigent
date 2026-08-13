<?php
defined('ABSPATH') || exit;

/** Deterministically normalizes source/contact data and isolates safe requirement text. */
class WP_AIGent_Form_Submission_Normalizer {

    private const CONTACT_PATTERNS = [
        'name' => '/(^|[_-])(name|full.?name|first.?name|last.?name|姓名|名字)([_-]|$)/iu',
        'email' => '/(email|e-mail|邮箱|邮件)/iu',
        'phone' => '/(phone|mobile|tel|telephone|电话|手机)/iu',
        'whatsapp' => '/(whatsapp|wa.?number|what.?app)/iu',
        'company' => '/(company|organization|organisation|business.?name|公司|企业|组织)/iu',
        'country' => '/(country|国家)/iu',
        'region' => '/(state|province|region|州|省|地区)/iu',
        'city' => '/(city|城市|市区)/iu',
        'language' => '/(language|lang|语言)/iu',
    ];

    private const REQUIREMENT_PATTERN = '/(message|comment|inquiry|enquiry|requirement|requirements|request|project|brief|details|description|need|needs|service|product|budget|timeline|deadline|需求|留言|咨询|项目|服务|产品|预算|工期|时间|描述|详情)/iu';

    public function normalize(array $submission): array {
        $fields = is_array($submission['fields'] ?? null) ? $submission['fields'] : [];
        $contact = array_fill_keys(array_keys(self::CONTACT_PATTERNS), '');
        $page_url = esc_url_raw((string) ($submission['page_url'] ?? ''));
        $query = [];
        parse_str((string) wp_parse_url($page_url, PHP_URL_QUERY), $query);
        $traffic_medium = sanitize_text_field((string) ($query['utm_medium'] ?? ''));

        foreach ($fields as $field_key => $field) {
            [$semantic_key, $value] = $this->field_parts($field_key, $field);
            $key = $this->normalize_key($semantic_key);
            if ($value === '') continue;
            foreach (self::CONTACT_PATTERNS as $contact_key => $pattern) {
                if ($contact[$contact_key] === '' && preg_match($pattern, $key)) {
                    $contact[$contact_key] = $value;
                    break;
                }
            }
        }

        $contact['email'] = sanitize_email(strtolower(trim($contact['email'])));
        $contact['phone'] = $this->normalize_phone($contact['phone']);
        $contact['whatsapp'] = $this->normalize_phone($contact['whatsapp']);
        foreach ($contact as $key => $value) {
            if (!in_array($key, ['email', 'phone', 'whatsapp'], true)) $contact[$key] = sanitize_text_field($value);
        }

        return [
            'source' => [
                'channel' => 'form',
                'platform' => 'elementor',
                'form_name' => sanitize_text_field((string) ($submission['form_name'] ?? '')),
                'form_id' => sanitize_text_field((string) ($submission['form_id'] ?? '')),
                'page_id' => absint($submission['page_id'] ?? 0),
                'page_url' => $page_url,
                'campaign_id' => sanitize_text_field((string) ($submission['campaign_id'] ?? '')),
                'source_category' => $this->source_category($query, $traffic_medium),
                'traffic_source' => sanitize_text_field((string) ($query['utm_source'] ?? '')),
                'traffic_medium' => $traffic_medium,
                'traffic_campaign' => sanitize_text_field((string) ($query['utm_campaign'] ?? '')),
                'traffic_term' => sanitize_text_field((string) ($query['utm_term'] ?? '')),
                'traffic_content' => sanitize_text_field((string) ($query['utm_content'] ?? '')),
            ],
            'contact' => $contact,
            'requirements_text' => $this->requirements_text($fields, $contact),
        ];
    }

    /** Only explicitly requirement-like fields can enter the external AI payload. */
    private function requirements_text(array $fields, array $contact): string {
        $parts = [];
        foreach ($fields as $field_key => $field) {
            [$semantic_key, $value] = $this->field_parts($field_key, $field);
            $key = $this->normalize_key($semantic_key);
            if (!preg_match(self::REQUIREMENT_PATTERN, $key) || $this->is_contact_key($key)) continue;
            if ($value !== '') $parts[] = $value;
        }
        $text = implode("\n\n", array_values(array_unique($parts)));
        return mb_substr($this->redact_personal_data($text, $contact), 0, 12000);
    }

    private function redact_personal_data(string $text, array $contact): string {
        foreach ($contact as $value) {
            $value = trim((string) $value);
            if (mb_strlen($value) >= 2) $text = str_ireplace($value, '[contact removed]', $text);
        }
        $text = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', '[email removed]', $text);
        $text = preg_replace('#https?://\S+|www\.\S+#iu', '[url removed]', $text);
        $text = preg_replace('/(?<!\w)(?:\+|00)?\d[\d\s().\-]{6,}\d(?!\w)/u', '[phone removed]', $text);
        return trim((string) $text);
    }

    private function is_contact_key(string $key): bool {
        foreach (self::CONTACT_PATTERNS as $pattern) {
            if (preg_match($pattern, $key)) return true;
        }
        return false;
    }

    private function normalize_phone(string $phone): string {
        $phone = trim($phone);
        if ($phone === '') return '';
        $has_plus = str_starts_with($phone, '+');
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === '') return '';
        if (str_starts_with($digits, '00')) {
            $has_plus = true;
            $digits = substr($digits, 2);
        }
        return ($has_plus ? '+' : '') . $digits;
    }

    private function source_category(array $query, string $medium): string {
        if (!empty($query['gclid']) || !empty($query['fbclid']) || preg_match('/(cpc|ppc|paid|display|affiliate)/i', $medium)) return 'paid';
        if (preg_match('/(organic|seo)/i', $medium)) return 'organic';
        if (preg_match('/(email|newsletter)/i', $medium)) return 'email';
        if (preg_match('/(social|social-network|social-media)/i', $medium)) return 'social';
        if (!empty($query['utm_source']) || $medium !== '') return 'campaign';
        return 'direct_or_referral';
    }

    private function normalize_key(string $key): string {
        return mb_strtolower(trim(str_replace([' ', '.'], '_', $key)));
    }

    private function field_parts($fallback_key, $field): array {
        if (is_array($field) && array_key_exists('value', $field)) {
            $semantic_key = implode(' ', array_filter([
                (string) ($field['key'] ?? ''),
                (string) ($field['label'] ?? ''),
                (string) ($field['type'] ?? ''),
            ]));
            return [$semantic_key, $this->clean_scalar($field['value'])];
        }
        return [(string) $fallback_key, $this->clean_scalar($field)];
    }

    private function clean_scalar($value): string {
        if (is_array($value)) $value = implode(', ', array_map('strval', $value));
        return trim(wp_strip_all_tags((string) $value));
    }
}
