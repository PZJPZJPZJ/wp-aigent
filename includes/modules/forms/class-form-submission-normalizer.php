<?php
defined('ABSPATH') || exit;

/** Normalizes local data and builds a locally masked full-submission AI payload. */
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
            'llm_payload' => $this->masked_payload($submission, $contact),
        ];
    }

    /** Include all useful submission data after deterministic local masking. */
    private function masked_payload(array $submission, array $contact): array {
        $fields = [];
        foreach ((array) ($submission['fields'] ?? []) as $fallback_key => $field) {
            [$semantic_key, $value] = $this->field_parts($fallback_key, $field);
            $contact_type = $this->contact_type($this->normalize_key($semantic_key));
            $fields[] = [
                'key' => sanitize_text_field((string) ($field['key'] ?? $fallback_key)),
                'label' => sanitize_text_field((string) ($field['label'] ?? '')),
                'type' => sanitize_key((string) ($field['type'] ?? '')),
                'value' => $contact_type ? $this->mask_contact($value, $contact_type) : $this->redact_personal_data($value, $contact),
            ];
        }

        return [
            'source_type' => sanitize_key((string) ($submission['source_type'] ?? '')),
            'source_record_id' => absint($submission['source_record_id'] ?? 0),
            'source_created_at_gmt' => sanitize_text_field((string) ($submission['source_created_at_gmt'] ?? '')),
            'form_name' => $this->redact_personal_data(sanitize_text_field((string) ($submission['form_name'] ?? '')), $contact),
            'form_id' => $this->redact_personal_data(sanitize_text_field((string) ($submission['form_id'] ?? '')), $contact),
            'page_id' => absint($submission['page_id'] ?? 0),
            'page_url' => $this->mask_url((string) ($submission['page_url'] ?? ''), $contact),
            'page_title' => $this->redact_personal_data(sanitize_text_field((string) ($submission['page_title'] ?? '')), $contact),
            'campaign_id' => $this->redact_personal_data(sanitize_text_field((string) ($submission['campaign_id'] ?? '')), $contact),
            'fields' => $fields,
        ];
    }

    private function redact_personal_data(string $text, array $contact): string {
        foreach ($contact as $value) {
            $value = trim((string) $value);
            if (mb_strlen($value) >= 2) $text = str_ireplace($value, '[contact removed]', $text);
        }
        $text = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', '[email removed]', $text);
        $text = preg_replace('/(?<!\w)(?:\+|00)?\d[\d\s().\-]{6,}\d(?!\w)/u', '[phone removed]', $text);
        $text = preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/u', '[ip removed]', $text);
        $text = preg_replace('/\b(?:\d[ -]*?){13,19}\b/u', '[payment number removed]', $text);
        return trim((string) $text);
    }

    private function mask_url(string $url, array $contact): string {
        $url = esc_url_raw($url);
        if ($url === '') return '';
        $parts = wp_parse_url($url);
        if (!is_array($parts)) return '';
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        foreach ($query as $key => $value) {
            $type = $this->contact_type($this->normalize_key((string) $key));
            if ($type && is_scalar($value)) $query[$key] = $this->mask_contact((string) $value, $type);
        }
        $base = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
        if (!empty($parts['port'])) $base .= ':' . absint($parts['port']);
        $base .= $parts['path'] ?? '';
        if ($query) $base .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        if (!empty($parts['fragment'])) $base .= '#' . sanitize_text_field((string) $parts['fragment']);
        return $this->redact_personal_data($base, $contact);
    }

    private function is_contact_key(string $key): bool {
        foreach (self::CONTACT_PATTERNS as $pattern) {
            if (preg_match($pattern, $key)) return true;
        }
        return false;
    }

    private function contact_type(string $key): string {
        foreach (self::CONTACT_PATTERNS as $type => $pattern) {
            if (preg_match($pattern, $key)) return $type;
        }
        return '';
    }

    private function mask_contact(string $value, string $type): string {
        $value = trim($value);
        if ($value === '') return '';
        if ($type === 'email' && str_contains($value, '@')) {
            [$local, $domain] = array_pad(explode('@', $value, 2), 2, '');
            return mb_substr($local, 0, 1) . '***@' . $domain;
        }
        if (in_array($type, ['phone', 'whatsapp'], true)) {
            $digits = preg_replace('/\D+/', '', $value);
            return $digits ? '***' . substr($digits, -4) : '[masked phone]';
        }
        $visible = mb_substr($value, 0, $type === 'company' ? 2 : 1);
        return $visible . str_repeat('*', max(3, min(8, mb_strlen($value) - mb_strlen($visible))));
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
