<?php defined('ABSPATH') || exit; ?>
<div class="wrap wp-aigent-ai-form-admin">
    <h1><?php esc_html_e('AI Forms', 'wp-aigent'); ?></h1>
    <form method="post" action="options.php">
        <?php settings_fields('wp_aigent_ai_form'); ?>
        <div class="wp-aigent-settings-panel">
            <h2><?php esc_html_e('Elementor Form Enhancer', 'wp-aigent'); ?></h2>
            <label class="wp-aigent-checkbox-row">
                <input type="checkbox" name="<?php echo esc_attr(WP_AIGent_Forms_Module::OPTION_NAME); ?>[elementor_enabled]" value="1" <?php checked($settings['elementor_enabled'], '1'); ?>>
                <strong><?php esc_html_e('Add Country Code field type', 'wp-aigent'); ?></strong>
            </label>
            <p class="description"><?php esc_html_e('Adds the country-code field to Elementor Forms. This setting is independent of submission analysis.', 'wp-aigent'); ?></p>
        </div>
        <div class="wp-aigent-settings-panel">
            <h2><?php esc_html_e('Form Information Analysis Model', 'wp-aigent'); ?></h2>
            <p class="description"><?php esc_html_e('The complete form submission can be analyzed by the selected model. Contact fields and personal patterns are masked locally before any data is sent.', 'wp-aigent'); ?></p>
            <table class="form-table" role="presentation"><tbody>
                <tr><th scope="row"><label for="wp-aigent-analysis-provider"><?php esc_html_e('API Provider', 'wp-aigent'); ?></label></th><td>
                    <select id="wp-aigent-analysis-provider" name="<?php echo esc_attr(WP_AIGent_Forms_Module::OPTION_NAME); ?>[analysis_provider_id]">
                        <option value="0"><?php esc_html_e('Choose a Provider', 'wp-aigent'); ?></option>
                        <?php foreach ($providers as $provider) : ?><option value="<?php echo esc_attr($provider->ID); ?>" <?php selected((int) $settings['analysis_provider_id'], $provider->ID); ?>><?php echo esc_html($provider->post_title); ?></option><?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e('The selected Provider receives a locally masked copy of the form, page, campaign, and all submitted fields.', 'wp-aigent'); ?></p>
                </td></tr>
                <tr><th scope="row"><label for="wp-aigent-analysis-model"><?php esc_html_e('Analysis model', 'wp-aigent'); ?></label></th><td>
                    <select id="wp-aigent-analysis-model" data-selected="<?php echo esc_attr($settings['analysis_model']); ?>" name="<?php echo esc_attr(WP_AIGent_Forms_Module::OPTION_NAME); ?>[analysis_model]"></select>
                    <p class="description"><?php esc_html_e('Use a model that reliably returns structured JSON. Save the settings before starting analysis.', 'wp-aigent'); ?></p>
                </td></tr>
                <tr><th scope="row"><label for="wp-aigent-analysis-effort"><?php esc_html_e('Reasoning effort', 'wp-aigent'); ?></label></th><td>
                    <select id="wp-aigent-analysis-effort" name="<?php echo esc_attr(WP_AIGent_Forms_Module::OPTION_NAME); ?>[analysis_reasoning_effort]">
                        <?php foreach (['off', 'low', 'medium', 'high', 'xhigh'] as $effort) : ?><option value="<?php echo esc_attr($effort); ?>" <?php selected($settings['analysis_reasoning_effort'], $effort); ?>><?php echo esc_html(ucfirst($effort)); ?></option><?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e('Off is recommended for short requirement summaries and usually costs less.', 'wp-aigent'); ?></p>
                </td></tr>
                <tr><th scope="row"><label for="wp-aigent-analysis-tokens"><?php esc_html_e('Maximum output tokens', 'wp-aigent'); ?></label></th><td>
                    <input id="wp-aigent-analysis-tokens" type="number" min="256" max="8000" name="<?php echo esc_attr(WP_AIGent_Forms_Module::OPTION_NAME); ?>[analysis_output_tokens]" value="<?php echo esc_attr($settings['analysis_output_tokens']); ?>">
                    <p class="description"><?php esc_html_e('Limits the generated summary. 1600 is suitable for most form submissions.', 'wp-aigent'); ?></p>
                </td></tr>
            </tbody></table>
        </div>
        <?php submit_button(); ?>
    </form>
</div>
