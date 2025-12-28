<?php

/**
 * Admin Class
 *
 * @package WPHelpZone\WCSO
 */

namespace WPHelpZone\WCSO;

use WPHelpZone\WCSO\Abstracts\WCSO_Singleton;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * WCSO Admin Class
 *
 * Handles admin menu, assets, and order list customizations.
 */
class WCSO_Admin extends WCSO_Singleton
{

    /**
     * Initialize the class
     *
     * @return void
     */
    protected function init()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        add_filter('manage_edit-shop_order_columns', array($this, 'add_order_column'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'display_order_column'), 10, 2);
        add_action('restrict_manage_posts', array($this, 'add_order_filter'));
        add_filter('parse_query', array($this, 'filter_orders'));
    }

    /**
     * Create admin menu on sidebar
     *
     * @return void
     */
    public function add_admin_menu()
    {
        add_menu_page(
            __('Sample Orders', 'wc-sample-orders'),
            __('Sample Orders', 'wc-sample-orders'),
            'manage_woocommerce',
            'wc-sample-orders',
            array($this, 'render_order_page'),
            'dashicons-cart',
            56
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_assets($hook)
    {
        // Only load on our main plugin page.
        if ($hook !== 'toplevel_page_wc-sample-orders') {
            return;
        }

        // Get the auto-generated asset file (For react).
        $asset_file = include WCSO_PLUGIN_DIR . 'build/index.asset.php';

        // Enqueue the React App.
        wp_enqueue_script(
            'wcso-react-app',
            WCSO_PLUGIN_URL . 'build/index.js',
            $asset_file['dependencies'],
            $asset_file['version'],
            true
        );

        // Enqueue Styles.
        wp_enqueue_style(
            'wcso-react-style',
            WCSO_PLUGIN_URL . 'build/index.css',
            array('wp-components'),
            $asset_file['version']
        );

        // Site user data (Admin side) to use for billing details.
        $user_query = get_users(
            array(
                'role__in' => array('administrator', 'shop_manager', 'editor', 'author'),
                'fields'   => array('ID', 'display_name', 'user_email'),
            )
        );

        // Tier configuration for approval of sample order.
        $tier_config = WCSO_Settings::get_tier_config();

        // Localize script to talk between js and php.
        wp_localize_script(
            'wcso-react-app',
            'wcsoData',
            array(
                'restUrl'           => rest_url('wcso/v1'),
                'restNonce'         => wp_create_nonce('wp_rest'),

                'currentUserId'     => get_current_user_id(),
                'users'             => $user_query,
                'tierConfig'        => $tier_config,

                // Settings page initial settings.
                'initialSettings'   => array(
                    'barcode_scanner' => get_option('wcso_enable_barcode_scanner', 'no'),
                    'coupon_code'     => get_option('wcso_coupon_code', 'flat100'),
                    'email_logging'   => get_option('wcso_email_logging', '0'),
                    'tiers'           => $tier_config,
                ),

                // For Shipping.
                'countries'         => \WC()->countries->get_countries(),
                'states'            => \WC()->countries->get_states(),
                'baseCountry'       => \WC()->countries->get_base_country(),
                'baseState'         => \WC()->countries->get_base_state(),
                'shippingZones'     => $this->render_shipping_details_data_only(),
            )
        );
    }

    /**
     * Order page container for react
     *
     * @return void
     */
    public function render_order_page()
    {
        if (! current_user_can('manage_woocommerce')) {
            wp_die(__('Unauthorized'));
        }

        // Load template.
        include WCSO_PLUGIN_DIR . 'templates/admin/order-page.php';
    }

    /**
     * Helper: Fetch Shipping Zone's Necessary Data
     *
     * @return array
     */
    public function render_shipping_details_data_only()
    {
        if (! current_user_can('manage_woocommerce')) {
            return array();
        }

        $shipping_zones = \WC_Shipping_Zones::get_zones();
        $all_shipping_zone_with_methods = array();

        foreach ($shipping_zones as $shipping_zone) {
            $current_zone = $this->build_zone_data($shipping_zone);
            $all_shipping_zone_with_methods[] = $current_zone;
        }
        return $all_shipping_zone_with_methods;
    }

    /**
     * Build zone data array with zone information
     * Helper method used by render_shipping_details_data_only()
     *
     * @param array $shipping_zone Shipping zone data from WooCommerce.
     * @return array Formatted zone data with shipping methods.
     */
    private function build_zone_data($shipping_zone)
    {
        $shipping_zone_id   = $shipping_zone['zone_id'];
        $shipping_zone_name = $shipping_zone['zone_name'];
        $zone_locations     = $shipping_zone['zone_locations'];

        $current_zone = array(
            'zone_id'          => $shipping_zone_id,
            'zone_name'        => $shipping_zone_name,
            'zone_locations'   => $zone_locations,
            'shipping_methods' => $this->parse_shipping_methods($shipping_zone['shipping_methods']),
        );

        return $current_zone;
    }

    /**
     * Parse shipping methods and return formatted array
     * Helper method used by build_zone_data()
     *
     * @param array $shipping_methods Array of WooCommerce shipping method objects.
     * @return array Formatted array of shipping methods.
     */
    private function parse_shipping_methods($shipping_methods)
    {
        $formatted_methods = array();

        foreach ($shipping_methods as $shipping_method) {
            $formatted_methods[] = array(
                'id'             => $shipping_method->id,
                'title'          => $shipping_method->method_title,
                'instance_id'    => $shipping_method->instance_id,
                'method_id'      => $shipping_method->id . ':' . $shipping_method->instance_id,
                'instance_title' => $shipping_method->instance_settings['title'],
                'instance_cost'  => $shipping_method->instance_settings['cost'] ?? 0,
                'enabled'        => $shipping_method->enabled,
            );
        }

        return $formatted_methods;
    }

    /**
     * Add sample order column to order list
     *
     * @param array $columns Existing columns.
     * @return array
     */
    public function add_order_column($columns)
    {
        $new = array();
        foreach ($columns as $key => $col) {
            $new[$key] = $col;
            if ($key === 'order_status') {
                $new['sample_order'] = __('Sample', 'wc-sample-orders');
            }
        }
        return $new;
    }

    /**
     * Display sample order column content
     *
     * @param string $column  Column name.
     * @param int    $post_id Post ID.
     * @return void
     */
    public function display_order_column($column, $post_id)
    {
        if ($column !== 'sample_order') {
            return;
        }
        $order = wc_get_order($post_id);
        if ($order && $order->get_meta('_is_sample_order') === 'yes') {
            $tier       = $order->get_meta('_wcso_tier');
            $tier_label = $tier ? strtoupper(str_replace('so', '', $tier)) : 'SAMPLE';
            echo '<span style="background:#46b450;color:#fff;padding:3px 8px;border-radius:3px;font-size:11px;">' . esc_html($tier_label) . '</span>';
        }
    }

    /**
     * Add filter dropdown for sample orders
     *
     * @return void
     */
    public function add_order_filter()
    {
        global $typenow;
        if ($typenow !== 'shop_order') {
            return;
        }
        $selected = isset($_GET['sample_filter']) ? $_GET['sample_filter'] : '';
?>
        <select name="sample_filter">
            <option value="">All Orders</option>
            <option value="yes" <?php selected($selected, 'yes'); ?>>Sample Orders</option>
            <option value="no" <?php selected($selected, 'no'); ?>>Regular Orders</option>
        </select>
<?php
    }

    /**
     * Filter orders by sample order status
     *
     * @param WP_Query $query Query object.
     * @return void
     */
    public function filter_orders($query)
    {
        global $pagenow, $typenow;
        if ($typenow === 'shop_order' && $pagenow === 'edit.php' && isset($_GET['sample_filter']) && $_GET['sample_filter'] !== '') {
            $query->set(
                'meta_query',
                array(
                    array(
                        'key'     => '_is_sample_order',
                        'value'   => $_GET['sample_filter'],
                        'compare' => '=',
                    ),
                )
            );
        }
    }
}
