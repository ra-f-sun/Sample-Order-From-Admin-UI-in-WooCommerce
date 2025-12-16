<?php
if (!defined('ABSPATH')) exit;
class WCSO_Ajax
{
    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct()
    {
        add_action('wp_ajax_wcso_search_products', array($this, 'search_products'));
        add_action('wp_ajax_wcso_get_all_products', array($this, 'get_all_products'));
        add_action('wp_ajax_wcso_create_order', array($this, 'create_sample_order'));
        add_action('wp_ajax_wcso_save_settings', array($this, 'save_settings'));

        add_action('wp_ajax_wcso_get_email_log', array($this, 'get_email_log'));
        add_action('wp_ajax_wcso_clear_log', array($this, 'clear_email_log'));

        // Fetch Sample Orders Created before Analytics
        add_action('wp_ajax_wcso_analytics_backfill', array($this, 'analytics_backfill'));
    }


    // Handle Settings Save via React
    public function save_settings()
    {
        check_ajax_referer('wcso_save_settings', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Unauthorized');
        }

        $data = $_POST['settings'];

        // Save General Settings
        update_option('wcso_enable_barcode_scanner', sanitize_text_field($data['barcode_scanner']));
        update_option('wcso_coupon_code', sanitize_text_field($data['coupon_code']));
        update_option('wcso_email_logging', sanitize_text_field($data['email_logging']));

        // ===Save Tier Settings Start===
        $tiers = $data['tiers'];

        // Tier 1
        update_option('wcso_t1_name', sanitize_text_field($tiers['t1']['name']));
        update_option('wcso_t1_limit', absint($tiers['t1']['limit']));

        // Tier 2
        update_option('wcso_t2_name', sanitize_text_field($tiers['t2']['name']));
        update_option('wcso_t2_limit', absint($tiers['t2']['limit']));
        update_option('wcso_t2_email', sanitize_email($tiers['t2']['email']));

        // Tier 3
        update_option('wcso_t3_name', sanitize_text_field($tiers['t3']['name']));
        update_option('wcso_t3_limit', absint($tiers['t3']['limit']));
        update_option('wcso_t3_email', sanitize_email($tiers['t3']['email']));

        // ===Save Tier Settings End===

        wp_send_json_success('Settings saved successfully.');
    }

