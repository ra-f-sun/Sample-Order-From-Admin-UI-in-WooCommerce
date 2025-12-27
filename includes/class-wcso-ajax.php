<?php

/**
 * AJAX Class
 *
 * @package WPHelpZone\WCSO
 */

namespace WPHelpZone\WCSO;

use WPHelpZone\WCSO\Abstracts\WCSO_Singleton;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * WCSO AJAX Class
 *
 * Handles all AJAX requests for the plugin.
 */
class WCSO_Ajax extends WCSO_Singleton
{

    /**
     * Initialize the class
     *
     * @return void
     */
    protected function init()
    {
        add_action('wp_ajax_wcso_search_products', array($this, 'search_products'));
        add_action('wp_ajax_wcso_get_all_products', array($this, 'get_all_products'));
        add_action('wp_ajax_wcso_create_order', array($this, 'create_sample_order'));
        add_action('wp_ajax_wcso_save_settings', array($this, 'save_settings'));

        add_action('wp_ajax_wcso_get_email_log', array($this, 'get_email_log'));
        add_action('wp_ajax_wcso_clear_log', array($this, 'clear_email_log'));

        // Fetch Sample Orders Created before Analytics.
        add_action('wp_ajax_wcso_analytics_backfill', array($this, 'analytics_backfill'));
    }
    /**
     * Handle Settings Save via React
     *
     * @return void
     */
    public function save_settings()
    {
        check_ajax_referer('wcso_save_settings', 'nonce');

        if (! current_user_can('manage_woocommerce')) {
            wp_send_json_error('Unauthorized');
        }

        $data = $_POST['settings'];

        // Save General Settings.
        update_option('wcso_enable_barcode_scanner', sanitize_text_field($data['barcode_scanner']));
        update_option('wcso_coupon_code', sanitize_text_field($data['coupon_code']));
        update_option('wcso_email_logging', sanitize_text_field($data['email_logging']));

        // Save Tier Settings.
        $tiers = $data['tiers'];

        // Tier 1.
        update_option('wcso_t1_name', sanitize_text_field($tiers['t1']['name']));
        update_option('wcso_t1_limit', absint($tiers['t1']['limit']));

        // Tier 2.
        update_option('wcso_t2_name', sanitize_text_field($tiers['t2']['name']));
        update_option('wcso_t2_limit', absint($tiers['t2']['limit']));
        update_option('wcso_t2_email', sanitize_email($tiers['t2']['email']));

        // Tier 3.
        update_option('wcso_t3_name', sanitize_text_field($tiers['t3']['name']));
        update_option('wcso_t3_limit', absint($tiers['t3']['limit']));
        update_option('wcso_t3_email', sanitize_email($tiers['t3']['email']));

        wp_send_json_success('Settings saved successfully.');
    }

    /**
     * To select products for orders
     *
     * @return void
     */
    public function get_all_products()
    {
        check_ajax_referer('wcso_cache', 'nonce');
        if (! current_user_can('manage_woocommerce')) {
            wp_send_json_error('Unauthorized');
        }

        $all_product_ids = get_posts(
            array(
                'post_type'      => 'product',
                'post_status'    => array('publish', 'draft', 'private', 'pending'),
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
                'fields'         => 'ids',
            )
        );

        $products_necessary_data = array();
        foreach ($all_product_ids as $single_product_id) {
            $single_product = wc_get_product($single_product_id);
            if (! $single_product) {
                continue;
            }
            $post   = get_post($single_product_id);
            $status = ($post->post_status !== 'publish') ? $post->post_status : $single_product->get_catalog_visibility();
            $products_necessary_data[] = array(
                'id'         => $single_product->get_id(),
                'name'       => $single_product->get_name(),
                'sku'        => $single_product->get_sku(),
                'status'     => $status,
                'price'      => $single_product->get_price(),
                'price_html' => $single_product->get_price() . '/-',
            );
        }
        wp_send_json_success($products_necessary_data);
    }

    /**
     * Search products by term
     *
     * @return void
     */
    public function search_products()
    {
        check_ajax_referer('wcso_search', 'nonce');
        if (! current_user_can('manage_woocommerce')) {
            wp_send_json_error('Unauthorized');
        }

        $term   = sanitize_text_field($_POST['search'] ?? '');
        $is_num = is_numeric($term);

        $products = $this->search_products_by_term($term, $is_num);
        $products_necessary_data = $this->format_product_data($products);

        wp_send_json_success($products_necessary_data);
    }

