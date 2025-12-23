<?php

/**
 * Plugin Constants
 *
 * @package WPHelpZone\WCSO
 */

if (! defined('ABSPATH')) {
    exit;
}

// Plugin version.
define('WCSO_VERSION', '3.2.0');

// Plugin directory path.
define('WCSO_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__)));

// Plugin directory URL.
define('WCSO_PLUGIN_URL', plugin_dir_url(dirname(__FILE__)));

// Plugin file path.
define('WCSO_PLUGIN_FILE', dirname(dirname(__FILE__)) . '/woocommerce-sample-orders.php');

// Plugin basename.
define('WCSO_PLUGIN_BASENAME', plugin_basename(WCSO_PLUGIN_FILE));