    // To select products for orders
    public function get_all_products()
    {
        check_ajax_referer('wcso_cache', 'nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('Unauthorized');

        $all_product_ids = get_posts(array(
            'post_type'      => 'product',
            'post_status'    => array('publish', 'draft', 'private', 'pending'),
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids'
        ));

        $products_necessary_data = array();
        foreach ($all_product_ids as $single_product_id) {
            $single_product = wc_get_product($single_product_id);
            if (!$single_product) continue;
            $post = get_post($single_product_id);
            $status = ($post->post_status !== 'publish') ? $post->post_status : $single_product->get_catalog_visibility();
            $products_necessary_data[] = array(
                'id'         => $single_product->get_id(),
                'name'       => $single_product->get_name(),
                'sku'        => $single_product->get_sku(),
                'status'     => $status,
                'price'      => $single_product->get_price(),
                'price_html' => $single_product->get_price() . '/-'
            );
        }
        wp_send_json_success($products_necessary_data);
    }

    public function search_products()
    {
        check_ajax_referer('wcso_search', 'nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('Unauthorized');

        $term = sanitize_text_field($_POST['search'] ?? '');
        $is_num = is_numeric($term);

        $args = array(
            'post_type'      => 'product',
            'post_status'    => array('publish', 'draft', 'private', 'pending'),
            'posts_per_page' => 20,
        );

        if ($is_num) {
            $args['post__in'] = array($term);
            $by_id = get_posts($args);
            $by_sku = get_posts(array(
                'post_type'   => 'product',
                'post_status' => array('publish', 'draft', 'private', 'pending'),
                'posts_per_page' => 20,
                'meta_query'  => array(array('key' => '_sku', 'value' => $term, 'compare' => 'LIKE'))
            ));
            $products = array_unique(array_merge($by_id, $by_sku), SORT_REGULAR);
            if (empty($products)) {
                $args['s'] = $term;
                $products = get_posts($args);
            }
        } else {
            $args['s'] = $term;
            $by_title = get_posts($args);
            $by_sku   = get_posts(array(
                'post_type'   => 'product',
                'post_status' => array('publish', 'draft', 'private', 'pending'),
                'posts_per_page' => 20,
                'meta_query'  => array(array('key' => '_sku', 'value' => $term, 'compare' => 'LIKE'))
            ));
            $products = array_unique(array_merge($by_title, $by_sku), SORT_REGULAR);
        }

        $seen = array();
        $products_necessary_data  = array();
        foreach ($products as $post) {
            if (in_array($post->ID, $seen)) continue;
            $seen[] = $post->ID;
            $single_product = wc_get_product($post->ID);
            if (!$single_product) continue;
            $status = ($post->post_status !== 'publish') ? $post->post_status : $single_product->get_catalog_visibility();
            $products_necessary_data[] = array(
                'id'         => $single_product->get_id(),
                'name'       => $single_product->get_name(),
                'sku'        => $single_product->get_sku(),
                'status'     => $status,
                'price'      => $single_product->get_price(),
                'price_html' => $single_product->get_price() . '/-'
            );
        }
        wp_send_json_success($products_necessary_data);
    }

    public function create_sample_order()
    {
        check_ajax_referer('wcso_create_order', 'nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('Unauthorized');

        try {
            $products = is_array($_POST['products']) ? $_POST['products'] : json_decode(stripslashes($_POST['products']), true);

            // Purpose of sample order is being called sample category
            $category = sanitize_text_field($_POST['sample_category'] ?? '');

            if (empty($products)) wp_send_json_error('No products selected');

            // Calculate Original Total (Before Coupon)
            // We use this for Tier logic
            $original_total = 0;
            foreach ($products as $item) {
                $product_id = isset($item['id']) ? absint($item['id']) : 0;
                $product_quantity = max(1, intval($item['quantity']));
                $single_product = wc_get_product($product_id);
                if ($single_product) $original_total += floatval($single_product->get_price()) * $product_quantity;
            }

            // Load Settings Logic for approval
            $config = WCSO_Settings::get_tier_config();
            $tier = '';
            $status = 'processing'; // default
            $needed_approvals = array();
            $assigned_approver = '';

            // Determine Tier & Assign
            // Uses dynamic limits from Settings
            if ($original_total <= $config['t1']['limit']) {
                // Tier 1
                $tier = 't1so';
                $assigned_approver = $config['t1']['name'];
            } elseif ($original_total <= $config['t2']['limit']) {
                // Tier 2
                $tier = 't2so';
                $status = 'on-hold';
                $needed_approvals[] = $config['t2']['email'];
                $assigned_approver = $config['t2']['name'];
            } else {
                // Tier 3 (Greater than Tier 2 limit)
                $tier = 't3so';
                $status = 'on-hold';

                // Needs T2 AND T3
                if (!empty($config['t2']['email'])) $needed_approvals[] = $config['t2']['email'];
                if (!empty($config['t3']['email'])) $needed_approvals[] = $config['t3']['email'];

                $assigned_approver = $config['t3']['name'];
            }

            // Create Order
            $order = wc_create_order();
            if (!$order) wp_send_json_error('Failed to create order');

            // Add Products
            foreach ($products as $item) {
                $product_id = absint($item['id']);
                $product_quantity = max(1, intval($item['quantity']));
                if ($single_product = wc_get_product($product_id)) {

                    // Create in original price, reduce later by coupon
                    $order->add_product($single_product, $product_quantity);
                }
            }

            // Set Billing
            $billing_user_id = isset($_POST['billing_user_id']) ? absint($_POST['billing_user_id']) : 0;
            if ($billing_user_id) {
                $order->set_customer_id($billing_user_id);
                $billing_user = get_user_by('id', $billing_user_id);
                if ($billing_user) {
                    $order->set_billing_first_name(get_user_meta($billing_user->ID, 'billing_first_name', true) ?: $billing_user->display_name);
                    $order->set_billing_email($billing_user->user_email);
                }
            }

            // Set Shipping Address
            $order->set_shipping_first_name(sanitize_text_field($_POST['shipping_first_name'] ?? ''));
            $order->set_shipping_last_name(sanitize_text_field($_POST['shipping_last_name'] ?? ''));
            $order->set_shipping_company(sanitize_text_field($_POST['shipping_company'] ?? ''));
            $order->set_shipping_country(sanitize_text_field($_POST['shipping_country'] ?? ''));
            $order->set_shipping_address_1(sanitize_text_field($_POST['shipping_address_1'] ?? ''));
            $order->set_shipping_address_2(sanitize_text_field($_POST['shipping_address_2'] ?? ''));
            $order->set_shipping_city(sanitize_text_field($_POST['shipping_city'] ?? ''));
            $order->set_shipping_state(sanitize_text_field($_POST['shipping_state'] ?? ''));
            $order->set_shipping_postcode(sanitize_text_field($_POST['shipping_postcode'] ?? ''));

            // Custom Shipping Email & Phone Meta
            if (!empty($_POST['shipping_email'])) $order->update_meta_data('_shipping_email', sanitize_email($_POST['shipping_email']));
            if (!empty($_POST['shipping_phone'])) $order->update_meta_data('_shipping_phone', sanitize_text_field($_POST['shipping_phone']));

            // Add Shipping Method
            $shipping_item = new WC_Order_Item_Shipping();
            $shipping_item->set_method_title(sanitize_text_field($_POST['shipping_method_title']));
            $shipping_item->set_method_id(sanitize_text_field($_POST['shipping_method_id']));
            $shipping_item->set_total(sanitize_text_field($_POST['shipping_method_cost']));
            $shipping_item->set_instance_id(sanitize_text_field($_POST['shipping_method_instance_id']));
            $order->add_item($shipping_item);

            // ===Apply Coupon Start===
            // Get coupon code, fallback "flat100"
            $coupon_code = get_option('wcso_coupon_code', 'flat100');

            // Only apply if a code is set
            if (!empty($coupon_code)) {
                $result = $order->apply_coupon($coupon_code);

                // Log error if coupon fails, but proceed
                if (is_wp_error($result)) {
                    $order->add_order_note('Error applying sample coupon (' . $coupon_code . '): ' . $result->get_error_message());
                }
            }

            $result = $order->apply_coupon($coupon_code);

            // Log error if coupon fails, but proceed
            if (is_wp_error($result)) {
                $order->add_order_note('Error applying sample coupon (flat100): ' . $result->get_error_message());
            }

            // Calculate totals (This applies the discount)
            $order->calculate_totals();
            // ===Apply Coupon End===

            // 5. Save Meta
            $current_user = wp_get_current_user();
            $roles = $current_user->roles;
            $role_display = !empty($roles) ? ucfirst($roles[0]) : 'User';
            $origin = $current_user->display_name . " (" . $role_display . ")";

            $order->update_meta_data('_is_sample_order', 'yes');
            $order->update_meta_data('_original_total', $original_total);
            $order->update_meta_data('_wcso_tier', $tier);
            $order->update_meta_data('_wcso_sample_category', $category);
            $order->update_meta_data('_wcso_origin', $origin);

            $order->update_meta_data('_approved_by', $assigned_approver);

            $order->update_meta_data('_wcso_approvals_needed', $needed_approvals);
            $order->update_meta_data('_wcso_approvals_granted', array());

            // Customer Note
            if (!empty($_POST['order_note'])) {
                $order->set_customer_note(sanitize_textarea_field($_POST['order_note']));
            }

            $order->set_status($status);
            $order->save();

            // Trigger Emails
            do_action('wcso_sample_order_created', $order->get_id());

            wp_send_json_success(array(
                'order_id'  => $order->get_id(),
                'order_url' => admin_url('post.php?post=' . $order->get_id() . '&action=edit')
            ));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    // Email Log
    public function get_email_log()
    {
        check_ajax_referer('wcso_save_settings', 'nonce'); // Reuse settings nonce
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('Unauthorized');

        $content = WCSO_Email_Handler::get_instance()->get_email_log();
        wp_send_json_success($content);
    }

    public function clear_email_log()
    {
        check_ajax_referer('wcso_save_settings', 'nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('Unauthorized');

        WCSO_Email_Handler::get_instance()->clear_email_log();
        wp_send_json_success('Log cleared.');
    }

    public function analytics_backfill()
    {
        check_ajax_referer('wcso_save_settings', 'nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('Unauthorized');

        $count = WCSO_Analytics_DB::get_instance()->backfill_data();
        wp_send_json_success("Successfully indexed {$count} orders for analytics.");
    }
}
