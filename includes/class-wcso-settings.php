<?php
if (!defined('ABSPATH')) exit;

class WCSO_Settings
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_settings()
    {
        // General
        register_setting('wcso_settings', 'wcso_enable_barcode_scanner');
        register_setting('wcso_settings', 'wcso_coupon_code');

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
    public static function get_tier_config()
    {
        return array(
            't1' => array(
                'name'  => get_option('wcso_t1_name', 'Customer Service Team'),
                'limit' => (int)get_option('wcso_t1_limit', 15), // Default to 15 if not set
                'cc'    => get_option('wcso_t1_cc', ''),
            ),
            't2' => array(
                'name'  => get_option('wcso_t2_name', 'Bren'),
                'limit' => (int)get_option('wcso_t2_limit', 100), // Default to 100
                'email' => get_option('wcso_t2_email', ''),
                'cc'    => get_option('wcso_t2_cc', ''),
            ),
            't3' => array(
                'name'  => get_option('wcso_t3_name', 'Josh'),
                // Tier 3 is anything ABOVE Tier 2, so we technically just need T1 and T2 limits
                // But we store this for completeness in the UI
                'limit' => (int)get_option('wcso_t3_limit', 100),
                'email' => get_option('wcso_t3_email', ''),
                'cc'    => get_option('wcso_t3_cc', ''),
            )
        );
    }
}
