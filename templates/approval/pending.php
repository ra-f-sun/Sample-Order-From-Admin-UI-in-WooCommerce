<?php

/**
 * Approval Recorded - Still Pending Template
 * 
 * This template displays a message when an approval has been recorded but more approvals are still needed.
 * 
 * @package WPHelpZone\WCSO
 * 
 * Available variables:
 * @var array $missing Array of email addresses still needed for approval
 */

if (! defined('ABSPATH')) {
    exit;
}
?>
<h1>Approval Recorded</h1>
<p>Thank you. Order is still waiting for: <?php echo esc_html(implode(', ', $missing)); ?></p>