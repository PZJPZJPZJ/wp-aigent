<?php
defined('ABSPATH') || exit;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/** WordPress-native list table backed by Elementor submissions. */
class WP_AIGent_Submissions_List_Table extends WP_List_Table {

    private WP_AIGent_Form_Submissions_Query $query;
    private array $filters;
    private array $filter_options;

    public function __construct(WP_AIGent_Form_Submissions_Query $query, array $filters, array $filter_options) {
        $this->query = $query;
        $this->filters = $filters;
        $this->filter_options = $filter_options;
        parent::__construct([
            'singular' => 'wp_aigent_submission',
            'plural' => 'wp_aigent_submissions',
            'ajax' => false,
        ]);
    }

    public static function column_definitions(): array {
        return [
            'id' => __('ID', 'wp-aigent'),
            'email' => __('Email', 'wp-aigent'),
            'form' => __('Form', 'wp-aigent'),
            'page_url' => __('Page', 'wp-aigent'),
            'submission_date' => __('Submission Date', 'wp-aigent'),
            'requirements' => __('Requirements', 'wp-aigent'),
            'spam' => __('Spam', 'wp-aigent'),
            'intent' => __('Intent', 'wp-aigent'),
            'analysis_status' => __('Analysis Status', 'wp-aigent'),
        ];
    }

    public function get_columns(): array {
        return self::column_definitions();
    }

    protected function get_sortable_columns(): array {
        return [
            'id' => ['id', false],
            'submission_date' => ['submission_date', true],
        ];
    }

    protected function get_primary_column_name(): string {
        return 'id';
    }

    public function prepare_items(): void {
        $per_page = $this->get_items_per_page('wp_aigent_submissions_per_page', 20);
        $page = $this->get_pagenum();
        $orderby = sanitize_key(wp_unslash($_REQUEST['orderby'] ?? 'submission_date'));
        $order = sanitize_key(wp_unslash($_REQUEST['order'] ?? 'desc'));
        $result = $this->query->query($this->filters, $page, $per_page, $orderby, $order);
        $this->items = $result['items'];
        $this->_column_headers = [
            $this->get_columns(),
            get_hidden_columns($this->screen),
            $this->get_sortable_columns(),
            $this->get_primary_column_name(),
        ];
        $this->set_pagination_args([
            'total_items' => $result['total'],
            'per_page' => $per_page,
            'total_pages' => (int) ceil($result['total'] / $per_page),
        ]);
    }

    public function no_items(): void {
        esc_html_e('No Elementor submissions match the current filters.', 'wp-aigent');
    }

