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

        // Order list customization
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
            array($this, 'render_order_page'),
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

    public function enqueue_assets($hook)
    {
        if ($hook !== 'toplevel_page_wc-sample-orders' && $hook !== 'sample-orders_page_wc-sample-orders-settings') return;

        // CSS
        wp_enqueue_style(
            'wcso-admin-style',
            WCSO_PLUGIN_URL . 'assets/css/admin-style.css',
            array(),
            WCSO_VERSION
        );

        // JS
        wp_enqueue_script(
            'wcso-admin-script',
            WCSO_PLUGIN_URL . 'assets/js/admin-script.js',
            array('jquery'),
            WCSO_VERSION,
            true
        );

        // Get Tier Config from Settings Helper
        $tier_config = WCSO_Settings::get_tier_config();

        // Localize data
        wp_localize_script('wcso-admin-script', 'wcsoData', array(
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'searchNonce'  => wp_create_nonce('wcso_search'),
            'cacheNonce'   => wp_create_nonce('wcso_cache'),
            'orderNonce'   => wp_create_nonce('wcso_create_order'),
            'enableScanner' => get_option('wcso_enable_barcode_scanner', '0'),
            'currentUser'  => wp_get_current_user()->user_login,
            'countries'    => WC()->countries->get_countries(),
            'states'       => WC()->countries->get_states(),
            'baseCountry'  => WC()->countries->get_base_country(),
            'baseState'    => WC()->countries->get_base_state(),
            'shippingZones' => $this->render_shipping_details(),
            'tierConfig'   => $tier_config // <--- NEW: Tier Configuration for JS
        ));
    }

    public function render_shipping_details()
    {
        if (!current_user_can('manage_woocommerce')) wp_die(__('You do not have sufficient permissions.'));

        $shipping_zones = WC_Shipping_Zones::get_zones();
        $all_shipping_zone_with_methods = array();

        foreach ($shipping_zones as $shipping_zone) {
            $shipping_zone_id = $shipping_zone['zone_id'];
            $shipping_zone_name = $shipping_zone['zone_name'];
            $shipping_zone_formatted_location = $shipping_zone['formatted_zone_location'];
            $zone_locations = $shipping_zone['zone_locations'];

            $current_zone = array(
                'zone_id' => $shipping_zone_id,
                'zone_name' => $shipping_zone_name,
                'formatted_location' => $shipping_zone_formatted_location,
                'zone_locations' => $zone_locations,
                'shipping_methods' => array()
            );

            $shipping_methods = $shipping_zone['shipping_methods'];

            foreach ($shipping_methods as $shipping_method) {
                $shipping_method_id = $shipping_method->id;
                $shipping_method_title = $shipping_method->method_title;
                $shipping_method_instance_id = $shipping_method->instance_id;
                $shipping_method_instance_title = $shipping_method->instance_settings['title'];
                $shipping_method_instance_cost = $shipping_method->instance_settings['cost'] ?? 0;

                $current_zone['shipping_methods'][] = array(
                    'id' => $shipping_method_id,
                    'title' => $shipping_method_title,
                    'instance_id' => $shipping_method_instance_id,
                    'method_id' => $shipping_method_id . ':' . $shipping_method_instance_id,
                    'instance_title' => $shipping_method_instance_title,
                    'instance_cost' => $shipping_method_instance_cost,
                    'enabled' => $shipping_method->enabled,
                );
            }
            $all_shipping_zone_with_methods[] = $current_zone;
        }
        return $all_shipping_zone_with_methods;
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_woocommerce')) wp_die(__('You do not have sufficient permissions.'));

        // Clear log action
        if (isset($_POST['wcso_clear_log']) && check_admin_referer('wcso_clear_log_nonce')) {
            WCSO_Email_Handler::get_instance()->clear_email_log();
            echo '<div class="notice notice-success"><p>Email log cleared!</p></div>';
        }
?>
        <div class="wrap">
            <h1>Sample Orders Settings v2.0</h1>

            <form method="post" action="options.php">
                <?php settings_fields('wcso_settings'); ?>

                <h2 class="title">Tier Configuration</h2>
                <p class="description">Configure the approval workflow based on order value.</p>

                <table class="form-table" style="background: #fff; padding: 10px; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <tr style="background:#f0f0f1">
                        <th colspan="2" style="padding:10px;"><strong>Tier 1 (Total &le; 15) - Auto Approved</strong></th>
                    </tr>
                    <tr>
                        <th scope="row">Owner/Approver Label</th>
                        <td><input type="text" name="wcso_t1_name" value="<?php echo esc_attr(get_option('wcso_t1_name', 'Customer Service Team')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">CC Emails</th>
                        <td><textarea name="wcso_t1_cc" class="large-text" rows="2" placeholder="email@example.com, another@example.com"><?php echo esc_textarea(get_option('wcso_t1_cc')); ?></textarea></td>
                    </tr>

                    <tr style="background:#f0f0f1">
                        <th colspan="2" style="padding:10px;"><strong>Tier 2 (15 < Total &le; 100) - Requires Approval</strong>
                        </th>
                    </tr>
                    <tr>
                        <th scope="row">Approver Name</th>
                        <td><input type="text" name="wcso_t2_name" value="<?php echo esc_attr(get_option('wcso_t2_name', 'Bren')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">Approver Email</th>
                        <td>
                            <input type="email" name="wcso_t2_email" value="<?php echo esc_attr(get_option('wcso_t2_email')); ?>" class="regular-text">
                            <p class="description">The "Action Required" email with the approval link sends here.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">CC Emails</th>
                        <td><textarea name="wcso_t2_cc" class="large-text" rows="2"><?php echo esc_textarea(get_option('wcso_t2_cc')); ?></textarea></td>
                    </tr>

                    <tr style="background:#f0f0f1">
                        <th colspan="2" style="padding:10px;"><strong>Tier 3 (Total > 100) - Requires Tier 2 & 3 Approval</strong></th>
                    </tr>
                    <tr>
                        <th scope="row">Approver Name</th>
                        <td><input type="text" name="wcso_t3_name" value="<?php echo esc_attr(get_option('wcso_t3_name', 'Josh')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">Approver Email</th>
                        <td><input type="email" name="wcso_t3_email" value="<?php echo esc_attr(get_option('wcso_t3_email')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">CC Emails</th>
                        <td><textarea name="wcso_t3_cc" class="large-text" rows="2"><?php echo esc_textarea(get_option('wcso_t3_cc')); ?></textarea></td>
                    </tr>
                </table>

                <h2 class="title">General Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Barcode Scanner</th>
                        <td>
                            <label><input type="checkbox" name="wcso_enable_barcode_scanner" value="1" <?php checked(get_option('wcso_enable_barcode_scanner'), '1'); ?>> Enable Barcode2Win integration</label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Email Logging</th>
                        <td>
                            <label><input type="checkbox" name="wcso_email_logging" value="1" <?php checked(get_option('wcso_email_logging'), '1'); ?>> Enable logging (for testing)</label>
                            <p class="description">Logs all emails to a file in the plugin directory. Use this to find Approval Links during testing.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">From Name</th>
                        <td><input type="text" name="wcso_email_from_name" value="<?php echo esc_attr(get_option('wcso_email_from_name', get_bloginfo('name'))); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row">From Email</th>
                        <td><input type="email" name="wcso_email_from_email" value="<?php echo esc_attr(get_option('wcso_email_from_email', get_option('admin_email'))); ?>" class="regular-text"></td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <hr>
            <h2 class="title">Email Log (Testing Console)</h2>
            <div class="card" style="max-width: none;">
                <p>Use this log to retrieve the "Approve/Reject" links if you do not have a real mail server set up.</p>
                <form method="post" style="margin-bottom: 10px;">
                    <?php wp_nonce_field('wcso_clear_log_nonce'); ?>
                    <button type="submit" name="wcso_clear_log" class="button">Clear Log</button>
                </form>
                <div style="background: #f5f5f5; padding: 15px; border: 1px solid #ddd; border-radius: 4px; max-height: 400px; overflow-y: auto;">
                    <pre style="margin: 0; font-family: monospace; font-size: 12px; white-space: pre-wrap; word-wrap: break-word;">
                        <?php
                        echo esc_html(WCSO_Email_Handler::get_instance()->get_email_log());
                        ?>
                    </pre>
                </div>
            </div>
        </div>
    <?php
    }

    public function render_order_page()
    {
        if (!current_user_can('manage_woocommerce')) wp_die(__('You do not have sufficient permissions.'));
        $current_user = wp_get_current_user();
        $enable_scanner = get_option('wcso_enable_barcode_scanner', '0');
    ?>
        <div class="wrap wcso-wrap">
            <div class="wcso-header">
                <h1 class="wcso-page-title"><span class="dashicons dashicons-cart"></span> <?php _e('Create Sample Order', 'wc-sample-orders'); ?></h1>
                <div id="cache-status" class="wcso-cache-badge"><span id="cache-info">Loading...</span></div>
            </div>

            <button type="button" id="refresh-cache" class="wcso-refresh-btn"><span class="dashicons dashicons-update"></span> Refresh Cache</button>
            <span id="cache-loading" style="display:none; margin-left:10px;"><span class="spinner is-active"></span></span>

            <form id="wcso-order-form" method="post">
                <?php wp_nonce_field('wcso_create_order', 'wcso_nonce'); ?>

                <div class="wcso-section">
                    <div class="wcso-grid">
                        <div class="wcso-card">
                            <h3 class="wcso-section-title"><span class="dashicons dashicons-businessman"></span> Billing Information</h3>
                            <div class="wcso-form-group">
                                <label for="billing_user_id">Billed User</label>
                                <select id="billing_user_id" class="wcso-input">
                                    <?php
                                    $eligible = get_users(array(
                                        'role__in' => array('administrator', 'shop_manager'),
                                        'fields'   => array('ID', 'display_name', 'user_login'),
                                        'orderby'  => 'display_name',
                                        'order'    => 'ASC',
                                    ));
                                    foreach ($eligible as $u) {
                                        printf(
                                            '<option value="%1$d" %4$s>%2$s (%3$s)</option>',
                                            $u->ID,
                                            esc_html($u->display_name),
                                            esc_html($u->user_login),
                                            selected($u->ID, $current_user->ID, false)
                                        );
                                    }
                                    ?>
                                </select>
                                <p class="description">Defaults to current user.</p>
                            </div>
                        </div>

                        <div class="wcso-card">
                            <h3 class="wcso-section-title"><span class="dashicons dashicons-location"></span> Shipping Information</h3>

                            <div class="wcso-grid" style="grid-template-columns: 1fr 1fr; gap: var(--wcso-spacing-md);">
                                <div class="wcso-form-group">
                                    <label for="shipping_first_name">First name <span class="required">*</span></label>
                                    <input type="text" id="shipping_first_name" class="wcso-input" required>
                                </div>
                                <div class="wcso-form-group">
                                    <label for="shipping_last_name">Last name <span class="required">*</span></label>
                                    <input type="text" id="shipping_last_name" class="wcso-input" required>
                                </div>
                            </div>

                            <div class="wcso-form-group">
                                <label for="shipping_company">Company (optional)</label>
                                <input type="text" id="shipping_company" class="wcso-input">
                            </div>

                            <div class="wcso-form-group">
                                <label for="shipping_country">Country / Region <span class="required">*</span></label>
                                <select id="shipping_country" class="wcso-input"></select>
                            </div>

                            <div class="wcso-form-group">
                                <label for="shipping_address_1">Street address <span class="required">*</span></label>
                                <input type="text" id="shipping_address_1" class="wcso-input" placeholder="House number and street name" required>
                            </div>

                            <div class="wcso-form-group">
                                <label for="shipping_address_2">Apartment, suite, unit, etc. (optional)</label>
                                <input type="text" id="shipping_address_2" class="wcso-input">
                            </div>

                            <div class="wcso-form-group">
                                <label for="shipping_city">Town / City <span class="required">*</span></label>
                                <input type="text" id="shipping_city" class="wcso-input" required>
                            </div>

                            <div class="wcso-grid" style="grid-template-columns: 1fr 1fr; gap: var(--wcso-spacing-md);">
                                <div class="wcso-form-group">
                                    <label for="shipping_state">State / District <span class="required">*</span></label>
                                    <select id="shipping_state" class="wcso-input"></select>
                                </div>
                                <div class="wcso-form-group">
                                    <label for="shipping_postcode">Postcode / ZIP (optional)</label>
                                    <input type="text" id="shipping_postcode" class="wcso-input">
                                </div>
                            </div>

                            <div class="wcso-grid" style="grid-template-columns: 1fr 1fr; gap: var(--wcso-spacing-md);">
                                <div class="wcso-form-group">
                                    <label for="shipping_phone">Phone (optional)</label>
                                    <input type="text" id="shipping_phone" class="wcso-input">
                                </div>
                                <div class="wcso-form-group">
                                    <label for="shipping_email">Email address (optional)</label>
                                    <input type="email" id="shipping_email" class="wcso-input" placeholder="recipient@example.com">
                                </div>
                            </div>

                            <div class="wcso-form-group">
                                <label for="shipping_method">Shipping Method <span class="required">*</span></label>
                                <select id="shipping_method" class="wcso-input"></select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="wcso-section">
                    <div class="wcso-card wcso-card-prominent">
                        <h3 class="wcso-section-title"><span class="dashicons dashicons-search"></span> Add Products</h3>

                        <div class="wcso-search-container">
                            <div class="wcso-form-group">
                                <label for="product_search">Search Products <span id="search-mode" class="wcso-status-badge"></span></label>
                                <div class="wcso-search-input-wrapper">
                                    <input type="text" id="product_search" class="wcso-input wcso-input-search" placeholder="Type product name, ID or SKU...">
                                    <span class="wcso-search-icon dashicons dashicons-search"></span>
                                </div>
                                <div id="product_dropdown" class="wcso-dropdown"></div>
                            </div>

                            <?php if ($enable_scanner === '1'): ?>
                                <div class="wcso-or-divider">OR</div>
                                <div class="wcso-form-group">
                                    <label for="barcode_input"><span class="dashicons dashicons-smartphone"></span> Scan Barcode</label>
                                    <div class="wcso-search-input-wrapper">
                                        <input type="text" id="barcode_input" class="wcso-input wcso-input-search" placeholder="Click here to scan..." autocomplete="off">
                                        <span class="wcso-search-icon dashicons dashicons-camera"></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="wcso-section">
                    <div class="wcso-card">
                        <h3 class="wcso-section-title"><span class="dashicons dashicons-clipboard"></span> Selected Products <span id="products-count" class="wcso-count-badge"></span></h3>
                        <div id="selected_products_table"></div>
                        <input type="hidden" id="selected_products_data" name="selected_products_data">
                    </div>
                </div>

                <div class="wcso-section">
                    <div class="wcso-grid wcso-grid-40-60">
                        <div class="wcso-card">
                            <h3 class="wcso-section-title"><span class="dashicons dashicons-yes-alt"></span> Approval & Category</h3>

                            <div class="wcso-form-group">
                                <label>Owner / Approver Status</label>
                                <div id="wcso-approval-status-box" style="background: #f6f7f7; padding: 10px; border: 1px solid #c3c4c7; border-left: 4px solid #2271b1; border-radius: 4px;">
                                    <span class="dashicons dashicons-info" style="color:#2271b1; vertical-align:middle;"></span>
                                    <span id="wcso-approval-text" style="font-weight:600;">Add products to calculate...</span>
                                </div>
                                <p class="description">The system will automatically assign the approver based on the order total.</p>
                            </div>

                            <div class="wcso-form-group">
                                <label for="sample_category">Sample Category <span class="required">*</span></label>
                                <select id="sample_category" class="wcso-input">
                                    <option value="Customer Service">Customer Service</option>
                                    <option value="Sales Reps">Sales Reps</option>
                                    <option value="Promotions">Promotions</option>
                                </select>
                            </div>
                        </div>

                        <div class="wcso-card">
                            <h3 class="wcso-section-title"><span class="dashicons dashicons-edit"></span> Notes</h3>
                            <div class="wcso-form-group">
                                <label for="order_note">Order Note</label>
                                <textarea id="order_note" class="wcso-textarea" rows="4" placeholder="Add any special instructions or notes..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="wcso-section wcso-submit-section">
                    <button type="submit" class="wcso-btn wcso-btn-primary wcso-btn-large" id="submit_order"><span class="dashicons dashicons-cart"></span> Create Sample Order</button>
                    <span id="order_loading" style="display:none; margin-left:15px;"><span class="spinner is-active"></span> Creating order...</span>
                </div>
            </form>

            <div id="order_message"></div>
        </div>

        <div id="product_modal" class="wcso-modal">
            <div class="wcso-modal-overlay"></div>
            <div class="wcso-modal-content">
                <div class="wcso-modal-header">
                    <h2>Multiple Products Found</h2>
                    <button type="button" id="close_modal" class="wcso-modal-close">&times;</button>
                </div>
                <p id="scanned_code" class="wcso-scanned-code"></p>
                <div id="modal_products"></div>
            </div>
        </div>
    <?php
    }

    // Orders list custom column/filter
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