    /**
     * Search products by term using ID, SKU, or title
     * 
     * Helper method for search_products()
     * Handles the logic of searching products based on whether the term is numeric or text
     *
     * @param string $term The search term to query products
     * @param bool $is_num Whether the search term is numeric
     * @return array Array of WP_Post objects representing products
     */
    private function search_products_by_term($term, $is_num)
    {
        $args = array(
            'post_type'      => 'product',
            'post_status'    => array('publish', 'draft', 'private', 'pending'),
            'posts_per_page' => 20,
        );

        if ($is_num) {
            $args['post__in'] = array($term);
            $by_id            = get_posts($args);
            $by_sku           = get_posts(
                array(
                    'post_type'      => 'product',
                    'post_status'    => array('publish', 'draft', 'private', 'pending'),
                    'posts_per_page' => 20,
                    'meta_query'     => array(
                        array(
                            'key'     => '_sku',
                            'value'   => $term,
                            'compare' => 'LIKE',
                        ),
                    ),
                )
            );
            $products = array_unique(array_merge($by_id, $by_sku), SORT_REGULAR);
            if (empty($products)) {
                $args['s'] = $term;
                $products  = get_posts($args);
            }
        } else {
            $args['s'] = $term;
            $by_title  = get_posts($args);
            $by_sku    = get_posts(
                array(
                    'post_type'      => 'product',
                    'post_status'    => array('publish', 'draft', 'private', 'pending'),
                    'posts_per_page' => 20,
                    'meta_query'     => array(
                        array(
                            'key'     => '_sku',
                            'value'   => $term,
                            'compare' => 'LIKE',
                        ),
                    ),
                )
            );
            $products = array_unique(array_merge($by_title, $by_sku), SORT_REGULAR);
        }

        return $products;
    }

    /**
     * Format product data for JSON response
     * 
     * Helper method for search_products()
     * Formats raw product posts into array structure with necessary data
     *
     * @param array $products Array of WP_Post objects
     * @return array Formatted array of product data
     */
    private function format_product_data($products)
    {
        $seen                    = array();
        $products_necessary_data = array();
        foreach ($products as $post) {
            if (in_array($post->ID, $seen)) {
                continue;
            }
            $seen[]         = $post->ID;
            $single_product = wc_get_product($post->ID);
            if (! $single_product) {
                continue;
            }
            $status                    = ($post->post_status !== 'publish') ? $post->post_status : $single_product->get_catalog_visibility();
            $products_necessary_data[] = array(
                'id'         => $single_product->get_id(),
                'name'       => $single_product->get_name(),
                'sku'        => $single_product->get_sku(),
                'status'     => $status,
                'price'      => $single_product->get_price(),
                'price_html' => $single_product->get_price() . '/-',
            );
        }
        return $products_necessary_data;
    }

