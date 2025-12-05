<?php
/**
 * Admin Pages Handler
 */
if (!defined('ABSPATH')) exit;

class WCSO_Admin {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_menu', array($this, 'add_settings_page'), 99);
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        // Order list customization
        add_filter('manage_edit-shop_order_columns', array($this, 'add_order_column'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'display_order_column'), 10, 2);
        add_action('restrict_manage_posts', array($this, 'add_order_filter'));
        add_filter('parse_query', array($this, 'filter_orders'));
    }

    public function add_admin_menu() {
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

    public function add_settings_page() {
        add_submenu_page(
            'wc-sample-orders',
            __('Settings', 'wc-sample-orders'),
            __('Settings', 'wc-sample-orders'),
            'manage_woocommerce',
            'wc-sample-orders-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('wcso_settings', 'wcso_enable_barcode_scanner');
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_wc-sample-orders' && $hook !== 'sample-orders_page_wc-sample-orders-settings') return;

        
        $all_shipping_data = $this->render_shipping_details();

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

        // Localize data (includes Woo countries/states, shipping data)
        wp_localize_script('wcso-admin-script', 'wcsoData', array(
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'searchNonce'  => wp_create_nonce('wcso_search'),
            'cacheNonce'   => wp_create_nonce('wcso_cache'),
            'orderNonce'   => wp_create_nonce('wcso_create_order'),
            'enableScanner'=> get_option('wcso_enable_barcode_scanner', '0'),
            'currentUser'  => wp_get_current_user()->user_login,
            'countries'    => WC()->countries->get_countries(),
            'states'       => WC()->countries->get_states(),
            'baseCountry'  => WC()->countries->get_base_country(),
            'baseState'    => WC()->countries->get_base_state(),
            'shippingZones' => $this->render_shipping_details(),
        ));
    }


   public function render_shipping_details() {
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
        
        foreach($shipping_methods as $shipping_method) {
            $shipping_method_id = $shipping_method->id; 
            $shipping_method_title = $shipping_method->method_title;
            $shipping_method_instance_id = $shipping_method->instance_id; 
            $shipping_method_instance_settings = $shipping_method->instance_settings;
            $shipping_method_instance_title = $shipping_method->instance_settings['title'];
            $shipping_method_instance_cost = $shipping_method->instance_settings['cost'];
            
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

    public function render_settings_page() {
        if (!current_user_can('manage_woocommerce')) wp_die(__('You do not have sufficient permissions.'));
        
        if (isset($_POST['wcso_save_settings']) && check_admin_referer('wcso_settings_nonce')) {
            // Barcode scanner
            $enable_scanner = isset($_POST['wcso_enable_barcode_scanner']) ? '1' : '0';
            update_option('wcso_enable_barcode_scanner', $enable_scanner);
            
            // Email settings
            $cc_emails = sanitize_textarea_field($_POST['wcso_cc_emails'] ?? '');
            update_option('wcso_cc_emails', $cc_emails);
            
            $email_logging = isset($_POST['wcso_email_logging']) ? '1' : '0';
            update_option('wcso_email_logging', $email_logging);
            
            $email_from_name = sanitize_text_field($_POST['wcso_email_from_name'] ?? '');
            update_option('wcso_email_from_name', $email_from_name);
            
            $email_from_email = sanitize_email($_POST['wcso_email_from_email'] ?? '');
            update_option('wcso_email_from_email', $email_from_email);
            
            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }
        
        // Clear log action
        if (isset($_POST['wcso_clear_log']) && check_admin_referer('wcso_clear_log_nonce')) {
            WCSO_Email_Handler::get_instance()->clear_email_log();
            echo '<div class="notice notice-success"><p>Email log cleared!</p></div>';
        }
        
        $enable_scanner = get_option('wcso_enable_barcode_scanner', '0');
        $cc_emails = get_option('wcso_cc_emails', '');
        $email_logging = get_option('wcso_email_logging', '1');
        $email_from_name = get_option('wcso_email_from_name', get_bloginfo('name'));
        $email_from_email = get_option('wcso_email_from_email', get_option('admin_email'));
        ?>
        <div class="wrap">
            <h1><?php _e('Sample Orders Settings', 'wc-sample-orders'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('wcso_settings_nonce'); ?>
                
                <h2 class="title"><?php _e('General Settings', 'wc-sample-orders'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="wcso_enable_barcode_scanner"><?php _e('Enable Barcode Scanner', 'wc-sample-orders'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="wcso_enable_barcode_scanner" name="wcso_enable_barcode_scanner" value="1" <?php checked($enable_scanner, '1'); ?>>
                                <?php _e('Enable barcode/QR scanning with Barcode2Win', 'wc-sample-orders'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <h2 class="title"><?php _e('Email Notification Settings', 'wc-sample-orders'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="wcso_email_from_name"><?php _e('From Name', 'wc-sample-orders'); ?></label></th>
                        <td>
                            <input type="text" id="wcso_email_from_name" name="wcso_email_from_name" value="<?php echo esc_attr($email_from_name); ?>" class="regular-text">
                            <p class="description"><?php _e('The name that appears in the "From" field of emails.', 'wc-sample-orders'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wcso_email_from_email"><?php _e('From Email', 'wc-sample-orders'); ?></label></th>
                        <td>
                            <input type="email" id="wcso_email_from_email" name="wcso_email_from_email" value="<?php echo esc_attr($email_from_email); ?>" class="regular-text">
                            <p class="description"><?php _e('The email address that appears in the "From" field.', 'wc-sample-orders'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wcso_cc_emails"><?php _e('CC Email Addresses', 'wc-sample-orders'); ?></label></th>
                        <td>
                            <textarea id="wcso_cc_emails" name="wcso_cc_emails" rows="4" class="large-text code"><?php echo esc_textarea($cc_emails); ?></textarea>
                            <p class="description">
                                <?php _e('Enter email addresses to CC on all sample order notifications. Separate multiple emails with commas.', 'wc-sample-orders'); ?><br>
                                <strong><?php _e('Example:', 'wc-sample-orders'); ?></strong> manager@wphelpzone.com, admin@wphelpzone.com
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="wcso_email_logging"><?php _e('Email Logging', 'wc-sample-orders'); ?></label></th>
                        <td>
                            <label>
                                <input type="checkbox" id="wcso_email_logging" name="wcso_email_logging" value="1" <?php checked($email_logging, '1'); ?>>
                                <?php _e('Enable email logging for testing (logs all email attempts to email-log.txt)', 'wc-sample-orders'); ?>
                            </label>
                            <p class="description" style="color: #d63638;">
                                <strong><?php _e('Note:', 'wc-sample-orders'); ?></strong> <?php _e('Disable this in production to avoid large log files.', 'wc-sample-orders'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" name="wcso_save_settings" class="button button-primary"><?php _e('Save Settings', 'wc-sample-orders'); ?></button>
                </p>
            </form>

            <hr>

            <h2 class="title"><?php _e('Email Testing & Logs', 'wc-sample-orders'); ?></h2>
            <div class="card" style="max-width: none;">
                <h3><?php _e('Email Log', 'wc-sample-orders'); ?></h3>
                <p><?php _e('View all email attempts below. This helps you verify emails are being triggered correctly even without a mail server.', 'wc-sample-orders'); ?></p>
                
                <form method="post" style="margin-bottom: 15px;">
                    <?php wp_nonce_field('wcso_clear_log_nonce'); ?>
                    <button type="submit" name="wcso_clear_log" class="button"><?php _e('Clear Log', 'wc-sample-orders'); ?></button>
                </form>
                
                <div style="background: #f5f5f5; padding: 15px; border: 1px solid #ddd; border-radius: 4px; max-height: 400px; overflow-y: auto;">
                    <pre style="margin: 0; font-family: monospace; font-size: 12px; white-space: pre-wrap; word-wrap: break-word;"><?php 
                        echo esc_html(WCSO_Email_Handler::get_instance()->get_email_log()); 
                    ?></pre>
                </div>
            </div>

            <div class="card" style="max-width: none; margin-top: 20px;">
                <h3><?php _e('Testing Plugins Recommendation', 'wc-sample-orders'); ?></h3>
                <p><?php _e('To test email functionality without a real mail server, we recommend these plugins:', 'wc-sample-orders'); ?></p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li>
                        <strong>WP Mail Logging</strong> - 
                        <a href="<?php echo admin_url('plugin-install.php?s=WP+Mail+Logging&tab=search&type=term'); ?>" target="_blank">Install Now</a>
                        <p style="margin: 5px 0 10px 0; color: #666;">
                            <?php _e('Logs all emails sent by WordPress. View subject, recipients, content, and headers.', 'wc-sample-orders'); ?>
                        </p>
                    </li>
                    <li>
                        <strong>MailHog</strong> (for local development) - 
                        <a href="https://github.com/mailhog/MailHog" target="_blank">Learn More</a>
                        <p style="margin: 5px 0 10px 0; color: #666;">
                            <?php _e('Catches all outgoing emails locally. Perfect for testing without sending real emails.', 'wc-sample-orders'); ?>
                        </p>
                    </li>
                    <li>
                        <strong>Check Email</strong> - 
                        <a href="<?php echo admin_url('plugin-install.php?s=Check+Email&tab=search&type=term'); ?>" target="_blank">Install Now</a>
                        <p style="margin: 5px 0 10px 0; color: #666;">
                            <?php _e('Logs and displays all emails sent by WordPress. Includes test email feature.', 'wc-sample-orders'); ?>
                        </p>
                    </li>
                    <li>
                        <strong>WP Mail SMTP</strong> - 
                        <a href="<?php echo admin_url('plugin-install.php?s=WP+Mail+SMTP&tab=search&type=term'); ?>" target="_blank">Install Now</a>
                        <p style="margin: 5px 0 10px 0; color: #666;">
                            <?php _e('Configure SMTP settings and send test emails. Required when you\'re ready to send real emails.', 'wc-sample-orders'); ?>
                        </p>
                    </li>
                </ul>
            </div>
        </div>
        <?php
    }

    public function render_order_page() {
        if (!current_user_can('manage_woocommerce')) wp_die(__('You do not have sufficient permissions.'));
        $current_user = wp_get_current_user();
        $enable_scanner = get_option('wcso_enable_barcode_scanner', '0');

        //  $shipping_zones = $this->render_shipping_details();
        //     echo '<pre>';
        //     var_dump($shipping_zones);
        //     echo '</pre>';
           

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

                <!-- Section: Billing & Shipping -->
                <div class="wcso-section">
                    <div class="wcso-grid">

                        <!-- Billing (user selector) -->
                        <div class="wcso-card">
                            <h3 class="wcso-section-title"><span class="dashicons dashicons-businessman"></span> Billing Information</h3>
                            <div class="wcso-form-group">
                                <label for="billing_user_id">Billed User</label>
                                <select id="billing_user_id" class="wcso-input">
                                    <?php
                                    $eligible = get_users(array(
                                        'role__in' => array('administrator','shop_manager'), // add 'customer' if needed
                                        'fields'   => array('ID','display_name','user_login'),
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
                                <p class="description">Defaults to current user; choose another eligible user if needed.</p>
                            </div>
                        </div>

                        <!-- Shipping full address -->
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
                                    <p class="description" style="font-size: 11px; margin-top: 4px; color: #666;">
                                        If provided, this email will be displayed in the order's shipping details.
                                    </p>
                                </div>
                            </div>

                            <div class="wcso-form-group">
                                <label for="shipping_method">Shipping Method <span class="required">*</span></label>
                                <select id="shipping_method" class="wcso-input"></select>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Section: Add Products -->
                <div class="wcso-section">
                    <div class="wcso-card wcso-card-prominent">
                        <h3 class="wcso-section-title"><span class="dashicons dashicons-search"></span> Add Products to Order</h3>

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
                                    <label for="barcode_input"><span class="dashicons dashicons-smartphone"></span> Scan Barcode/QR Code</label>
                                    <div class="wcso-search-input-wrapper">
                                        <input type="text" id="barcode_input" class="wcso-input wcso-input-search" placeholder="Click here to scan with Barcode2Win..." autocomplete="off">
                                        <span class="wcso-search-icon dashicons dashicons-camera"></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Section: Selected Products -->
                <div class="wcso-section">
                    <div class="wcso-card">
                        <h3 class="wcso-section-title"><span class="dashicons dashicons-clipboard"></span> Selected Products <span id="products-count" class="wcso-count-badge"></span></h3>
                        <div id="selected_products_table"></div>
                        <input type="hidden" id="selected_products_data" name="selected_products_data">
                    </div>
                </div>

                <!-- Section: Approval & Notes -->
                <div class="wcso-section">
                    <div class="wcso-grid wcso-grid-40-60">
                        <div class="wcso-card">
                            <h3 class="wcso-section-title"><span class="dashicons dashicons-yes-alt"></span> Approval</h3>
                            <div class="wcso-form-group">
                                <label for="approved_by">Approved By <span class="required">*</span></label>
                                <input type="text" id="approved_by" class="wcso-input" placeholder="Name of approver" required>
                            </div>
                        </div>
                        <div class="wcso-card">
                            <h3 class="wcso-section-title"><span class="dashicons dashicons-edit"></span> Additional Information</h3>
                            <div class="wcso-form-group">
                                <label for="order_note">Order Note</label>
                                <textarea id="order_note" class="wcso-textarea" rows="4" placeholder="Add any special instructions or notes..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="wcso-section wcso-submit-section">
                    <button type="submit" class="wcso-btn wcso-btn-primary wcso-btn-large" id="submit_order"><span class="dashicons dashicons-cart"></span> Create Sample Order</button>
                    <span id="order_loading" style="display:none; margin-left:15px;"><span class="spinner is-active"></span> Creating order...</span>
                </div>
            </form>

            <div id="order_message"></div>
        </div>

        <!-- Modal -->
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
    public function add_order_column($columns) {
        $new = array();
        foreach ($columns as $key => $col) {
            $new[$key] = $col;
            if ($key === 'order_status') $new['sample_order'] = __('Sample', 'wc-sample-orders');
        }
        return $new;
    }

    public function display_order_column($column, $post_id) {
        if ($column !== 'sample_order') return;
        $order = wc_get_order($post_id);
        if ($order && $order->get_meta('_is_sample_order') === 'yes') {
            echo '<span style="background:#46b450;color:#fff;padding:3px 8px;border-radius:3px;font-size:11px;">SAMPLE</span>';
        }
    }

    public function add_order_filter() {
        global $typenow;
        if ($typenow !== 'shop_order') return;
        $selected = isset($_GET['sample_filter']) ? $_GET['sample_filter'] : '';
        ?>
        <select name="sample_filter">
            <option value="">All Orders</option>
            <option value="yes" <?php selected($selected, 'yes'); ?>>Sample Orders</option>
            <option value="no"  <?php selected($selected, 'no');  ?>>Regular Orders</option>
        </select>
        <?php
    }

    public function filter_orders($query) {
        global $pagenow, $typenow;
        if ($typenow === 'shop_order' && $pagenow === 'edit.php' && isset($_GET['sample_filter']) && $_GET['sample_filter'] !== '') {
            $query->set('meta_query', array(
                array('key' => '_is_sample_order', 'value' => $_GET['sample_filter'], 'compare' => '=')
            ));
        }
    }
}
