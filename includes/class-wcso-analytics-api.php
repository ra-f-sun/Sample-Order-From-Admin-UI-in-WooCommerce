<?php
if (!defined('ABSPATH')) exit;

class WCSO_Analytics_API
{
    private static $instance = null;
    private $table_name;

    public static function get_instance()
    {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wcso_analytics_cache';

        // 1. Endpoint for Charts (Aggregated Data)
        add_action('wp_ajax_wcso_get_analytics_data', array($this, 'get_analytics_data'));

        // 2. Endpoint for Drill-Down (Specific Order List)
        add_action('wp_ajax_wcso_get_analytics_drilldown', array($this, 'get_drilldown_data'));
    }

    /**
     * Returns aggregated data for the 5 Charts
     */
    public function get_analytics_data()
    {
        check_ajax_referer('wcso_analytics', 'nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('Unauthorized');

        global $wpdb;

        // Default to last 30 days
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : date('Y-m-d');
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : date('Y-m-d', strtotime('-30 days'));

        $start_query = $start_date . ' 00:00:00';
        $end_query = $end_date . ' 23:59:59';

        // QUERY A: By Category (for Volume & Spend Charts)
        $by_category = $wpdb->get_results($wpdb->prepare("
            SELECT category as name, COUNT(id) as count, SUM(total_amount) as value
            FROM $this->table_name
            WHERE created_at BETWEEN %s AND %s
            GROUP BY category
            ORDER BY value DESC
        ", $start_query, $end_query), ARRAY_A);

        // QUERY B: By Date (for Trend & Success Charts)
        $by_date = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(created_at) as date, 
                COUNT(id) as count, 
                SUM(total_amount) as amount,
                SUM(CASE WHEN status IN ('completed', 'processing') THEN 1 ELSE 0 END) as success,
                SUM(CASE WHEN status IN ('cancelled', 'failed', 'refunded') THEN 1 ELSE 0 END) as failed
            FROM $this->table_name
            WHERE created_at BETWEEN %s AND %s
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ", $start_query, $end_query), ARRAY_A);

        $response = array(
            'volume_by_category' => $by_category,
            'spend_by_category'  => $by_category,
            'trends'             => $by_date,
            'success_rate'       => $by_date
        );

        wp_send_json_success($response);
    }

    /**
     * Returns list of orders for the Modal when a user clicks a chart
     */
    public function get_drilldown_data()
    {
        check_ajax_referer('wcso_analytics', 'nonce');
        if (!current_user_can('manage_woocommerce')) wp_send_json_error('Unauthorized');

        global $wpdb;

        $type = sanitize_text_field($_POST['filter_type']); // 'category' or 'date'
        $value = sanitize_text_field($_POST['filter_value']); // e.g. 'Customer Service' or '2023-10-25'

        $sql = "SELECT order_id, category, tier, status, total_amount, created_at FROM $this->table_name WHERE ";

        if ($type === 'category') {
            $sql .= $wpdb->prepare("category = %s", $value);
        } elseif ($type === 'date') {
            $sql .= $wpdb->prepare("DATE(created_at) = %s", $value);
        } else {
            wp_send_json_error('Invalid filter');
        }

        $sql .= " ORDER BY id DESC LIMIT 100"; // Limit to prevent overload

        $orders = $wpdb->get_results($sql, ARRAY_A);

        // Add Edit Links
        foreach ($orders as &$order) {
            $order['edit_url'] = admin_url('post.php?post=' . $order['order_id'] . '&action=edit');
            $order['formatted_date'] = date('M j, Y', strtotime($order['created_at']));
        }

        wp_send_json_success($orders);
    }
}
