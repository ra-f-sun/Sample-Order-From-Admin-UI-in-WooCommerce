<?php

/**
 * Settings Class
 *
 * @package WPHelpZone\WCSO
 */

namespace WPHelpZone\WCSO;

use WPHelpZone\WCSO\Abstracts\WCSO_Singleton;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * WCSO Settings Class
 *
 * Handles plugin settings registration and retrieval.
 */
class WCSO_Settings extends WCSO_Singleton
{

    /**
     * Initialize the class
     *
     * @return void
     */
    protected function init()
    {
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * Register all plugin settings
     *
     * @return void
     */
    public function register_settings()
    {
        // General.
        register_setting('wcso_settings', 'wcso_enable_barcode_scanner');
        register_setting('wcso_settings', 'wcso_coupon_code');

        // Tier 1 (<= 15).
        register_setting('wcso_settings', 'wcso_t1_name');
        register_setting('wcso_settings', 'wcso_t1_cc');

        // Tier 2 (15 - 100).
        register_setting('wcso_settings', 'wcso_t2_name');
        register_setting('wcso_settings', 'wcso_t2_email');
        register_setting('wcso_settings', 'wcso_t2_cc');

        // Tier 3 (> 100).
        register_setting('wcso_settings', 'wcso_t3_name');
        register_setting('wcso_settings', 'wcso_t3_email');
        register_setting('wcso_settings', 'wcso_t3_cc');

        // Email Appearance.
        register_setting('wcso_settings', 'wcso_email_from_name');
        register_setting('wcso_settings', 'wcso_email_from_email');
        register_setting('wcso_settings', 'wcso_email_logging');
    }

    /**
     * Get tier configuration
     *
     * Helper to get all config in one array for JS/PHP.
     *
     * @return array
     */
    public static function get_tier_config()
    {
        return array(
            't1' => array(
                'name'  => get_option('wcso_t1_name', 'Customer Service Team'),
                'limit' => (int) get_option('wcso_t1_limit', 15),
                'cc'    => get_option('wcso_t1_cc', ''),
            ),
            't2' => array(
                'name'  => get_option('wcso_t2_name', 'Bren'),
                'limit' => (int) get_option('wcso_t2_limit', 100),
                'email' => get_option('wcso_t2_email', ''),
                'cc'    => get_option('wcso_t2_cc', ''),
            ),
            't3' => array(
                'name'  => get_option('wcso_t3_name', 'Josh'),
                'limit' => (int) get_option('wcso_t3_limit', 100),
                'email' => get_option('wcso_t3_email', ''),
                'cc'    => get_option('wcso_t3_cc', ''),
            ),
        );
    }
}
