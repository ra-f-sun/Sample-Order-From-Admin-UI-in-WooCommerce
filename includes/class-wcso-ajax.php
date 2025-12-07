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
    }

    public function get_all_products()
    {
        check_ajax_referer('wcso_cache', 'nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('Unauthorized');

        $ids = get_posts(array(
            'post_type'      => 'product',
            'post_status'    => array('publish', 'draft', 'private', 'pending'),
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids'
        ));

        $out = array();
        foreach ($ids as $id) {
            $p = wc_get_product($id);
            if (!$p) continue;
            $post = get_post($id);
            $status = ($post->post_status !== 'publish') ? $post->post_status : $p->get_catalog_visibility();
            $out[] = array(
                'id'         => $p->get_id(),
                'name'       => $p->get_name(),
                'sku'        => $p->get_sku(),
                'status'     => $status,
                'price'      => $p->get_price(),
                'price_html' => $p->get_price() . '/-'
            );
        }
        wp_send_json_success($out);
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
        $out  = array();
        foreach ($products as $post) {
            if (in_array($post->ID, $seen)) continue;
            $seen[] = $post->ID;
            $p = wc_get_product($post->ID);
            if (!$p) continue;
            $status = ($post->post_status !== 'publish') ? $post->post_status : $p->get_catalog_visibility();
            $out[] = array(
                'id'         => $p->get_id(),
                'name'       => $p->get_name(),
                'sku'        => $p->get_sku(),
                'status'     => $status,
                'price'      => $p->get_price(),
                'price_html' => $p->get_price() . '/-'
            );
        }
        wp_send_json_success($out);
    }

    public function create_sample_order()
    {
        check_ajax_referer('wcso_create_order', 'nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('Unauthorized');

        try {
            $products = is_array($_POST['products']) ? $_POST['products'] : json_decode(stripslashes($_POST['products']), true);
            // v2 Update: We do not get approved_by from POST anymore. We calculate it.
            $category = sanitize_text_field($_POST['sample_category'] ?? '');

            if (empty($products)) wp_send_json_error('No products selected');

            // 1. Calculate Original Total (Before Coupon)
            // We use this for Tier logic
            $original_total = 0;
            foreach ($products as $item) {
                $pid = isset($item['id']) ? absint($item['id']) : 0;
                $qty = max(1, intval($item['quantity']));
                $p = wc_get_product($pid);
                if ($p) $original_total += floatval($p->get_price()) * $qty;
            }

            // 2. Load Settings Logic
            $config = WCSO_Settings::get_tier_config();
            $tier = '';
            $status = 'processing'; // default
            $needed_approvals = array();
            $assigned_approver = ''; // New variable

            // 3. Determine Tier & Assign (SERVER SIDE AUTHORITY)
            if ($original_total <= 15) {
                // Tier 1
                $tier = 't1so';
                $assigned_approver = $config['t1']['name'];
            } elseif ($original_total <= 100) {
                // Tier 2
                $tier = 't2so';
                $status = 'on-hold';
                $needed_approvals[] = $config['t2']['email'];
                $assigned_approver = $config['t2']['name'];
            } else {
                // Tier 3 (> 100)
                $tier = 't3so';
                $status = 'on-hold';
                // Needs T2 AND T3
                if (!empty($config['t2']['email'])) $needed_approvals[] = $config['t2']['email'];
                if (!empty($config['t3']['email'])) $needed_approvals[] = $config['t3']['email'];

                $assigned_approver = $config['t3']['name'];
            }

            // 4. Create Order
            $order = wc_create_order();
            if (!$order) wp_send_json_error('Failed to create order');

            // Add Products
            foreach ($products as $item) {
                $pid = absint($item['id']);
                $qty = max(1, intval($item['quantity']));
                if ($p = wc_get_product($pid)) {
                    // Note: We add the product at full price now, coupon will discount it later
                    $order->add_product($p, $qty);
                }
            }

            // Set Customer/Billing (Basic implementation of fields)
            $billing_user_id = isset($_POST['billing_user_id']) ? absint($_POST['billing_user_id']) : 0;
            if ($billing_user_id) {
                $order->set_customer_id($billing_user_id);
                $u = get_user_by('id', $billing_user_id);
                if ($u) {
                    $order->set_billing_first_name(get_user_meta($u->ID, 'billing_first_name', true) ?: $u->display_name);
                    $order->set_billing_email($u->user_email);
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

            // --- COUPON APPLICATION START ---
            $coupon_code = 'flat100';
            $result = $order->apply_coupon($coupon_code);

            // Log error if coupon fails, but proceed
            if (is_wp_error($result)) {
                $order->add_order_note('Error applying sample coupon (flat100): ' . $result->get_error_message());
            }

            // Calculate totals (This applies the discount)
            $order->calculate_totals();
            // --- COUPON APPLICATION END ---

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

            // v2 Update: Use system assigned approver, not POST data
            $order->update_meta_data('_approved_by', $assigned_approver);

            // Approval State
            $order->update_meta_data('_wcso_approvals_needed', $needed_approvals);
            $order->update_meta_data('_wcso_approvals_granted', array());

            // Customer Note
            if (!empty($_POST['order_note'])) {
                $order->set_customer_note(sanitize_textarea_field($_POST['order_note']));
            }

            $order->set_status($status);
            $order->save();

            // 6. Trigger Emails
            do_action('wcso_sample_order_created', $order->get_id());

            wp_send_json_success(array(
                'order_id'  => $order->get_id(),
                'order_url' => admin_url('post.php?post=' . $order->get_id() . '&action=edit')
            ));
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}
