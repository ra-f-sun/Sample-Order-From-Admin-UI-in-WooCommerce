<?php

/**
 * Analytics DB Class
 *
 * @package WPHelpZone\WCSO
 */

namespace WPHelpZone\WCSO;

use WPHelpZone\WCSO\Abstracts\WCSO_Singleton;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * WCSO Analytics DB Class
 *
 * Handles analytics data storage and updates.
 */
class WCSO_Analytics_DB extends WCSO_Singleton
{

    /**
     * Analytics table name
     *
     * @var string
     */
    private $table_name;

    /**
     * Initialize the class
     *
     * @return void
     */
    protected function init()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wcso_analytics_cache';

        // Listen for new sample orders.
        add_action('wcso_sample_order_created', array($this, 'record_new_order'));

        // Listen for status changes (e.g. Processing -> Completed, or Cancelled).
        add_action('woocommerce_order_status_changed', array($this, 'update_order_status'), 10, 3);
    }

    /**
     * Create the custom table.
     * Run this on plugin activation.
     *
     * @return void
     */
    public function create_table()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $this->table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            user_id bigint(20) NOT NULL,
            category varchar(100) NOT NULL,
            tier varchar(20) NOT NULL,
            status varchar(50) NOT NULL,
            total_amount decimal(10,2) NOT NULL DEFAULT 0.00,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY order_id (order_id),
            KEY created_at (created_at),
            KEY status (status)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Insert a new order into the analytics table
     *
     * @param int $order_id Order ID.
     * @return void
     */
    public function record_new_order($order_id)
    {
        global $wpdb;
        $order = wc_get_order($order_id);
        if (! $order) {
            return;
        }

        // Ensure it's a sample order.
        if ($order->get_meta('_is_sample_order') !== 'yes') {
            return;
        }

        $wpdb->replace(
            $this->table_name,
            array(
                'order_id'     => $order->get_id(),
                'user_id'      => $order->get_customer_id(),
                'category'     => $order->get_meta('_wcso_sample_category'),
                'tier'         => $order->get_meta('_wcso_tier'),
                'status'       => $order->get_status(),
                'total_amount' => $order->get_meta('_original_total'),
                'created_at'   => $order->get_date_created()->date('Y-m-d H:i:s'),
            ),
            array('%d', '%d', '%s', '%s', '%s', '%f', '%s')
        );
    }

    /**
     * Sync status changes (Approved, Rejected, Completed)
     *
     * @param int    $order_id   Order ID.
     * @param string $old_status Old status.
     * @param string $new_status New status.
     * @return void
     */
    public function update_order_status($order_id, $old_status, $new_status)
    {
        global $wpdb;

        // Quick check if this order exists in our analytics table.
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM $this->table_name WHERE order_id = %d", $order_id));

        if ($exists) {
            $wpdb->update(
                $this->table_name,
                array('status' => $new_status),
                array('order_id' => $order_id),
                array('%s'),
                array('%d')
            );
        } else {
            // If it's a sample order but missing from our table (legacy?), add it now.
            $this->record_new_order($order_id);
        }
    }

    /**
     * Helper: Backfill all existing orders
     * Useful for the initial setup button
     *
     * @return int Number of orders backfilled.
     */
    public function backfill_data()
    {
        $orders = wc_get_orders(
            array(
                'limit'      => -1,
                'meta_key'   => '_is_sample_order',
                'meta_value' => 'yes',
            )
        );

        $count = 0;
        foreach ($orders as $order) {
            $this->record_new_order($order->get_id());
            $count++;
        }
        return $count;
    }
}
