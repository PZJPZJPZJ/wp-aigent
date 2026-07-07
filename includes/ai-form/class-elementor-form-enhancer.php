<?php
defined('ABSPATH') || exit;

class WP_AIGent_Elementor_Form_Enhancer {

    public function init(): void {
        add_action('elementor_pro/forms/fields/register', [$this, 'register_country_code_field']);
    }

    public function register_country_code_field($fields_manager): void {
        $settings = WP_AIGent_AI_Form::get_settings();
        if ($settings['elementor_enabled'] !== '1') {
            return;
        }

        if (!class_exists('ElementorPro\Modules\Forms\Fields\Field_Base')) {
            return;
        }

        require_once WP_AIGENT_PATH . 'includes/ai-form/class-elementor-country-code-field.php';

        if (method_exists($fields_manager, 'register')) {
            $fields_manager->register(new WP_AIGent_Elementor_Country_Code_Field());
        } elseif (method_exists($fields_manager, 'register_field_type')) {
            $fields_manager->register_field_type(new WP_AIGent_Elementor_Country_Code_Field());
        }
    }
}
