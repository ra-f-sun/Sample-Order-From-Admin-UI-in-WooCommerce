<?php

/**
 * REST API Controller Class
 *
 * @package WPHelpZone\WCSO
 */

namespace WPHelpZone\WCSO;

use WPHelpZone\WCSO\Abstracts\WCSO_Singleton;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * WCSO REST API Class
 *
 * Handles all REST API endpoints for the plugin.
 */
class WCSO_Rest_API extends WCSO_Singleton
{

    /**
     * API namespace
     *
     * @var string
     */
    private $namespace = 'wcso/v1';

    /**
     * Initialize the class
     *
     * @return void
     */
    protected function init()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register all REST API routes
     *
     * @return void
     */
    public function register_routes()
    {
        // Products endpoints
        register_rest_route(
            $this->namespace,
            '/products/search',
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'search_products'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => array(
                    'term' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/products',
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'get_all_products'),
                'permission_callback' => array($this, 'check_permissions'),
            )
        );

        // Orders endpoint
        register_rest_route(
            $this->namespace,
            '/orders',
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'create_order'),
                'permission_callback' => array($this, 'check_permissions'),
            )
        );

        // Settings endpoint
        register_rest_route(
            $this->namespace,
            '/settings',
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'save_settings'),
                'permission_callback' => array($this, 'check_permissions'),
            )
        );

        // Logs endpoints
        register_rest_route(
            $this->namespace,
            '/logs/email',
            array(
                array(
                    'methods'             => 'GET',
                    'callback'            => array($this, 'get_email_log'),
                    'permission_callback' => array($this, 'check_permissions'),
                ),
                array(
                    'methods'             => 'DELETE',
                    'callback'            => array($this, 'clear_email_log'),
                    'permission_callback' => array($this, 'check_permissions'),
                ),
            )
        );

        // Analytics endpoints
        register_rest_route(
            $this->namespace,
            '/analytics',
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'get_analytics_data'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => array(
                    'start_date' => array(
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'end_date'   => array(
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/analytics/drilldown',
            array(
                'methods'             => 'GET',
                'callback'            => array($this, 'get_drilldown_data'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => array(
                    'category'      => array(
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'date'          => array(
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'status_filter' => array(
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );

        register_rest_route(
            $this->namespace,
            '/analytics/backfill',
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'analytics_backfill'),
                'permission_callback' => array($this, 'check_permissions'),
            )
        );
    }

    /**
     * Check if user has permission to access endpoints
     *
     * @return bool|WP_Error
     */
    public function check_permissions()
    {
        if (! current_user_can('manage_woocommerce')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access this endpoint.', 'wc-sample-orders'),
                array('status' => 403)
            );
        }
        return true;
    }

    /**
     * Search products by term
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function search_products($request)
    {
        $term   = $request->get_param('term');
        $is_num = is_numeric($term);

        $products = $this->search_products_by_term($term, $is_num);
        $products_data = $this->format_product_data($products);

        return new WP_REST_Response($products_data, 200);
    }

    /**
     * Search products by term using ID, SKU, or title
     *
     * @param string $term   The search term.
     * @param bool   $is_num Whether the term is numeric.
     * @return array Array of WP_Post objects.
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
     * Format product data for response
     *
     * @param array $products Array of WP_Post objects.
     * @return array Formatted product data.
     */
    private function format_product_data($products)
    {
        $seen          = array();
        $products_data = array();

        foreach ($products as $post) {
            if (in_array($post->ID, $seen)) {
                continue;
            }
            $seen[]         = $post->ID;
            $single_product = wc_get_product($post->ID);
            if (! $single_product) {
                continue;
            }
            $status          = ($post->post_status !== 'publish') ? $post->post_status : $single_product->get_catalog_visibility();
            $products_data[] = array(
                'id'         => $single_product->get_id(),
                'name'       => $single_product->get_name(),
                'sku'        => $single_product->get_sku(),
                'status'     => $status,
                'price'      => $single_product->get_price(),
                'price_html' => $single_product->get_price() . '/-',
            );
        }

        return $products_data;
    }

    /**
     * Get all products
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_all_products($request)
    {
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

        $products_data = array();
        foreach ($all_product_ids as $product_id) {
            $product = wc_get_product($product_id);
            if (! $product) {
                continue;
            }
            $post   = get_post($product_id);
            $status = ($post->post_status !== 'publish') ? $post->post_status : $product->get_catalog_visibility();

            $products_data[] = array(
                'id'         => $product->get_id(),
                'name'       => $product->get_name(),
                'sku'        => $product->get_sku(),
                'status'     => $status,
                'price'      => $product->get_price(),
                'price_html' => $product->get_price() . '/-',
            );
        }

        return new WP_REST_Response($products_data, 200);
    }

    /**
     * Create sample order
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function create_order($request)
    {
        try {
            $params   = $request->get_json_params();
            $products = isset($params['products']) ? $params['products'] : array();
            $category = isset($params['sample_category']) ? sanitize_text_field($params['sample_category']) : '';

            if (empty($products)) {
                return new WP_Error(
                    'no_products',
                    __('No products selected', 'wc-sample-orders'),
                    array('status' => 400)
                );
            }

            // Calculate order total and determine tier/approval logic.
            $original_total = $this->calculate_order_total($products);
            $config         = WCSO_Settings::get_tier_config();
            $tier_data      = $this->determine_tier_and_status($original_total, $config);

            // Create and configure the order.
            $order = wc_create_order();
            if (! $order) {
                return new WP_Error(
                    'order_creation_failed',
                    __('Failed to create order', 'wc-sample-orders'),
                    array('status' => 500)
                );
            }

            $this->add_products_to_order($order, $products);
            $this->set_order_billing($order, isset($params['billing_user_id']) ? absint($params['billing_user_id']) : 0);
            $this->set_order_shipping($order, $params);
            $this->add_shipping_method_to_order($order, $params);
            $this->apply_coupon_to_order($order);

            // Save all order metadata.
            $meta_data = array(
                'original_total'    => $original_total,
                'tier'              => $tier_data['tier'],
                'category'          => $category,
                'assigned_approver' => $tier_data['assigned_approver'],
                'needed_approvals'  => $tier_data['needed_approvals'],
                'order_note'        => isset($params['order_note']) ? sanitize_textarea_field($params['order_note']) : '',
            );
            $this->save_order_meta($order, $meta_data);

            $order->set_status($tier_data['status']);
            $order->save();

            // Trigger action hook for emails.
            do_action('wcso_sample_order_created', $order->get_id());

            return new WP_REST_Response(
                array(
                    'order_id'  => $order->get_id(),
                    'order_url' => admin_url('post.php?post=' . $order->get_id() . '&action=edit'),
                ),
                201
            );
        } catch (\Exception $e) {
            return new WP_Error(
                'order_creation_error',
                $e->getMessage(),
                array('status' => 500)
            );
        }
    }

    /**
     * Calculate order total from products
     *
     * @param array $products Products array.
     * @return float
     */
    private function calculate_order_total($products)
    {
        $total = 0;
        foreach ($products as $item) {
            $product_id = isset($item['id']) ? absint($item['id']) : 0;
            $quantity   = max(1, intval($item['quantity']));
            $product    = wc_get_product($product_id);
            if ($product) {
                $total += floatval($product->get_price()) * $quantity;
            }
        }
        return $total;
    }

    /**
     * Determine tier and status
     *
     * @param float $total  Order total.
     * @param array $config Tier config.
     * @return array
     */
    private function determine_tier_and_status($total, $config)
    {
        $tier              = '';
        $status            = 'processing';
        $needed_approvals  = array();
        $assigned_approver = '';

        if ($total <= $config['t1']['limit']) {
            $tier              = 't1so';
            $assigned_approver = $config['t1']['name'];
        } elseif ($total <= $config['t2']['limit']) {
            $tier               = 't2so';
            $status             = 'on-hold';
            $needed_approvals[] = $config['t2']['email'];
            $assigned_approver  = $config['t2']['name'];
        } else {
            $tier   = 't3so';
            $status = 'on-hold';
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
     * Add products to order
     *
     * @param \WC_Order $order    Order object.
     * @param array     $products Products array.
     * @return void
     */
    private function add_products_to_order($order, $products)
    {
        foreach ($products as $item) {
            $product_id = absint($item['id']);
            $quantity   = max(1, intval($item['quantity']));
            $product    = wc_get_product($product_id);
            if ($product) {
                $order->add_product($product, $quantity);
            }
        }
    }

    /**
     * Set order billing
     *
     * @param \WC_Order $order   Order object.
     * @param int       $user_id User ID.
     * @return void
     */
    private function set_order_billing($order, $user_id)
    {
        if ($user_id) {
            $order->set_customer_id($user_id);
            $user = get_user_by('id', $user_id);
            if ($user) {
                $order->set_billing_first_name(get_user_meta($user->ID, 'billing_first_name', true) ?: $user->display_name);
                $order->set_billing_email($user->user_email);
            }
        }
    }

    /**
     * Set order shipping
     *
     * @param \WC_Order $order Order object.
     * @param array     $data  Shipping data.
     * @return void
     */
    private function set_order_shipping($order, $data)
    {
        $order->set_shipping_first_name(isset($data['shipping_first_name']) ? sanitize_text_field($data['shipping_first_name']) : '');
        $order->set_shipping_last_name(isset($data['shipping_last_name']) ? sanitize_text_field($data['shipping_last_name']) : '');
        $order->set_shipping_company(isset($data['shipping_company']) ? sanitize_text_field($data['shipping_company']) : '');
        $order->set_shipping_country(isset($data['shipping_country']) ? sanitize_text_field($data['shipping_country']) : '');
        $order->set_shipping_address_1(isset($data['shipping_address_1']) ? sanitize_text_field($data['shipping_address_1']) : '');
        $order->set_shipping_address_2(isset($data['shipping_address_2']) ? sanitize_text_field($data['shipping_address_2']) : '');
        $order->set_shipping_city(isset($data['shipping_city']) ? sanitize_text_field($data['shipping_city']) : '');
        $order->set_shipping_state(isset($data['shipping_state']) ? sanitize_text_field($data['shipping_state']) : '');
        $order->set_shipping_postcode(isset($data['shipping_postcode']) ? sanitize_text_field($data['shipping_postcode']) : '');

        if (! empty($data['shipping_email'])) {
            $order->update_meta_data('_shipping_email', sanitize_email($data['shipping_email']));
        }
        if (! empty($data['shipping_phone'])) {
            $order->update_meta_data('_shipping_phone', sanitize_text_field($data['shipping_phone']));
        }
    }

    /**
     * Add shipping method to order
     *
     * @param \WC_Order $order Order object.
     * @param array     $data  Shipping method data.
     * @return void
     */
    private function add_shipping_method_to_order($order, $data)
    {
        $shipping_item = new \WC_Order_Item_Shipping();
        $shipping_item->set_method_title(isset($data['shipping_method_title']) ? sanitize_text_field($data['shipping_method_title']) : '');
        $shipping_item->set_method_id(isset($data['shipping_method_id']) ? sanitize_text_field($data['shipping_method_id']) : '');
        $shipping_item->set_total(isset($data['shipping_method_cost']) ? sanitize_text_field($data['shipping_method_cost']) : 0);
        $shipping_item->set_instance_id(isset($data['shipping_method_instance_id']) ? sanitize_text_field($data['shipping_method_instance_id']) : '');
        $order->add_item($shipping_item);
    }

    /**
     * Apply coupon to order
     *
     * @param \WC_Order $order Order object.
     * @return void
     */
    private function apply_coupon_to_order($order)
    {
        $coupon_code = get_option('wcso_coupon_code', 'flat100');

        if (! empty($coupon_code)) {
            $result = $order->apply_coupon($coupon_code);
            if (is_wp_error($result)) {
                $order->add_order_note('Error applying sample coupon (' . $coupon_code . '): ' . $result->get_error_message());
            }
        }

        $order->calculate_totals();
    }

    /**
     * Save order metadata
     *
     * @param \WC_Order $order Order object.
     * @param array     $meta  Metadata array.
     * @return void
     */
    private function save_order_meta($order, $meta)
    {
        $current_user = wp_get_current_user();
        $roles        = $current_user->roles;
        $role_display = ! empty($roles) ? ucfirst($roles[0]) : 'User';
        $origin       = $current_user->display_name . ' (' . $role_display . ')';

        $order->update_meta_data('_is_sample_order', 'yes');
        $order->update_meta_data('_original_total', $meta['original_total']);
        $order->update_meta_data('_wcso_tier', $meta['tier']);
        $order->update_meta_data('_wcso_sample_category', $meta['category']);
        $order->update_meta_data('_wcso_origin', $origin);
        $order->update_meta_data('_approved_by', $meta['assigned_approver']);
        $order->update_meta_data('_wcso_approvals_needed', $meta['needed_approvals']);
        $order->update_meta_data('_wcso_approvals_granted', array());

        if (! empty($meta['order_note'])) {
            $order->set_customer_note($meta['order_note']);
        }
    }

    /**
     * Save settings
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function save_settings($request)
    {
        $data = $request->get_json_params();

        if (empty($data['settings'])) {
            return new WP_Error(
                'missing_settings',
                __('Settings data is required', 'wc-sample-orders'),
                array('status' => 400)
            );
        }

        $settings = $data['settings'];

        // Save general settings.
        update_option('wcso_enable_barcode_scanner', sanitize_text_field($settings['barcode_scanner']));
        update_option('wcso_coupon_code', sanitize_text_field($settings['coupon_code']));
        update_option('wcso_email_logging', sanitize_text_field($settings['email_logging']));

        // Save tier settings.
        $tiers = $settings['tiers'];
        update_option('wcso_t1_name', sanitize_text_field($tiers['t1']['name']));
        update_option('wcso_t1_limit', absint($tiers['t1']['limit']));
        update_option('wcso_t2_name', sanitize_text_field($tiers['t2']['name']));
        update_option('wcso_t2_limit', absint($tiers['t2']['limit']));
        update_option('wcso_t2_email', sanitize_email($tiers['t2']['email']));
        update_option('wcso_t3_name', sanitize_text_field($tiers['t3']['name']));
        update_option('wcso_t3_limit', absint($tiers['t3']['limit']));
        update_option('wcso_t3_email', sanitize_email($tiers['t3']['email']));

        return new WP_REST_Response(
            array('message' => 'Settings saved successfully.'),
            200
        );
    }

    /**
     * Get email log
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_email_log($request)
    {
        $content = WCSO_Email_Handler::get_instance()->get_email_log();
        return new WP_REST_Response(array('content' => $content), 200);
    }

    /**
     * Clear email log
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function clear_email_log($request)
    {
        WCSO_Email_Handler::get_instance()->clear_email_log();
        return new WP_REST_Response(
            array('message' => 'Log cleared.'),
            200
        );
    }

    /**
     * Get analytics data
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_analytics_data($request)
    {
        $end_date   = $request->get_param('end_date') ?: date('Y-m-d');
        $start_date = $request->get_param('start_date') ?: date('Y-m-d', strtotime('-30 days'));

        $start_query = $start_date . ' 00:00:00';
        $end_query   = $end_date . ' 23:59:59';

        $by_category = $this->get_category_aggregates($start_query, $end_query);
        $by_date     = $this->get_date_trends($start_query, $end_query);

        $response = array(
            'volume_by_category' => $by_category,
            'spend_by_category'  => $by_category,
            'trends'             => $by_date,
            'success_rate'       => $by_date,
        );

        return new WP_REST_Response($response, 200);
    }

    /**
     * Get category aggregates
     *
     * @param string $start_query Start datetime.
     * @param string $end_query   End datetime.
     * @return array
     */
    private function get_category_aggregates($start_query, $end_query)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcso_analytics_cache';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT category as name, COUNT(id) as count, SUM(total_amount) as value
                FROM $table_name
                WHERE created_at BETWEEN %s AND %s
                GROUP BY category
                ORDER BY value DESC",
                $start_query,
                $end_query
            ),
            ARRAY_A
        );

        return $results;
    }

    /**
     * Get date trends
     *
     * @param string $start_query Start datetime.
     * @param string $end_query   End datetime.
     * @return array
     */
    private function get_date_trends($start_query, $end_query)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcso_analytics_cache';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    DATE(created_at) as date, 
                    COUNT(id) as count, 
                    SUM(total_amount) as amount,
                    SUM(CASE WHEN status IN ('completed', 'processing') THEN 1 ELSE 0 END) as success,
                    SUM(CASE WHEN status IN ('cancelled', 'failed', 'refunded') THEN 1 ELSE 0 END) as failed
                FROM $table_name
                WHERE created_at BETWEEN %s AND %s
                GROUP BY DATE(created_at)
                ORDER BY date ASC",
                $start_query,
                $end_query
            ),
            ARRAY_A
        );

        return $results;
    }

    /**
     * Get drilldown data
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_drilldown_data($request)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcso_analytics_cache';

        $category      = $request->get_param('category');
        $date          = $request->get_param('date');
        $status_filter = $request->get_param('status_filter');

        $where_clauses = array('1=1');
        $params        = array();

        if (! empty($category)) {
            $where_clauses[] = 'category = %s';
            $params[]        = $category;
        }

        if (! empty($date)) {
            $where_clauses[] = 'DATE(created_at) = %s';
            $params[]        = $date;
        }

        if (! empty($status_filter)) {
            if ($status_filter === 'success') {
                $where_clauses[] = "status IN ('completed', 'processing')";
            } elseif ($status_filter === 'failed') {
                $where_clauses[] = "status IN ('cancelled', 'failed', 'refunded')";
            }
        }

        $where_sql = implode(' AND ', $where_clauses);
        $sql       = "SELECT * FROM $table_name WHERE $where_sql ORDER BY created_at DESC";

        if (! empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $orders = $wpdb->get_results($sql, ARRAY_A);

        return new WP_REST_Response($orders, 200);
    }

    /**
     * Analytics backfill
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public function analytics_backfill($request)
    {
        $count = WCSO_Analytics_DB::get_instance()->backfill_data();
        return new WP_REST_Response(
            array('message' => "Successfully indexed {$count} orders for analytics."),
            200
        );
    }
}
