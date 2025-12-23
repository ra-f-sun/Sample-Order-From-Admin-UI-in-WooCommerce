<?php

/**
 * Plugin Name: WooCommerce Sample Orders
 * Plugin URI: https://wphelpzone.com
 * Description: Create sample orders with tiered approval workflow and dynamic settings.
 * Version: 3.2.0
 * Author: Rafsun Jani (WPHelpZone LLC)
 * Author URI: https://wphelpzone.com
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: wc-sample-orders
 *
 * @package WPHelpZone\WCSO
 */

if (! defined('ABSPATH')) {
    exit;
}

// Debug: Check if files exist
$debug_info = array();
$debug_info[] = 'Vendor autoload exists: ' . (file_exists(__DIR__ . '/vendor/autoload.php') ? 'YES' : 'NO');
$debug_info[] = 'Singleton file exists: ' . (file_exists(__DIR__ . '/includes/Abstracts/class-wcso-singleton.php') ? 'YES' : 'NO');
$debug_info[] = 'Old singleton exists: ' . (file_exists(__DIR__ . '/includes/abstract-wcso-singleton.php') ? 'YES' : 'NO');

// Log debug info
error_log('WCSO Debug: ' . implode(' | ', $debug_info));

// Always load the singleton file directly first
$singleton_path = __DIR__ . '/includes/Abstracts/class-wcso-singleton.php';
if (file_exists($singleton_path)) {
    require_once $singleton_path;
    error_log('WCSO: Loaded singleton directly');
}

// Then load composer autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
    error_log('WCSO: Loaded composer autoloader');
}

// Manually load all other classes
$class_files = array(
    __DIR__ . '/includes/class-wcso-settings.php',
    __DIR__ . '/includes/class-wcso-admin.php',
    __DIR__ . '/includes/class-wcso-ajax.php',
    __DIR__ . '/includes/class-wcso-approval.php',
    __DIR__ . '/includes/class-wcso-email-handler.php',
    __DIR__ . '/includes/class-wcso-order-customizer.php',
    __DIR__ . '/includes/class-wcso-analytics-db.php',
    __DIR__ . '/includes/class-wcso-analytics-api.php',
    __DIR__ . '/includes/class-wcso-lifecycle.php',
);

foreach ($class_files as $file) {
    if (file_exists($file)) {
        require_once $file;
    }
}

// Check if class exists now
error_log('WCSO: Singleton class exists: ' . (class_exists('WPHelpZone\WCSO\Abstracts\WCSO_Singleton') ? 'YES' : 'NO'));

define('WCSO_VERSION', '3.2.0');
define('WCSO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCSO_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main Plugin Class
 */
class WC_Sample_Orders extends \WPHelpZone\WCSO\Abstracts\WCSO_Singleton
{
    /**
     * Initialize the plugin
     *
     * @return void
     */
    protected function init()
    {
        if (! class_exists('WooCommerce')) {
            add_action(
                'admin_notices',
                function () {
                    echo '<div class="notice notice-error"><p><strong>WCSO</strong> requires WooCommerce.</p></div>';
                }
            );
            return;
        }
        add_action('init', array($this, 'initialize_classes'));
    }

    /**
     * Initialize all plugin classes
     *
     * @return void
     */
    public function initialize_classes()
    {
        if (is_admin()) {
            \WPHelpZone\WCSO\WCSO_Settings::get_instance();
            \WPHelpZone\WCSO\WCSO_Admin::get_instance();
            \WPHelpZone\WCSO\WCSO_Order_Customizer::get_instance();
        }
        \WPHelpZone\WCSO\WCSO_Ajax::get_instance();
        \WPHelpZone\WCSO\WCSO_Email_Handler::get_instance();
        \WPHelpZone\WCSO\WCSO_Approval::get_instance();
        \WPHelpZone\WCSO\WCSO_Analytics_DB::get_instance();
        \WPHelpZone\WCSO\WCSO_Analytics_API::get_instance();
    }
}

// Initialize the plugin.
add_action('plugins_loaded', array('WC_Sample_Orders', 'get_instance'));

// Register activation hook.
register_activation_hook(__FILE__, array('\WPHelpZone\WCSO\WCSO_Lifecycle', 'activate'));

// Register deactivation hook.
register_deactivation_hook(__FILE__, array('\WPHelpZone\WCSO\WCSO_Lifecycle', 'deactivate'));
