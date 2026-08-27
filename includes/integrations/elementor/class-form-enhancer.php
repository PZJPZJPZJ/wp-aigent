<?php
defined('ABSPATH') || exit;

class WP_AIGent_Elementor_Form_Enhancer {

    public function init(): void {
        add_action('elementor_pro/forms/fields/register', [$this, 'register_country_code_field']);
        add_action('elementor_pro/forms/new_record', [$this, 'format_country_code_submission'], 5, 2);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_country_code_script']);
        add_action('elementor/frontend/after_enqueue_scripts', [$this, 'enqueue_country_code_script']);
    }

    public function enqueue_country_code_script(): void {
        if ((string) WP_AIGent_Attribution_Settings::get('country_code_enabled') !== '1') return;
        $path = 'assets/modules/forms/js/country-code.js';
        $mtime = is_readable(WP_AIGENT_PATH . $path) ? filemtime(WP_AIGENT_PATH . $path) : false;
        wp_enqueue_script('wp-aigent-country-code', WP_AIGENT_URL . $path, [], $mtime ? (string) $mtime : WP_AIGENT_VERSION, ['strategy' => 'defer', 'in_footer' => true]);
    }

    public function register_country_code_field($fields_manager): void {
        if ((string) WP_AIGent_Attribution_Settings::get('country_code_enabled') !== '1') {
            return;
        }

        if (!class_exists('ElementorPro\Modules\Forms\Fields\Field_Base')) {
            return;
        }

        require_once WP_AIGENT_PATH . 'includes/integrations/elementor/class-country-code-field.php';

        if (method_exists($fields_manager, 'register')) {
            $fields_manager->register(new WP_AIGent_Elementor_Country_Code_Field());
        } elseif (method_exists($fields_manager, 'register_field_type')) {
            $fields_manager->register_field_type(new WP_AIGent_Elementor_Country_Code_Field());
        }
    }

    public function format_country_code_submission($record, $ajax_handler = null): void {
        if ((string) WP_AIGent_Attribution_Settings::get('country_code_enabled') !== '1' || !is_object($record) || !method_exists($record, 'get')) {
            return;
        }

        $fields = $record->get('fields');
        if (!is_array($fields)) {
            return;
        }

        $changed = false;
        foreach ($fields as $field_id => $field) {
            if (!is_array($field) || ($field['type'] ?? '') !== 'wp_aigent_country_code') {
                continue;
            }

            $country_code = $this->extract_country_code($field);
            if ($country_code === '') {
                if ($this->has_submitted_value($field)) {
                    $this->add_country_error($ajax_handler, (string) $field_id);
                }
                continue;
            }

            $display = WP_AIGent_Country_Resolver::format_country_display($country_code);
            if ($display === '') {
                $this->add_country_error($ajax_handler, (string) $field_id);
                continue;
            }

            $fields[$field_id]['raw_value'] = $country_code;
            $fields[$field_id]['value'] = $display;
            $changed = true;
        }

        if ($changed) {
            $this->replace_record_fields($record, $fields);
        }
    }

    private function extract_country_code(array $field): string {
        foreach (['raw_value', 'value'] as $key) {
            if (!isset($field[$key]) || is_array($field[$key])) {
                continue;
            }

            $country_code = WP_AIGent_Country_Resolver::country_from_submitted_value((string) $field[$key]);
            if ($country_code !== '') {
                return $country_code;
            }
        }

        return '';
    }

    private function has_submitted_value(array $field): bool {
        foreach (['raw_value', 'value'] as $key) {
            if (!isset($field[$key]) || is_array($field[$key])) {
                continue;
            }

            if ((string) $field[$key] !== '') {
                return true;
            }
        }

        return false;
    }

    private function add_country_error($ajax_handler, string $field_id): void {
        if (is_object($ajax_handler) && method_exists($ajax_handler, 'add_error')) {
            $ajax_handler->add_error($field_id, __('Please select a valid country/region.', 'wp-aigent'));
        }
    }

    private function replace_record_fields(object $record, array $fields): void {
        if (method_exists($record, 'set')) {
            try {
                $record->set('fields', $fields);
                return;
            } catch (\Throwable $e) {
                // Fall through to Elementor's field-level updater when needed.
            }
        }

        foreach ($fields as $field_id => $field) {
            $this->replace_record_field($record, (string) $field_id, $field);
        }
    }

    private function replace_record_field(object $record, string $field_id, array $field): void {
        if (!method_exists($record, 'update_field')) {
            return;
        }

        try {
            $method = new \ReflectionMethod($record, 'update_field');
            $parameter_count = $method->getNumberOfParameters();

            if ($parameter_count >= 3) {
                $record->update_field($field_id, 'value', $field['value']);
                $record->update_field($field_id, 'raw_value', $field['raw_value']);
                return;
            }

            if ($parameter_count >= 2) {
                $record->update_field($field_id, $field);
            }
        } catch (\Throwable $e) {
            return;
        }
    }
}
