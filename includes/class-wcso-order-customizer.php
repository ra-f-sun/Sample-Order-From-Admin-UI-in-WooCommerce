<?php

/**
 * Order Details Page Customizations
 */
if (!defined('ABSPATH')) exit;

class WCSO_Order_Customizer
{

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct()
    {
        // Add shipping email field to order details
        add_filter('woocommerce_admin_shipping_fields', array($this, 'add_shipping_email_field'));

        // Display shipping email in order details
        add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'display_shipping_email'));

        // Save shipping email when order is updated
        add_action('woocommerce_process_shop_order_meta', array($this, 'save_shipping_email'), 10, 2);

        // Add sample order info to order details
        // add_action('woocommerce_admin_order_data_after_order_details', array($this, 'display_sample_order_info'));

        // Modify order totals display for sample orders
        add_filter('woocommerce_get_formatted_order_total', array($this, 'format_sample_order_total'), 10, 2);
    }

    /**
     * Add shipping email field to admin shipping fields
     */
    public function add_shipping_email_field($fields)
    {
        $fields['email'] = array(
            'label' => __('Email address', 'wc-sample-orders'),
            'show'  => false,
        );
        return $fields;
    }

    /**
     * Display shipping email in order details page
     */
    public function display_shipping_email($order)
    {
        $shipping_email = $order->get_meta('_shipping_email');
        if (!empty($shipping_email)) {
            echo '<p class="form-field form-field-wide">';
            echo '<strong>' . __('Shipping Email:', 'wc-sample-orders') . '</strong><br>';
            echo '<a href="mailto:' . esc_attr($shipping_email) . '">' . esc_html($shipping_email) . '</a>';
            echo '</p>';
        }
    }

    /**
     * Save shipping email when order is updated manually
     */
    public function save_shipping_email($post_id, $post)
    {
        if (isset($_POST['_shipping_email'])) {
            $order = wc_get_order($post_id);
            if ($order) {
                $shipping_email = sanitize_email($_POST['_shipping_email']);
                if (!empty($shipping_email)) {
                    $order->update_meta_data('_shipping_email', $shipping_email);
                } else {
                    $order->delete_meta_data('_shipping_email');
                }
                $order->save();
            }
        }
    }

    /**
     * Format order total display for sample orders
     */
    public function format_sample_order_total($formatted_total, $order)
    {
        if ($order->get_meta('_is_sample_order') === 'yes') {
            return '<span style="color: #46b450; font-weight: bold;">' . wc_price(0) . '</span> <small style="color: #666;">(' . __('Sample Order', 'wc-sample-orders') . ')</small>';
        }
        return $formatted_total;
    }
}
