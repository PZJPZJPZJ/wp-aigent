<?php
/**
 * Plugin Name: WP AIgent
 * Description: AI chatbots, knowledge base Q&A, lead capture, notifications, and low-intrusion browser attribution for WordPress.
 * Version: 2.1.4
 * Requires at least: 6.7
 * Requires PHP: 8.0
 * Author: AzzDev
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-aigent
 */

defined('ABSPATH') || exit;

define('WP_AIGENT_VERSION', get_file_data(__FILE__, ['version' => 'Version'])['version']);
define('WP_AIGENT_FILE', __FILE__);
define('WP_AIGENT_PATH', plugin_dir_path(__FILE__));
define('WP_AIGENT_URL', plugin_dir_url(__FILE__));

add_action('plugins_loaded', function () {
    if (!defined('AI_CHAT_ENCRYPT_KEY')) {
        define('AI_CHAT_ENCRYPT_KEY', wp_salt('secure_auth'));
    }
}, 1);

// Autoload includes
require_once WP_AIGENT_PATH . 'includes/bootstrap/class-installer.php';
require_once WP_AIGENT_PATH . 'includes/bootstrap/class-bootstrap.php';

// Activation / Deactivation
register_activation_hook(__FILE__, [WP_AIGent_Installer::class, 'activate']);
register_deactivation_hook(__FILE__, [WP_AIGent_Installer::class, 'deactivate']);

// Bootstrap
add_action('plugins_loaded', [WP_AIGent_Bootstrap::class, 'init']);
