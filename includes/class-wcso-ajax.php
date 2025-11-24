<?php
/**
 * AJAX Handlers
 */
if (!defined('ABSPATH')) exit;

class WCSO_Ajax {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_ajax_wcso_search_products', array($this, 'search_products'));
        add_action('wp_ajax_wcso_get_all_products', array($this, 'get_all_products'));
        add_action('wp_ajax_wcso_create_order', array($this, 'create_sample_order'));
    }

    public function get_all_products() {
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

    public function search_products() {
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
                'meta_query'  => array(array('key' => '_sku','value' => $term,'compare' => 'LIKE'))
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
                'meta_query'  => array(array('key' => '_sku','value' => $term,'compare' => 'LIKE'))
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

    public function create_sample_order() {
        check_ajax_referer('wcso_create_order', 'nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('Unauthorized');

        try {
            // Validate products & approval
            $products    = is_array($_POST['products']) ? $_POST['products'] : json_decode(stripslashes($_POST['products'] ?? '[]'), true);
            $approved_by = sanitize_text_field($_POST['approved_by'] ?? '');
            if (empty($products)) wp_send_json_error('No products selected');
            if (empty($approved_by)) wp_send_json_error('Approved By is required');

            $order = wc_create_order();
            if (!$order) wp_send_json_error('Failed to create order');

            // Add products with quantities
            $original_total = 0;
            foreach ($products as $item) {
                $pid = isset($item['id']) ? absint($item['id']) : 0;
                $qty = isset($item['quantity']) ? max(1, intval($item['quantity'])) : 1;
                if (!$pid) continue;
                $product = wc_get_product($pid);
                if ($product) {
                    // Add product with zero price to prevent financial impact
                    $order->add_product($product, $qty);
                    $original_total += floatval($product->get_price()) * $qty;
                }
            }

            // Billing user (attach and hydrate billing fields)
            $billing_user_id = isset($_POST['billing_user_id']) ? absint($_POST['billing_user_id']) : 0;
            if ($billing_user_id) {
                $order->set_customer_id($billing_user_id);
                $u = get_user_by('id', $billing_user_id);
                if ($u) {
                    // Use user's display name parts for billing first/last name
                    $display_name = $u->display_name;
                    $name_parts = explode(' ', $display_name, 2);
                    $billing_first = isset($name_parts[0]) ? $name_parts[0] : $display_name;
                    $billing_last = isset($name_parts[1]) ? $name_parts[1] : '';
                    
                    // Fallback to user meta if available
                    if (empty($billing_first)) {
                        $billing_first = get_user_meta($u->ID, 'billing_first_name', true) ?: get_user_meta($u->ID, 'first_name', true);
                    }
                    if (empty($billing_last)) {
                        $billing_last = get_user_meta($u->ID, 'billing_last_name', true) ?: get_user_meta($u->ID, 'last_name', true);
                    }
                    
                    $order->set_billing_first_name($billing_first);
                    $order->set_billing_last_name($billing_last);
                    $order->set_billing_company(get_user_meta($u->ID, 'billing_company', true));
                    $order->set_billing_address_1(get_user_meta($u->ID, 'billing_address_1', true));
                    $order->set_billing_address_2(get_user_meta($u->ID, 'billing_address_2', true));
                    $order->set_billing_city(get_user_meta($u->ID, 'billing_city', true));
                    $order->set_billing_state(get_user_meta($u->ID, 'billing_state', true));
                    $order->set_billing_postcode(get_user_meta($u->ID, 'billing_postcode', true));
                    $order->set_billing_country(get_user_meta($u->ID, 'billing_country', true));
                    $order->set_billing_phone(get_user_meta($u->ID, 'billing_phone', true));
                    
                    // Set billing email from user
                    $order->set_billing_email($u->user_email);
                }
            }

            // Validate required shipping fields
            $required = array('shipping_first_name','shipping_last_name','shipping_country','shipping_address_1','shipping_city');
            foreach ($required as $k) {
                if (empty($_POST[$k])) wp_send_json_error('Missing required field: ' . esc_html(str_replace('_',' ', $k)));
            }
            $cc = sanitize_text_field($_POST['shipping_country'] ?? '');
            $states_all = WC()->countries->get_states($cc);
            if (is_array($states_all) && !empty($states_all) && empty($_POST['shipping_state'])) {
                wp_send_json_error('State/District is required for the selected country.');
            }

            // Shipping mapping
            $order->set_shipping_first_name(sanitize_text_field($_POST['shipping_first_name'] ?? ''));
            $order->set_shipping_last_name(sanitize_text_field($_POST['shipping_last_name'] ?? ''));
            $order->set_shipping_company(sanitize_text_field($_POST['shipping_company'] ?? ''));
            $order->set_shipping_country(sanitize_text_field($_POST['shipping_country'] ?? ''));
            $order->set_shipping_address_1(sanitize_text_field($_POST['shipping_address_1'] ?? ''));
            $order->set_shipping_address_2(sanitize_text_field($_POST['shipping_address_2'] ?? ''));
            $order->set_shipping_city(sanitize_text_field($_POST['shipping_city'] ?? ''));
            $order->set_shipping_state(sanitize_text_field($_POST['shipping_state'] ?? ''));
            $order->set_shipping_postcode(sanitize_text_field($_POST['shipping_postcode'] ?? ''));

            // Set shipping email if provided
            $shipping_email = isset($_POST['shipping_email']) ? sanitize_email($_POST['shipping_email']) : '';
            if (!empty($shipping_email)) {
                $order->update_meta_data('_shipping_email', $shipping_email);
            }

            // Preserve shipping phone as meta
            if (!empty($_POST['shipping_phone'])) {
                $order->update_meta_data('_shipping_phone', sanitize_text_field($_POST['shipping_phone']));
            }

            
            
            $shipping_item = new WC_Order_Item_Shipping();
    
            $shipping_item->set_method_title(sanitize_text_field($_POST['shipping_method_title']));
            $shipping_item->set_method_id(sanitize_text_field($_POST['shipping_method_id']));
            $shipping_item->set_total(sanitize_text_field($_POST['shipping_method_cost']));
            $shipping_item->set_tax_status('none');

            $order->add_item( $shipping_item );

            $order->apply_coupon('flat100');
            
            $order->calculate_totals();
            

            // Meta
            $order->update_meta_data('_is_sample_order', 'yes');
            $order->update_meta_data('_approved_by', $approved_by);
            $order->update_meta_data('_created_by', wp_get_current_user()->user_login);
            $order->update_meta_data('_original_total', $original_total);

            // Set customer note (appears in "Customer provided note" field)
            if (!empty($_POST['order_note'])) {
                $note_text = sanitize_textarea_field($_POST['order_note']);
                $order_note = sprintf(
                    __('Sample order created by %s. Approved by: %s. Original total: %s', 'wc-sample-orders'),
                    wp_get_current_user()->user_login,
                    $approved_by,
                    $original_total
                );
                $order->set_customer_note($note_text.'...'.$order_note);
            }

            // Add system note indicating it's a sample order
            // $order->add_order_note(
                // sprintf(
                //     __('Sample order created by %s. Approved by: %s. Original total: %s', 'wc-sample-orders'),
                //     wp_get_current_user()->user_login,
                //     $approved_by,
                //     wc_price($original_total)
                // ),
            //     false,
            //     true
            // );

            $order->set_status('processing');
            
            $order->save();

            // Trigger email notification for new sample order
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