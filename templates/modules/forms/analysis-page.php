<?php defined('ABSPATH') || exit; ?>
<div class="wrap wp-aigent-ai-form-admin">
    <h1><?php esc_html_e('Submissions', 'wp-aigent'); ?></h1>
    <div class="wp-aigent-settings-panel wp-aigent-submissions-panel">
        <form method="get" id="wp-aigent-submissions-filter" class="wp-aigent-submissions-table">
            <input type="hidden" name="post_type" value="ai_chatbot">
            <input type="hidden" name="page" value="wp-aigent-submission-analysis">
            <?php $submission_table->search_box(__('Search submissions', 'wp-aigent'), 'wp-aigent-submissions'); ?>
            <?php $submission_table->display(); ?>
            <div class="wp-aigent-analysis-run">
                <button type="button" class="button button-primary" data-analysis-mode="update"><?php esc_html_e('Update Analysis', 'wp-aigent'); ?></button>
                <button type="button" class="button" data-analysis-mode="overwrite"><?php esc_html_e('Overwrite Analysis', 'wp-aigent'); ?></button>
            </div>
        </form>
        <div id="wp-aigent-analysis-progress" class="wp-aigent-analysis-progress" hidden>
            <div class="wp-aigent-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span></span></div>
            <p class="wp-aigent-progress-label"></p>
        </div>
    </div>
</div>
