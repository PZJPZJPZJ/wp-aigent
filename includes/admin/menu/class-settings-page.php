<?php
defined('ABSPATH') || exit;

/** Registers the global WP AIgent Settings page and its Security tab. */
class WP_AIGent_Settings_Page {
    private const PAGE_SLUG = 'wp-aigent-settings';

    public function register(): void {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_menu(): void {
        add_submenu_page(
            'edit.php?post_type=ai_chatbot',
            __('WP AIgent Settings', 'wp-aigent'),
            __('Settings', 'wp-aigent'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render'],
            99
        );
    }

    public function register_settings(): void {
        register_setting(
            'wp_aigent_security',
            WP_AIGent_Security_Settings::OPTION_NAME,
            [
                'type'              => 'array',
                'sanitize_callback' => [WP_AIGent_Security_Settings::class, 'sanitize'],
                'default'           => [],
            ]
        );
    }

    public function render(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage WP AIgent settings.', 'wp-aigent'));
        }

        $settings = WP_AIGent_Security_Settings::all();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('WP AIgent Settings', 'wp-aigent'); ?></h1>
            <nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e('Settings sections', 'wp-aigent'); ?>">
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=ai_chatbot&page=' . self::PAGE_SLUG . '&tab=security')); ?>" class="nav-tab nav-tab-active"><?php esc_html_e('Security', 'wp-aigent'); ?></a>
            </nav>

            <form method="post" action="options.php">
                <?php settings_fields('wp_aigent_security'); ?>
                <h2><?php esc_html_e('Visitor identity', 'wp-aigent'); ?></h2>
                <p class="description"><?php esc_html_e('Visitor IDs are issued by the server and authorized by a 90-day rolling HttpOnly cookie. Unsigned or modified identities cannot create conversations or read history.', 'wp-aigent'); ?></p>
                <?php if (!WP_AIGent_Visitor_Identity::uses_secure_cookie()): ?>
                    <div class="notice notice-warning inline"><p><?php esc_html_e('This site is not configured for HTTPS. The Visitor cookie cannot use Secure or the __Host- prefix until both WordPress URLs use HTTPS.', 'wp-aigent'); ?></p></div>
                <?php endif; ?>
                <table class="form-table" role="presentation">
                    <?php $this->number_row('visitor_issue_limit', __('New identities per window', 'wp-aigent'), $settings, 1, 1000, __('Maximum new server-signed visitor identities issued to one IP during the window. Existing valid identities do not consume this limit.', 'wp-aigent')); ?>
                    <?php $this->number_row('visitor_issue_window', __('Identity window (seconds)', 'wp-aigent'), $settings, 60, DAY_IN_SECONDS, __('Time window for identity issuance limiting. A longer window reduces automated conversation creation but may affect shared networks.', 'wp-aigent')); ?>
                </table>

                <h2><?php esc_html_e('Public endpoint limits', 'wp-aigent'); ?></h2>
                <table class="form-table" role="presentation">
                    <?php $this->number_row('chat_visitor_rate_limit', __('Chat requests per Visitor', 'wp-aigent'), $settings, 1, 1000, __('Maximum chat requests from one authorized Visitor during the window. Requests above the limit return HTTP 429.', 'wp-aigent')); ?>
                    <?php $this->number_row('chat_ip_rate_limit', __('Chat requests per IP', 'wp-aigent'), $settings, 1, 5000, __('Maximum chat requests from one resolved client IP during the window. Keep this higher than the Visitor limit to support shared networks.', 'wp-aigent')); ?>
                    <?php $this->number_row('chat_rate_window', __('Chat window (seconds)', 'wp-aigent'), $settings, 1, HOUR_IN_SECONDS, __('Time window used by the chat request limit.', 'wp-aigent')); ?>
                    <?php $this->number_row('history_visitor_rate_limit', __('History requests per Visitor', 'wp-aigent'), $settings, 1, 2000, __('Maximum history reads from one authorized Visitor during the window.', 'wp-aigent')); ?>
                    <?php $this->number_row('history_ip_rate_limit', __('History requests per IP', 'wp-aigent'), $settings, 1, 10000, __('Maximum history reads from one resolved client IP during the window.', 'wp-aigent')); ?>
                    <?php $this->number_row('history_rate_window', __('History window (seconds)', 'wp-aigent'), $settings, 1, HOUR_IN_SECONDS, __('Time window used by the history request limit.', 'wp-aigent')); ?>
                    <?php $this->number_row('max_message_length', __('Maximum message length', 'wp-aigent'), $settings, 100, 20000, __('Messages longer than this number of characters are rejected before any AI request is made.', 'wp-aigent')); ?>
                    <tr>
                        <th scope="row"><label for="wp-aigent-client-ip-mode"><?php esc_html_e('Client IP deployment mode', 'wp-aigent'); ?></label></th>
                        <td>
                            <select id="wp-aigent-client-ip-mode" name="<?php echo esc_attr(WP_AIGent_Security_Settings::OPTION_NAME); ?>[client_ip_mode]">
                                <option value="origin_server" <?php selected($settings['client_ip_mode'], 'origin_server'); ?>><?php esc_html_e('Origin server — REMOTE_ADDR', 'wp-aigent'); ?></option>
                                <option value="reverse_proxy" <?php selected($settings['client_ip_mode'], 'reverse_proxy'); ?>><?php esc_html_e('Server reverse proxy — X-Forwarded-For', 'wp-aigent'); ?></option>
                                <option value="cloudflare_proxy" <?php selected($settings['client_ip_mode'], 'cloudflare_proxy'); ?>><?php esc_html_e('Cloudflare proxy — CF-Connecting-IP', 'wp-aigent'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Origin server reads REMOTE_ADDR. Server reverse proxy parses X-Forwarded-For from right to left and falls back to X-Real-IP. Cloudflare proxy reads CF-Connecting-IP only from official Cloudflare networks or additional trusted CIDRs.', 'wp-aigent'); ?></p>
                            <p class="description"><?php esc_html_e('If Nginx already restores the real Visitor IP into REMOTE_ADDR, choose Origin server even when Cloudflare is enabled.', 'wp-aigent'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wp-aigent-trusted-proxy-cidrs"><?php esc_html_e('Trusted proxy CIDRs', 'wp-aigent'); ?></label></th>
                        <td>
                            <textarea class="large-text code" rows="5" id="wp-aigent-trusted-proxy-cidrs" name="<?php echo esc_attr(WP_AIGent_Security_Settings::OPTION_NAME); ?>[trusted_proxy_cidrs]"><?php echo esc_textarea((string) $settings['trusted_proxy_cidrs']); ?></textarea>
                            <p class="description"><?php esc_html_e('One IPv4 or IPv6 CIDR per line. Required for Server reverse proxy. Cloudflare mode already includes official Cloudflare ranges; add only an intermediate Nginx, load balancer, or Tunnel address that overwrites CF-Connecting-IP. This field is ignored in Origin server mode.', 'wp-aigent'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function number_row(string $key, string $label, array $settings, int $minimum, int $maximum, string $description): void {
        ?>
        <tr>
            <th scope="row"><label for="wp-aigent-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
            <td>
                <input class="small-text" type="number" id="wp-aigent-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr(WP_AIGent_Security_Settings::OPTION_NAME); ?>[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr((string) $settings[$key]); ?>" min="<?php echo esc_attr((string) $minimum); ?>" max="<?php echo esc_attr((string) $maximum); ?>" required>
                <p class="description"><?php echo esc_html($description); ?></p>
            </td>
        </tr>
        <?php
    }
}
