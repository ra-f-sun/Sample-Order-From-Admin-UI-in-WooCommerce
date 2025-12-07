<?php
if (!defined('ABSPATH')) exit;

class WCSO_Settings {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_settings() {
        // General
        register_setting('wcso_settings', 'wcso_enable_barcode_scanner');
        
        // Tier 1 (<= 15)
        register_setting('wcso_settings', 'wcso_t1_name');
        register_setting('wcso_settings', 'wcso_t1_cc');
        
        // Tier 2 (15 - 100)
        register_setting('wcso_settings', 'wcso_t2_name');
        register_setting('wcso_settings', 'wcso_t2_email');
        register_setting('wcso_settings', 'wcso_t2_cc');
        
        // Tier 3 (> 100)
        register_setting('wcso_settings', 'wcso_t3_name');
        register_setting('wcso_settings', 'wcso_t3_email');
        register_setting('wcso_settings', 'wcso_t3_cc');

        // Email Appearance
        register_setting('wcso_settings', 'wcso_email_from_name');
        register_setting('wcso_settings', 'wcso_email_from_email');
        register_setting('wcso_settings', 'wcso_email_logging');
    }

    // Helper to get all config in one array for JS/PHP
    public static function get_tier_config() {
        return array(
            't1' => array(
                'name'  => get_option('wcso_t1_name', 'Customer Service Team'),
                'cc'    => get_option('wcso_t1_cc', ''),
            ),
            't2' => array(
                'name'  => get_option('wcso_t2_name', 'Bren'),
                'email' => get_option('wcso_t2_email', ''),
                'cc'    => get_option('wcso_t2_cc', ''),
            ),
            't3' => array(
                'name'  => get_option('wcso_t3_name', 'Josh'),
                'email' => get_option('wcso_t3_email', ''),
                'cc'    => get_option('wcso_t3_cc', ''),
            )
        );
    }
}