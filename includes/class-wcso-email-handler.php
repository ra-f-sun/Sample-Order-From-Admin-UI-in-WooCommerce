<?php
/**
 * Email Notification Handler for Sample Orders
 */
if (!defined('ABSPATH')) exit;

class WCSO_Email_Handler {

    private static $instance = null;

    /**
     * Hardcoded CC emails - Update these as needed
     */
    private $cc_emails = array(
        'manager@wphelpzone.com',
        'admin@wphelpzone.com',
    );

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Hook into order status changes
        add_action('woocommerce_order_status_changed', array($this, 'send_status_change_email'), 10, 4);
        
        // Hook into new sample order creation
        add_action('wcso_sample_order_created', array($this, 'send_new_order_email'), 10, 1);
        
        // Add settings page for CC emails
        add_action('admin_init', array($this, 'register_email_settings'));
        
        // Email logging for testing
        if (get_option('wcso_email_logging', '1') === '1') {
            add_action('wp_mail', array($this, 'log_email'), 10, 1);
            add_filter('wp_mail_failed', array($this, 'log_email_failure'), 10, 1);
        }
    }

    /**
     * Register email settings
     */
    public function register_email_settings() {
        register_setting('wcso_settings', 'wcso_cc_emails');
        register_setting('wcso_settings', 'wcso_email_logging');
        register_setting('wcso_settings', 'wcso_email_from_name');
        register_setting('wcso_settings', 'wcso_email_from_email');
    }

    /**
     * Get CC emails (from settings or default hardcoded)
     */
    private function get_cc_emails() {
        $saved_cc = get_option('wcso_cc_emails', '');
        
        if (!empty($saved_cc)) {
            $emails = array_map('trim', explode(',', $saved_cc));
            return array_filter($emails, 'is_email');
        }
        
        return $this->cc_emails;
    }

    /**
     * Send email when new sample order is created
     */
    public function send_new_order_email($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order || $order->get_meta('_is_sample_order') !== 'yes') {
            return;
        }

        $shipping_email = $order->get_meta('_shipping_email');
        
        if (empty($shipping_email)) {
            $this->log_message('No shipping email found for order #' . $order_id . '. Email not sent.');
            return;
        }

        $subject = sprintf(__('[Sample Order] New Order #%s', 'wc-sample-orders'), $order->get_order_number());
        $message = $this->get_new_order_email_content($order);
        $headers = $this->get_email_headers();
        
        $sent = $this->send_email($shipping_email, $subject, $message, $headers);
        
        if ($sent) {
            $order->add_order_note(
                sprintf(__('Sample order notification sent to: %s', 'wc-sample-orders'), $shipping_email),
                false,
                true
            );
        }
    }

    /**
     * Send email when order status changes
     */
    public function send_status_change_email($order_id, $old_status, $new_status, $order) {
        if (!$order || $order->get_meta('_is_sample_order') !== 'yes') {
            return;
        }

        $shipping_email = $order->get_meta('_shipping_email');
        
        if (empty($shipping_email)) {
            return;
        }

        $subject = sprintf(
            __('[Sample Order] Order #%s Status Changed to %s', 'wc-sample-orders'),
            $order->get_order_number(),
            ucfirst($new_status)
        );
        
        $message = $this->get_status_change_email_content($order, $old_status, $new_status);
        $headers = $this->get_email_headers();
        
        $sent = $this->send_email($shipping_email, $subject, $message, $headers);
        
        if ($sent) {
            $order->add_order_note(
                sprintf(
                    __('Status change notification sent to: %s (Status: %s → %s)', 'wc-sample-orders'),
                    $shipping_email,
                    $old_status,
                    $new_status
                ),
                false,
                true
            );
        }
    }

    /**
     * Build email headers with CC
     */
    private function get_email_headers() {
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        // Custom From name and email
        $from_name = get_option('wcso_email_from_name', get_bloginfo('name'));
        $from_email = get_option('wcso_email_from_email', get_option('admin_email'));
        
        $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
        
        // Add CC emails
        $cc_emails = $this->get_cc_emails();
        foreach ($cc_emails as $cc_email) {
            if (is_email($cc_email)) {
                $headers[] = 'Cc: ' . $cc_email;
            }
        }
        
        return $headers;
    }

    /**
     * Send email wrapper
     */
    private function send_email($to, $subject, $message, $headers) {
        $this->log_message('Attempting to send email to: ' . $to);
        $this->log_message('Subject: ' . $subject);
        $this->log_message('CC: ' . implode(', ', $this->get_cc_emails()));
        
        $sent = wp_mail($to, $subject, $message, $headers);
        
        if ($sent) {
            $this->log_message('✓ Email sent successfully');
        } else {
            $this->log_message('✗ Email sending failed');
        }
        
        return $sent;
    }

    /**
     * Get new order email content
     */
    private function get_new_order_email_content($order) {
        $approved_by = $order->get_meta('_approved_by');
        $created_by = $order->get_meta('_created_by');
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php echo esc_html(get_bloginfo('name')); ?></title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                
                <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                    <h2 style="margin: 0 0 10px 0; color: #856404;">
                        🛒 New Sample Order Received
                    </h2>
                    <p style="margin: 0; color: #856404;">
                        <strong>Order #<?php echo $order->get_order_number(); ?></strong>
                    </p>
                </div>

                <p>Hello,</p>
                <p>A new sample order has been created and is ready for processing.</p>

                <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                    <tr style="background: #f8f9fa;">
                        <td style="padding: 10px; border: 1px solid #ddd;"><strong>Order Number:</strong></td>
                        <td style="padding: 10px; border: 1px solid #ddd;">#<?php echo $order->get_order_number(); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ddd;"><strong>Order Date:</strong></td>
                        <td style="padding: 10px; border: 1px solid #ddd;"><?php echo $order->get_date_created()->format('F j, Y g:i A'); ?></td>
                    </tr>
                    <tr style="background: #f8f9fa;">
                        <td style="padding: 10px; border: 1px solid #ddd;"><strong>Status:</strong></td>
                        <td style="padding: 10px; border: 1px solid #ddd;"><?php echo ucfirst($order->get_status()); ?></td>
                    </tr>
                    <?php if (!empty($approved_by)): ?>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ddd;"><strong>Approved By:</strong></td>
                        <td style="padding: 10px; border: 1px solid #ddd;"><?php echo esc_html($approved_by); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>

                <h3 style="border-bottom: 2px solid #333; padding-bottom: 5px;">Shipping Address</h3>
                <p>
                    <?php echo $order->get_formatted_shipping_address(); ?>
                </p>

                <h3 style="border-bottom: 2px solid #333; padding-bottom: 5px;">Order Items</h3>
                <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                    <thead>
                        <tr style="background: #333; color: white;">
                            <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Product</th>
                            <th style="padding: 10px; border: 1px solid #ddd; text-align: center;">Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order->get_items() as $item): ?>
                        <tr>
                            <td style="padding: 10px; border: 1px solid #ddd;"><?php echo $item->get_name(); ?></td>
                            <td style="padding: 10px; border: 1px solid #ddd; text-align: center;"><?php echo $item->get_quantity(); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="background: #e7f3ff; border-left: 4px solid #2271b1; padding: 15px; margin: 20px 0;">
                    <p style="margin: 0; font-size: 14px;">
                        <strong>Note:</strong> This is a sample order with zero cost. No payment is required.
                    </p>
                </div>

                <?php if ($order->get_customer_note()): ?>
                <h3 style="border-bottom: 2px solid #333; padding-bottom: 5px;">Order Note</h3>
                <p style="background: #f8f9fa; padding: 15px; border-radius: 4px;">
                    <?php echo nl2br(esc_html($order->get_customer_note())); ?>
                </p>
                <?php endif; ?>

                <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">
                
                <p style="font-size: 12px; color: #666;">
                    If you have any questions about this order, please contact us.<br>
                    <strong><?php echo get_bloginfo('name'); ?></strong><br>
                    Email: <?php echo get_option('admin_email'); ?>
                </p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Get status change email content
     */
    private function get_status_change_email_content($order, $old_status, $new_status) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php echo esc_html(get_bloginfo('name')); ?></title>
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                
                <div style="background: #e7f3ff; border: 1px solid #2271b1; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                    <h2 style="margin: 0 0 10px 0; color: #135e96;">
                        📦 Order Status Updated
                    </h2>
                    <p style="margin: 0; color: #135e96;">
                        <strong>Order #<?php echo $order->get_order_number(); ?></strong>
                    </p>
                </div>

                <p>Hello,</p>
                <p>Your sample order status has been updated:</p>

                <div style="background: #f8f9fa; padding: 20px; border-radius: 4px; text-align: center; margin: 20px 0;">
                    <div style="display: inline-block;">
                        <span style="background: #dc3545; color: white; padding: 10px 20px; border-radius: 4px; font-weight: bold;">
                            <?php echo ucfirst($old_status); ?>
                        </span>
                        <span style="margin: 0 10px; font-size: 24px;">→</span>
                        <span style="background: #28a745; color: white; padding: 10px 20px; border-radius: 4px; font-weight: bold;">
                            <?php echo ucfirst($new_status); ?>
                        </span>
                    </div>
                </div>

                <h3 style="border-bottom: 2px solid #333; padding-bottom: 5px;">Order Details</h3>
                <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
                    <tr style="background: #f8f9fa;">
                        <td style="padding: 10px; border: 1px solid #ddd;"><strong>Order Number:</strong></td>
                        <td style="padding: 10px; border: 1px solid #ddd;">#<?php echo $order->get_order_number(); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ddd;"><strong>Order Date:</strong></td>
                        <td style="padding: 10px; border: 1px solid #ddd;"><?php echo $order->get_date_created()->format('F j, Y g:i A'); ?></td>
                    </tr>
                    <tr style="background: #f8f9fa;">
                        <td style="padding: 10px; border: 1px solid #ddd;"><strong>Current Status:</strong></td>
                        <td style="padding: 10px; border: 1px solid #ddd;"><strong><?php echo ucfirst($new_status); ?></strong></td>
                    </tr>
                </table>

                <?php $this->render_status_message($new_status); ?>

                <h3 style="border-bottom: 2px solid #333; padding-bottom: 5px;">Shipping Address</h3>
                <p>
                    <?php echo $order->get_formatted_shipping_address(); ?>
                </p>

                <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">
                
                <p style="font-size: 12px; color: #666;">
                    If you have any questions about this order, please contact us.<br>
                    <strong><?php echo get_bloginfo('name'); ?></strong><br>
                    Email: <?php echo get_option('admin_email'); ?>
                </p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Render status-specific message
     */
    private function render_status_message($status) {
        $messages = array(
            'processing' => array(
                'icon'  => '⏳',
                'title' => 'Order is Being Processed',
                'text'  => 'Your sample order is currently being prepared for shipment.',
                'color' => '#ffc107'
            ),
            'completed' => array(
                'icon'  => '✅',
                'title' => 'Order Completed',
                'text'  => 'Your sample order has been completed and shipped.',
                'color' => '#28a745'
            ),
            'on-hold' => array(
                'icon'  => '⏸️',
                'title' => 'Order On Hold',
                'text'  => 'Your sample order is temporarily on hold. We will contact you if needed.',
                'color' => '#ff9800'
            ),
            'cancelled' => array(
                'icon'  => '❌',
                'title' => 'Order Cancelled',
                'text'  => 'Your sample order has been cancelled.',
                'color' => '#dc3545'
            ),
        );

        $info = isset($messages[$status]) ? $messages[$status] : array(
            'icon'  => 'ℹ️',
            'title' => 'Status Updated',
            'text'  => 'Your order status has been updated to: ' . ucfirst($status),
            'color' => '#2271b1'
        );

        ?>
        <div style="background: <?php echo $info['color']; ?>22; border-left: 4px solid <?php echo $info['color']; ?>; padding: 15px; margin: 20px 0;">
            <h4 style="margin: 0 0 10px 0; color: <?php echo $info['color']; ?>;">
                <?php echo $info['icon']; ?> <?php echo $info['title']; ?>
            </h4>
            <p style="margin: 0;">
                <?php echo $info['text']; ?>
            </p>
        </div>
        <?php
    }

    /**
     * Log email attempt
     */
    public function log_email($args) {
        $this->log_message('wp_mail called with: ' . print_r($args, true));
    }

    /**
     * Log email failure
     */
    public function log_email_failure($error) {
        $this->log_message('Email failed: ' . $error->get_error_message());
        return $error;
    }

    /**
     * Write to log file
     */
    private function log_message($message) {
        if (get_option('wcso_email_logging', '1') !== '1') {
            return;
        }

        $log_file = WCSO_PLUGIN_DIR . 'email-log.txt';
        $timestamp = current_time('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] {$message}\n";
        
        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }

    /**
     * Get email log contents (for testing)
     */
    public function get_email_log() {
        $log_file = WCSO_PLUGIN_DIR . 'email-log.txt';
        
        if (!file_exists($log_file)) {
            return 'No emails logged yet.';
        }
        
        return file_get_contents($log_file);
    }

    /**
     * Clear email log
     */
    public function clear_email_log() {
        $log_file = WCSO_PLUGIN_DIR . 'email-log.txt';
        
        if (file_exists($log_file)) {
            unlink($log_file);
        }
    }
}