<?php

/**
 * Approval Request Email Template
 * 
 * This template renders the email sent to approvers requesting approval for a sample order.
 * 
 * @package WPHelpZone\WCSO
 * 
 * Available variables:
 * @var string $tier          Tier code (t1so, t2so, t3so)
 * @var int    $order_id      Order ID
 * @var string $created_by    Order creator name/email
 * @var string $total         Order total value
 * @var string $approve_link  Approval action URL
 * @var string $reject_link   Rejection action URL
 */

if (! defined('ABSPATH')) {
    exit;
}

$tier_display = strtoupper(str_replace('so', '', $tier));
?>
<h2>Approval Required</h2>
<p>A Tier <?php echo esc_html($tier_display); ?> sample order requires your approval.</p>
<p><strong>Created By:</strong> <?php echo esc_html($created_by); ?></p>
<p><strong>Total Value:</strong> <?php echo esc_html($total); ?></p>
<div style="margin:20px 0;">
    <a href="<?php echo esc_url($approve_link); ?>" style="background:green;color:white;padding:10px 15px;text-decoration:none;margin-right:10px;">APPROVE</a>
    <a href="<?php echo esc_url($reject_link); ?>" style="background:red;color:white;padding:10px 15px;text-decoration:none;">REJECT</a>
</div>
<p><small>Clicking these links simulates a login-free approval action.</small></p>