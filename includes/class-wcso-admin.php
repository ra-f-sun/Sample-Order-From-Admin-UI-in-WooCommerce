<?php

/**
 * Admin Pages Handler
 */
if (!defined('ABSPATH')) exit;

class WCSO_Admin
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_menu', array($this, 'add_settings_page'), 99);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        // Keep existing order column filters if you want them visible in the main order list
        add_filter('manage_edit-shop_order_columns', array($this, 'add_order_column'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'display_order_column'), 10, 2);
        add_action('restrict_manage_posts', array($this, 'add_order_filter'));
        add_filter('parse_query', array($this, 'filter_orders'));
    }

    public function add_admin_menu()
    {
        add_menu_page(
            __('Sample Orders', 'wc-sample-orders'),
            __('Sample Orders', 'wc-sample-orders'),
            'manage_woocommerce',
            'wc-sample-orders',
            array($this, 'render_order_page'), // Calls the React container
            'dashicons-cart',
            56
        );
    }

    public function add_settings_page()
    {
        add_submenu_page(
            'wc-sample-orders',
            __('Settings', 'wc-sample-orders'),
            __('Settings', 'wc-sample-orders'),
            'manage_woocommerce',
            'wc-sample-orders-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * NEW: Load the React App Build Files
     */
    public function enqueue_assets($hook)
    {
        // Only load on our main plugin page
        if ($hook !== 'toplevel_page_wc-sample-orders') return;

        // 1. Get the auto-generated asset file
        $asset_file = include(WCSO_PLUGIN_DIR . 'build/index.asset.php');

        // 2. Enqueue the React App
        wp_enqueue_script(
            'wcso-react-app',
            WCSO_PLUGIN_URL . 'build/index.js',
            $asset_file['dependencies'],
            $asset_file['version'],
            true
        );

        // 3. Enqueue Styles
        wp_enqueue_style(
            'wcso-react-style',
            WCSO_PLUGIN_URL . 'build/index.css',
            array('wp-components'),
            $asset_file['version']
        );

        // 4. Pass Data (The Bridge)
        $user_query = get_users(array(
            'role__in' => array('administrator', 'shop_manager', 'editor', 'author'),
            'fields'   => array('ID', 'display_name', 'user_email')
        ));

        // Get the tier config once to use in multiple places
        $tier_config = WCSO_Settings::get_tier_config();

        wp_localize_script('wcso-react-app', 'wcsoData', array(
            'ajaxUrl'          => admin_url('admin-ajax.php'),
            'createOrderNonce' => wp_create_nonce('wcso_create_order'),
            'searchNonce'      => wp_create_nonce('wcso_search'),

            // *** NEW: Add this line ***
            'cacheNonce'       => wp_create_nonce('wcso_cache'),

            'saveSettingsNonce' => wp_create_nonce('wcso_save_settings'),
            // ... (keep the rest of the array exactly as it was) ...
            'currentUserId'    => get_current_user_id(),
            'users'            => $user_query,
            'tierConfig'       => $tier_config,
            'initialSettings'  => array(
                'barcode_scanner' => get_option('wcso_enable_barcode_scanner', 'no'),
                'coupon_code'     => get_option('wcso_coupon_code', 'flat100'),
                'email_logging'   => get_option('wcso_email_logging', '0'),
                'tiers'           => $tier_config
            ),
            'countries'        => WC()->countries->get_countries(),
            'states'           => WC()->countries->get_states(),
            'baseCountry'      => WC()->countries->get_base_country(),
            'baseState'        => WC()->countries->get_base_state(),
            'shippingZones'    => $this->render_shipping_details_data_only()
        ));
    }

    /**
     * NEW: React Container
     * This replaces the old HTML form.
     */
    public function render_order_page()
    {
        if (!current_user_can('manage_woocommerce')) wp_die(__('Unauthorized'));
?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Sample Orders v3.0</h1>

            <div id="wcso-root"></div>
        </div>
    <?php
    }

    /**
     * Helper: Fetch Shipping Zones for React (Data Only)
     */
    public function render_shipping_details_data_only()
    {
        if (!current_user_can('manage_woocommerce')) return [];

        $shipping_zones = WC_Shipping_Zones::get_zones();
        $all_shipping_zone_with_methods = array();

        foreach ($shipping_zones as $shipping_zone) {
            $shipping_zone_id = $shipping_zone['zone_id'];
            $shipping_zone_name = $shipping_zone['zone_name'];
            $zone_locations = $shipping_zone['zone_locations'];

            $current_zone = array(
                'zone_id' => $shipping_zone_id,
                'zone_name' => $shipping_zone_name,
                'zone_locations' => $zone_locations,
                'shipping_methods' => array()
            );

            $shipping_methods = $shipping_zone['shipping_methods'];

            foreach ($shipping_methods as $shipping_method) {
                $current_zone['shipping_methods'][] = array(
                    'id' => $shipping_method->id,
                    'title' => $shipping_method->method_title,
                    'instance_id' => $shipping_method->instance_id,
                    'method_id' => $shipping_method->id . ':' . $shipping_method->instance_id,
                    'instance_title' => $shipping_method->instance_settings['title'],
                    'instance_cost' => $shipping_method->instance_settings['cost'] ?? 0,
                    'enabled' => $shipping_method->enabled,
                );
            }
            $all_shipping_zone_with_methods[] = $current_zone;
        }
        return $all_shipping_zone_with_methods;
    }

    // Keep the legacy Settings Page renderer if you want the "Old" settings page to still work separately
    // OR we can move settings to React later. For now, let's keep it.
    public function render_settings_page()
    {
        // ... (Keep your existing render_settings_page code here if you want) ...
        // For brevity, I'm assuming you have the code from previous steps. 
        // If not, ask and I will paste it.
        if (!current_user_can('manage_woocommerce')) wp_die(__('You do not have sufficient permissions.'));
        // ... include the settings form code ...
        echo '<h2>Standard Settings Page</h2>';
        echo '<form method="post" action="options.php">';
        settings_fields('wcso_settings');
        do_settings_sections('wcso_settings'); // Adjust as needed based on your registered sections
        submit_button();
        echo '</form>';
    }

    // ... Keep add_order_column, display_order_column, add_order_filter, filter_orders ...
    public function add_order_column($columns)
    {
        $new = array();
        foreach ($columns as $key => $col) {
            $new[$key] = $col;
            if ($key === 'order_status') $new['sample_order'] = __('Sample', 'wc-sample-orders');
        }
        return $new;
    }

    public function display_order_column($column, $post_id)
    {
        if ($column !== 'sample_order') return;
        $order = wc_get_order($post_id);
        if ($order && $order->get_meta('_is_sample_order') === 'yes') {
            $tier = $order->get_meta('_wcso_tier');
            $tier_label = $tier ? strtoupper(str_replace('so', '', $tier)) : 'SAMPLE';
            echo '<span style="background:#46b450;color:#fff;padding:3px 8px;border-radius:3px;font-size:11px;">' . esc_html($tier_label) . '</span>';
        }
    }

    public function add_order_filter()
    {
        global $typenow;
        if ($typenow !== 'shop_order') return;
        $selected = isset($_GET['sample_filter']) ? $_GET['sample_filter'] : '';
    ?>
        <select name="sample_filter">
            <option value="">All Orders</option>
            <option value="yes" <?php selected($selected, 'yes'); ?>>Sample Orders</option>
            <option value="no" <?php selected($selected, 'no');  ?>>Regular Orders</option>
        </select>
<?php
    }

    public function filter_orders($query)
    {
        global $pagenow, $typenow;
        if ($typenow === 'shop_order' && $pagenow === 'edit.php' && isset($_GET['sample_filter']) && $_GET['sample_filter'] !== '') {
            $query->set('meta_query', array(
                array('key' => '_is_sample_order', 'value' => $_GET['sample_filter'], 'compare' => '=')
            ));
        }
    }
}
