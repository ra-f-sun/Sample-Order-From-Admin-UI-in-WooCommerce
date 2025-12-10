<?php

/**
 * Plugin Name: WooCommerce Sample Orders
 * Plugin URI: https://wphelpzone.com
 * Description: Create sample orders with tiered approval workflow and dynamic settings.
 * Version: 2.0.0
 * Author: Rafsun Jani (WPHelpZone LLC)
 * Author URI: https://wphelpzone.com
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: wc-sample-orders
 */

if (!defined('ABSPATH')) exit;

define('WCSO_VERSION', '2.0.0');
define('WCSO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCSO_PLUGIN_URL', plugin_dir_url(__FILE__));

class WC_Sample_Orders
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct()
    {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function () {
                echo '<div class="notice notice-error"><p><strong>WCSO</strong> requires WooCommerce.</p></div>';
            });
            return;
        }

        $this->load_dependencies();
        add_action('init', array($this, 'init'));
    }

    private function load_dependencies()
    {
        // Core Logic
        require_once WCSO_PLUGIN_DIR . 'includes/class-wcso-settings.php'; // New: Handles Settings
        require_once WCSO_PLUGIN_DIR . 'includes/class-wcso-approval.php'; // New: Handles Links/State

        // Controllers
        require_once WCSO_PLUGIN_DIR . 'includes/class-wcso-admin.php';
        require_once WCSO_PLUGIN_DIR . 'includes/class-wcso-ajax.php';
        require_once WCSO_PLUGIN_DIR . 'includes/class-wcso-order-customizer.php';
        require_once WCSO_PLUGIN_DIR . 'includes/class-wcso-email-handler.php';
    }

    public function init()
    {
        if (is_admin()) {
            WCSO_Settings::get_instance();
            WCSO_Admin::get_instance();
            WCSO_Order_Customizer::get_instance();
        }

        WCSO_Ajax::get_instance();
        WCSO_Email_Handler::get_instance();
        WCSO_Approval::get_instance(); // Must run on frontend too for link clicking
    }
}

add_action('plugins_loaded', array('WC_Sample_Orders', 'get_instance'));
