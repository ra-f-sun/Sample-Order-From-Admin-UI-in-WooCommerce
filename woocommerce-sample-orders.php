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

// Load Composer autoloader.
require_once __DIR__ . '/vendor/autoload.php';

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
