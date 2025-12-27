<?php

/**
 * Order Confirmation Email Template
 * 
 * This template renders the email sent to customers when their sample order is received.
 * 
 * @package WPHelpZone\WCSO
 * 
 * Available variables:
 * @var int    $order_id    Order ID
 * @var string $status_msg  Order status message
 * @var string $total       Order total value
 */

if (! defined('ABSPATH')) {
    exit;
}
?>
<h2>Sample Order Received</h2>
<p>Your order #<?php echo esc_html($order_id); ?> has been placed.</p>
<p><strong>Current Status:</strong> <?php echo esc_html($status_msg); ?></p>
<p><strong>Total Value:</strong> <?php echo esc_html($total); ?></p>