<?php
defined('ABSPATH') || exit;

class WP_AIGent_Elementor_Country_Code_Field extends \ElementorPro\Modules\Forms\Fields\Field_Base {

    public function get_type() {
        return 'wp_aigent_country_code';
    }

    public function get_name() {
        return __('Country Code', 'wp-aigent');
    }

    public function update_controls($widget) {
        $control_data = \Elementor\Plugin::instance()->controls_manager->get_control_from_stack(
            $widget->get_unique_name(),
            'form_fields'
        );

        if (is_wp_error($control_data) || empty($control_data['fields']) || !is_array($control_data['fields'])) {
            return;
        }

        $field_controls = [
            'wp_aigent_use_cf_ipcountry' => [
                'name'         => 'wp_aigent_use_cf_ipcountry',
                'label'        => __('Use CF-IPCountry', 'wp-aigent'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'label_on'     => __('Yes', 'wp-aigent'),
                'label_off'    => __('No', 'wp-aigent'),
                'return_value' => 'yes',
                'render_type'  => 'template',
                'description'  => __('Auto-detects visitor country from Cloudflare edge (<code>/cdn-cgi/trace</code>) when Cloudflare is active.<br>Disable to always use the Default Country.', 'wp-aigent'),
                'condition'    => [
                    'field_type' => $this->get_type(),
                ],
                'tab'          => 'content',
                'inner_tab'    => 'form_fields_content_tab',
                'tabs_wrapper' => 'form_fields_tabs',
            ],
            'wp_aigent_default_country' => [
                'name'         => 'wp_aigent_default_country',
                'label'        => __('Default Country', 'wp-aigent'),
                'type'         => \Elementor\Controls_Manager::SELECT,
                'default'      => 'US',
                'options'      => WP_AIGent_Country_Resolver::country_select_options(),
                'render_type'  => 'template',
                'condition'    => [
                    'field_type' => $this->get_type(),
                ],
                'tab'          => 'content',
                'inner_tab'    => 'form_fields_content_tab',
                'tabs_wrapper' => 'form_fields_tabs',
            ],
        ];

        if (method_exists($this, 'inject_field_controls')) {
            $control_data['fields'] = $this->inject_field_controls($control_data['fields'], $field_controls);
        } else {
            $control_data['fields'] = array_merge($control_data['fields'], $field_controls);
        }

        $widget->update_control('form_fields', $control_data);
    }

    public function render($item, $item_index, $form) {
        $field_id = !empty($item['custom_id']) ? sanitize_key($item['custom_id']) : 'country_code';
        $field_name = 'form_fields[' . $field_id . ']';
        $field_dom_id = 'form-field-' . $field_id;
        $default_country = WP_AIGent_Country_Resolver::normalize_country((string) ($item['wp_aigent_default_country'] ?? 'US')) ?: 'US';
        $use_cloudflare_country = ($item['wp_aigent_use_cf_ipcountry'] ?? 'yes') === 'yes';
        // Always use the configured default at render time. When CF-IPCountry
        // is enabled, the frontend JS (country-code.js) will fetch the real
        // country from an uncached REST endpoint and update the select.
        $selected_country = $default_country;
        $countries = WP_AIGent_Country_Resolver::country_options();

        $form->add_render_attribute('wp-aigent-country-code-' . $item_index, [
            'name'                    => $field_name,
            'id'                      => $field_dom_id,
            'class'                   => 'elementor-field-textual elementor-field',
            'data-wp-aigent-country-code' => '1',
            'data-use-cf-ipcountry'       => $use_cloudflare_country ? '1' : '0',
            'data-default-country'        => $default_country,
        ]);

        if (!empty($item['required'])) {
            $form->add_render_attribute('wp-aigent-country-code-' . $item_index, 'required', 'required');
            $form->add_render_attribute('wp-aigent-country-code-' . $item_index, 'aria-required', 'true');
        }

        if (!empty($item['field_label'])) {
            $form->add_render_attribute('wp-aigent-country-code-' . $item_index, 'aria-label', $item['field_label']);
        }

        echo '<select ' . $form->get_render_attribute_string('wp-aigent-country-code-' . $item_index) . '>';
        foreach ($countries as $country_code => $country) {
            $dial = $country['dial'] ?? '';
            $name = $country['name'] ?? $country_code;
            if ($dial === '') {
                continue;
            }

            printf(
                '<option value="%1$s" data-country="%2$s"%3$s>%4$s</option>',
                esc_attr($dial),
                esc_attr($country_code),
                selected($selected_country, $country_code, false),
                esc_html($name . ' (' . $dial . ')')
            );
        }
        echo '</select>';
    }
}
