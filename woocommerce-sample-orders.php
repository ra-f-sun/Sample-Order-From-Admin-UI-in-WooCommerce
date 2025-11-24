<?php
/**
 * Plugin Name: WooCommerce Sample Orders
 * Plugin URI: https://wphelpzone.com
 * Description: Create sample orders with optional barcode scanning and local caching
 * Version: 1.2.0
 * Author: WPHelpZone LLC
 * Author URI: https://wphelpzone.com
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: wc-sample-orders
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('WCSO_VERSION', '1.2.0');
define('WCSO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCSO_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WCSO_PLUGIN_FILE', __FILE__);

/**
 * Main Plugin Class
 */
class WC_Sample_Orders {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Load dependencies
        $this->load_dependencies();
        
        // Initialize
        add_action('init', array($this, 'init'));
    }
    
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><strong>WooCommerce Sample Orders</strong> requires WooCommerce to be installed and activated.</p>
        </div>
        <?php
    }
    
    private function load_dependencies() {
        require_once WCSO_PLUGIN_DIR . 'includes/class-wcso-admin.php';
        require_once WCSO_PLUGIN_DIR . 'includes/class-wcso-ajax.php';
        require_once WCSO_PLUGIN_DIR . 'includes/class-wcso-order-customizer.php';
        require_once WCSO_PLUGIN_DIR . 'includes/class-wcso-email-handler.php';
    }
    
    public function init() {
        // Initialize admin
        if (is_admin()) {
            WCSO_Admin::get_instance();
            WCSO_Order_Customizer::get_instance();
        }
        
        // Initialize AJAX handlers
        WCSO_Ajax::get_instance();
        
        // Initialize email handler
        WCSO_Email_Handler::get_instance();
    }
}

// Initialize plugin
add_action('plugins_loaded', array('WC_Sample_Orders', 'get_instance'));