    protected function extra_tablenav($which): void {
        if ($which !== 'top') return;
        $forms = $this->filter_options['forms'] ?? [];
        $pages = $this->filter_options['pages'] ?? [];
        ?>
        <div class="alignleft actions">
            <select name="form">
                <option value=""><?php esc_html_e('All forms', 'wp-aigent'); ?></option>
                <?php foreach ($forms as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected($this->filters['form'], $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
            </select>
            <select name="page_url">
                <option value=""><?php esc_html_e('All pages', 'wp-aigent'); ?></option>
                <?php foreach ($pages as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected($this->filters['page_url'], $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
            </select>
            <input type="date" name="date_from" aria-label="<?php esc_attr_e('From', 'wp-aigent'); ?>" value="<?php echo esc_attr($this->filters['date_from']); ?>">
            <input type="date" name="date_to" aria-label="<?php esc_attr_e('To', 'wp-aigent'); ?>" value="<?php echo esc_attr($this->filters['date_to']); ?>">
            <select name="is_spam">
                <option value=""><?php esc_html_e('All spam statuses', 'wp-aigent'); ?></option>
                <option value="no" <?php selected($this->filters['spam'], 'no'); ?>><?php esc_html_e('Not spam', 'wp-aigent'); ?></option>
                <option value="yes" <?php selected($this->filters['spam'], 'yes'); ?>><?php esc_html_e('Spam', 'wp-aigent'); ?></option>
            </select>
            <select name="intent">
                <option value=""><?php esc_html_e('All intents', 'wp-aigent'); ?></option>
                <?php foreach (['high' => __('High', 'wp-aigent'), 'medium' => __('Medium', 'wp-aigent'), 'low' => __('Low', 'wp-aigent'), 'unknown' => __('Unknown', 'wp-aigent')] as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected($this->filters['intent'], $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
            </select>
            <select name="analysis_status">
                <option value=""><?php esc_html_e('All analysis statuses', 'wp-aigent'); ?></option>
                <?php foreach (['unanalyzed' => __('Unanalyzed', 'wp-aigent'), 'pending' => __('Pending', 'wp-aigent'), 'running' => __('Running', 'wp-aigent'), 'succeeded' => __('Succeeded', 'wp-aigent'), 'failed' => __('Failed', 'wp-aigent')] as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected($this->filters['analysis_status'], $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
            </select>
            <?php submit_button(__('Filter', 'wp-aigent'), '', 'filter_action', false); ?>
        </div>
        <?php
    }

    public function column_default($item, $column_name): string {
        return esc_html((string) ($item[$column_name] ?? ''));
    }

    public function column_email($item): string {
        $email = (string) ($item['email'] ?? '');
        return $email === '' ? '&mdash;' : '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
    }

    public function column_id($item): string {
        return '<strong><a href="' . esc_url($item['detail_url']) . '">#' . esc_html((string) $item['source_record_id']) . '</a></strong>';
    }

    public function column_page_url($item): string {
        if ($item['page_url'] === '') return '';
        return '<a href="' . esc_url($item['page_url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($item['page_url']) . '</a>';
    }

    public function column_spam($item): string {
        if ($item['is_spam'] === null) return '&mdash;';
        $label = $item['is_spam'] ? __('Yes', 'wp-aigent') : __('No', 'wp-aigent');
        $output = '<strong>' . esc_html($label) . '</strong>';
        if ($item['spam_reason'] !== '') $output .= '<br><small>' . esc_html($item['spam_reason']) . '</small>';
        return $output;
    }

    public function column_form($item): string {
        return esc_html((string) $item['form_name']);
    }

    public function column_submission_date($item): string {
        return esc_html($this->local_time((string) $item['source_created_at_gmt']));
    }

    public function column_requirements($item): string {
        return esc_html((string) $item['requirements_summary']);
    }

    public function column_intent($item): string {
        if ($item['intent_level'] === '') return '&mdash;';
        $labels = ['high' => __('High', 'wp-aigent'), 'medium' => __('Medium', 'wp-aigent'), 'low' => __('Low', 'wp-aigent'), 'unknown' => __('Unknown', 'wp-aigent')];
        $output = '<strong>' . esc_html($labels[$item['intent_level']] ?? (string) $item['intent_level']) . '</strong>';
        if ($item['intent_summary'] !== '') $output .= '<br><small>' . esc_html($item['intent_summary']) . '</small>';
        return $output;
    }

    public function column_analysis_status($item): string {
        $status = (string) $item['analysis_status'];
        $labels = [
            'unanalyzed' => __('Unanalyzed', 'wp-aigent'), 'pending' => __('Pending', 'wp-aigent'),
            'running' => __('Running', 'wp-aigent'), 'succeeded' => __('Succeeded', 'wp-aigent'), 'failed' => __('Failed', 'wp-aigent'),
        ];
        $output = '<strong>' . esc_html($labels[$status] ?? $status) . '</strong>';
        if ($status === 'failed' && $item['error_message'] !== '') $output .= '<br><small>' . esc_html(wp_html_excerpt($item['error_message'], 160, '&hellip;')) . '</small>';
        return $output;
    }

    private function local_time(string $gmt): string {
        return $gmt === '' ? '' : get_date_from_gmt($gmt, 'Y-m-d H:i');
    }
}
