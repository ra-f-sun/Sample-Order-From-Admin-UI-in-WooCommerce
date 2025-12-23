<?php

/**
 * Plugin Lifecycle Class
 *
 * @package WPHelpZone\WCSO
 */

namespace WPHelpZone\WCSO;

use WPHelpZone\WCSO\WCSO_Analytics_DB;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * WCSO Lifecycle Class
 *
 * Handles plugin activation and deactivation logic.
 */
class WCSO_Lifecycle
{

    /**
     * Plugin activation hook
     *
     * @return void
     */
    public static function activate()
    {
        // Create analytics table.
        WCSO_Analytics_DB::get_instance()->create_table();

        // Flush rewrite rules.
        flush_rewrite_rules();

        // Set activation flag.
        update_option('wcso_activated', true);
        update_option('wcso_activation_time', time());
    }

    /**
     * Plugin deactivation hook
     *
     * @return void
     */
    public static function deactivate()
    {
        // Flush rewrite rules.
        flush_rewrite_rules();

        // Clear any cached data.
        delete_transient('wcso_analytics_cache');

        // Set deactivation flag.
        update_option('wcso_deactivated', true);
        update_option('wcso_deactivation_time', time());
    }
}
