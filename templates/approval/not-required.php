<?php

/**
 * Approval Not Required Template
 * 
 * This template displays a message when someone tries to approve but is not a required approver.
 * 
 * @package WPHelpZone\WCSO
 * 
 * Available variables:
 * @var string $email    The email address that attempted approval
 * @var int    $order_id The order ID
 */

if (! defined('ABSPATH')) {
    exit;
}
?>
<h1>Approval Not Required</h1>
<p>The email <strong><?php echo esc_html($email); ?></strong> is not listed as a required approver for Order #<?php echo esc_html($order_id); ?>.</p>