    /**
     * Create a sample order
     *
     * @return void
     */
    public function create_sample_order()
    {
        check_ajax_referer('wcso_create_order', 'nonce');
        if (! current_user_can('manage_woocommerce')) {
            wp_send_json_error('Unauthorized');
        }

        try {
            $products = is_array($_POST['products']) ? $_POST['products'] : json_decode(stripslashes($_POST['products']), true);
            $category = sanitize_text_field($_POST['sample_category'] ?? '');

            if (empty($products)) {
                wp_send_json_error('No products selected');
            }

            // Calculate order total and determine tier/approval logic
            $original_total = $this->calculate_order_total($products);
            $config = WCSO_Settings::get_tier_config();
            $tier_data = $this->determine_tier_and_status($original_total, $config);

            // Create and configure the order
            $order = $this->create_wc_order();
            if (! $order) {
                wp_send_json_error('Failed to create order');
            }

            $this->add_products_to_order($order, $products);
            $this->set_order_billing($order, isset($_POST['billing_user_id']) ? absint($_POST['billing_user_id']) : 0);
            $this->set_order_shipping($order, $_POST);
            $this->add_shipping_method_to_order($order, $_POST);
            $this->apply_coupon_to_order($order);

            // Save all order metadata
            $meta_data = array(
                'original_total'    => $original_total,
                'tier'              => $tier_data['tier'],
                'category'          => $category,
                'assigned_approver' => $tier_data['assigned_approver'],
                'needed_approvals'  => $tier_data['needed_approvals'],
                'order_note'        => isset($_POST['order_note']) ? sanitize_textarea_field($_POST['order_note']) : '',
            );
            $this->save_order_meta($order, $meta_data);

            $order->set_status($tier_data['status']);
            $order->save();

            // Trigger action hook for emails
            do_action('wcso_sample_order_created', $order->get_id());

            wp_send_json_success(
                array(
                    'order_id'  => $order->get_id(),
                    'order_url' => admin_url('post.php?post=' . $order->get_id() . '&action=edit'),
                )
            );
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Calculate the original order total from products array
     * 
     * Helper method for create_sample_order()
     * Calculates total price before any coupons or discounts are applied
     *
     * @param array $products Array of product items with id and quantity
     * @return float The calculated original total
     */
    private function calculate_order_total($products)
    {
        $original_total = 0;
        foreach ($products as $item) {
            $product_id       = isset($item['id']) ? absint($item['id']) : 0;
            $product_quantity = max(1, intval($item['quantity']));
            $single_product   = wc_get_product($product_id);
            if ($single_product) {
                $original_total += floatval($single_product->get_price()) * $product_quantity;
            }
        }
        return $original_total;
    }

    /**
     * Determine tier and order status based on order total
     * 
     * Helper method for create_sample_order()
     * Uses tier configuration to determine which tier the order belongs to and what approvals are needed
     *
     * @param float $original_total The order total before discounts
     * @param array $config Tier configuration from WCSO_Settings
     * @return array Array with tier, status, needed_approvals, and assigned_approver keys
     */
    private function determine_tier_and_status($original_total, $config)
    {
        $tier             = '';
        $status           = 'processing'; // default
        $needed_approvals = array();
        $assigned_approver = '';

        if ($original_total <= $config['t1']['limit']) {
            // Tier 1
            $tier              = 't1so';
            $assigned_approver = $config['t1']['name'];
        } elseif ($original_total <= $config['t2']['limit']) {
            // Tier 2
            $tier              = 't2so';
            $status            = 'on-hold';
            $needed_approvals[] = $config['t2']['email'];
            $assigned_approver  = $config['t2']['name'];
        } else {
            // Tier 3 (Greater than Tier 2 limit)
            $tier   = 't3so';
            $status = 'on-hold';

            // Needs T2 AND T3
            if (! empty($config['t2']['email'])) {
                $needed_approvals[] = $config['t2']['email'];
            }
            if (! empty($config['t3']['email'])) {
                $needed_approvals[] = $config['t3']['email'];
            }

            $assigned_approver = $config['t3']['name'];
        }

        return array(
            'tier'              => $tier,
            'status'            => $status,
            'needed_approvals'  => $needed_approvals,
            'assigned_approver' => $assigned_approver,
        );
    }

    /**
     * Create a new WooCommerce order object
     * 
     * Helper method for create_sample_order()
     * Creates and returns a new WC_Order instance
     *
     * @return \WC_Order|false The created order object or false on failure
     */
    private function create_wc_order()
    {
        return wc_create_order();
    }

    /**
     * Add products to the order
     * 
     * Helper method for create_sample_order()
     * Iterates through products array and adds each product to the order
     *
     * @param \WC_Order $order The WooCommerce order object
     * @param array $products Array of product items with id and quantity
     * @return void
     */
    private function add_products_to_order($order, $products)
    {
        foreach ($products as $item) {
            $product_id       = absint($item['id']);
            $product_quantity = max(1, intval($item['quantity']));
            if ($single_product = wc_get_product($product_id)) {
                // Create in original price, reduce later by coupon
                $order->add_product($single_product, $product_quantity);
            }
        }
    }

    /**
     * Set billing information for the order
     * 
     * Helper method for create_sample_order()
     * Sets customer ID and billing details from user account
     *
     * @param \WC_Order $order The WooCommerce order object
     * @param int $billing_user_id The user ID for billing information
     * @return void
     */
    private function set_order_billing($order, $billing_user_id)
    {
        if ($billing_user_id) {
            $order->set_customer_id($billing_user_id);
            $billing_user = get_user_by('id', $billing_user_id);
            if ($billing_user) {
                $order->set_billing_first_name(get_user_meta($billing_user->ID, 'billing_first_name', true) ?: $billing_user->display_name);
                $order->set_billing_email($billing_user->user_email);
            }
        }
    }

    /**
     * Set shipping address for the order
     * 
     * Helper method for create_sample_order()
     * Sets all shipping address fields including custom email and phone meta
     *
     * @param \WC_Order $order The WooCommerce order object
     * @param array $shipping_data Array containing shipping address fields from $_POST
     * @return void
     */
    private function set_order_shipping($order, $shipping_data)
    {
        $order->set_shipping_first_name(sanitize_text_field($shipping_data['shipping_first_name'] ?? ''));
        $order->set_shipping_last_name(sanitize_text_field($shipping_data['shipping_last_name'] ?? ''));
        $order->set_shipping_company(sanitize_text_field($shipping_data['shipping_company'] ?? ''));
        $order->set_shipping_country(sanitize_text_field($shipping_data['shipping_country'] ?? ''));
        $order->set_shipping_address_1(sanitize_text_field($shipping_data['shipping_address_1'] ?? ''));
        $order->set_shipping_address_2(sanitize_text_field($shipping_data['shipping_address_2'] ?? ''));
        $order->set_shipping_city(sanitize_text_field($shipping_data['shipping_city'] ?? ''));
        $order->set_shipping_state(sanitize_text_field($shipping_data['shipping_state'] ?? ''));
        $order->set_shipping_postcode(sanitize_text_field($shipping_data['shipping_postcode'] ?? ''));

        // Custom Shipping Email & Phone Meta
        if (! empty($shipping_data['shipping_email'])) {
            $order->update_meta_data('_shipping_email', sanitize_email($shipping_data['shipping_email']));
        }
        if (! empty($shipping_data['shipping_phone'])) {
            $order->update_meta_data('_shipping_phone', sanitize_text_field($shipping_data['shipping_phone']));
        }
    }

    /**
     * Add shipping method to the order
     * 
     * Helper method for create_sample_order()
     * Creates a shipping item and adds it to the order
     *
     * @param \WC_Order $order The WooCommerce order object
     * @param array $shipping_method_data Array containing shipping method details from $_POST
     * @return void
     */
    private function add_shipping_method_to_order($order, $shipping_method_data)
    {
        $shipping_item = new \WC_Order_Item_Shipping();
        $shipping_item->set_method_title(sanitize_text_field($shipping_method_data['shipping_method_title']));
        $shipping_item->set_method_id(sanitize_text_field($shipping_method_data['shipping_method_id']));
        $shipping_item->set_total(sanitize_text_field($shipping_method_data['shipping_method_cost']));
        $shipping_item->set_instance_id(sanitize_text_field($shipping_method_data['shipping_method_instance_id']));
        $order->add_item($shipping_item);
    }

    /**
     * Apply coupon to order and calculate totals
     * 
     * Helper method for create_sample_order()
     * Applies configured coupon code and calculates final order totals
     *
     * @param \WC_Order $order The WooCommerce order object
     * @return void
     */
    private function apply_coupon_to_order($order)
    {
        $coupon_code = get_option('wcso_coupon_code', 'flat100');

        // Only apply if a code is set
        if (! empty($coupon_code)) {
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
    }

    /**
     * Save all order metadata
     * 
     * Helper method for create_sample_order()
     * Saves sample order metadata including tier, approvals, origin, and customer note
     *
     * @param \WC_Order $order The WooCommerce order object
     * @param array $meta_data Array containing order metadata (original_total, tier, category, etc.)
     * @return void
     */
    private function save_order_meta($order, $meta_data)
    {
        $current_user = wp_get_current_user();
        $roles        = $current_user->roles;
        $role_display = ! empty($roles) ? ucfirst($roles[0]) : 'User';
        $origin       = $current_user->display_name . ' (' . $role_display . ')';

        $order->update_meta_data('_is_sample_order', 'yes');
        $order->update_meta_data('_original_total', $meta_data['original_total']);
        $order->update_meta_data('_wcso_tier', $meta_data['tier']);
        $order->update_meta_data('_wcso_sample_category', $meta_data['category']);
        $order->update_meta_data('_wcso_origin', $origin);
        $order->update_meta_data('_approved_by', $meta_data['assigned_approver']);
        $order->update_meta_data('_wcso_approvals_needed', $meta_data['needed_approvals']);
        $order->update_meta_data('_wcso_approvals_granted', array());

        // Customer Note
        if (! empty($meta_data['order_note'])) {
            $order->set_customer_note($meta_data['order_note']);
        }
    }

    /**
     * Get email log content
     *
     * @return void
     */
    public function get_email_log()
    {
        check_ajax_referer('wcso_save_settings', 'nonce');
        if (! current_user_can('manage_woocommerce')) {
            wp_send_json_error('Unauthorized');
        }

        $content = WCSO_Email_Handler::get_instance()->get_email_log();
        wp_send_json_success($content);
    }

    /**
     * Clear email log
     *
     * @return void
     */
    public function clear_email_log()
    {
        check_ajax_referer('wcso_save_settings', 'nonce');
        if (! current_user_can('manage_woocommerce')) {
            wp_send_json_error('Unauthorized');
        }

        WCSO_Email_Handler::get_instance()->clear_email_log();
        wp_send_json_success('Log cleared.');
    }

    /**
     * Backfill analytics data
     *
     * @return void
     */
    public function analytics_backfill()
    {
        check_ajax_referer('wcso_save_settings', 'nonce');
        if (! current_user_can('manage_woocommerce')) {
            wp_send_json_error('Unauthorized');
        }

        $count = WCSO_Analytics_DB::get_instance()->backfill_data();
        wp_send_json_success("Successfully indexed {$count} orders for analytics.");
    }
}
