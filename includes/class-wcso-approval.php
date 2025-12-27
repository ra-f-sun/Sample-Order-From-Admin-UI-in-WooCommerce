<?php

/**
 * Approval Class
 *
 * @package WPHelpZone\WCSO
 */

namespace WPHelpZone\WCSO;

use WPHelpZone\WCSO\Abstracts\WCSO_Singleton;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * WCSO Approval Class
 *
 * Handles approval workflow for sample orders.
 */
class WCSO_Approval extends WCSO_Singleton
{

    /**
     * Security salt for token generation
     *
     * @var string
     */
    private $salt = 'wcso_v2_secure_salt_string';

    /**
     * Initialize the class
     *
     * @return void
     */
    protected function init()
    {
        // Approving won't redirect to homepage.
        add_action('template_redirect', array($this, 'handle_approval_click'));
    }

    /**
     * Generate signed URL for Approve/Reject
     *
     * @param int    $order_id       Order ID.
     * @param string $approver_email Approver email.
     * @param string $action         Action type (approve/reject).
     * @return string
     */
    public function get_action_url($order_id, $approver_email, $action = 'approve')
    {
        // Create a hash to verify the request later.
        $token = hash_hmac('sha256', $order_id . $approver_email . $action, $this->salt);

        return add_query_arg(
            array(
                'wcso_action' => $action,
                'wcso_oid'    => $order_id,
                'wcso_email'  => urlencode($approver_email),
                'wcso_token'  => $token,
            ),
            home_url('/')
        );
    }


    /**
     * Handle the click event
     *
     * @return void
     */
    public function handle_approval_click()
    {

        if (! isset($_GET['wcso_action'], $_GET['wcso_token'])) {
            return;
        }

        $action   = sanitize_text_field($_GET['wcso_action']);
        $order_id = absint($_GET['wcso_oid']);
        $email    = urldecode($_GET['wcso_email']);
        $token    = $_GET['wcso_token'];

        // Validate security token.
        if (! $this->validate_approval_token($token, $order_id, $email, $action)) {
            wp_die('Invalid security token. <br>Hash received: ' . esc_html($token), 'Security Error', array('response' => 403));
        }

        $order = wc_get_order($order_id);
        if (! $order) {
            wp_die('Order not found.');
        }

        // Reject Logic.
        if ($action === 'reject') {
            $order->update_status('cancelled', "Rejected by {$email} via email link.");
            wp_die('<h1>Order Rejected</h1><p>The order has been cancelled.</p>');
        }

        // Approve Logic.
        if ($action === 'approve') {
            // Check if this person needs to approve.
            if (! $this->is_approval_needed($order, $email)) {
                wp_die("<h1>Approval Not Required</h1><p>The email <strong>{$email}</strong> is not listed as a required approver for Order #{$order_id}.</p>");
            }

            // Grant the approval.
            $this->grant_approval($order, $email);

            // Check if all approvals received.
            if ($this->check_approval_completion($order)) {
                $order->update_status('processing', 'All tier approvals granted.');
                wp_die('<h1 style="color:green">Approval Successful</h1><p>All approvals received. Order is now <strong>Processing</strong>.</p>');
            } else {
                $needed  = $order->get_meta('_wcso_approvals_needed') ?: array();
                $granted = $order->get_meta('_wcso_approvals_granted') ?: array();
                $missing = array_diff($needed, $granted);
                wp_die('<h1>Approval Recorded</h1><p>Thank you. Order is still waiting for: ' . implode(', ', $missing) . '</p>');
            }
        }
    }

    /**
     * Validate the approval security token
     * Helper method used by handle_approval_click()
     *
     * @param string $token    The token from URL.
     * @param int    $order_id Order ID.
     * @param string $email    Approver email.
     * @param string $action   Action type (approve/reject).
     * @return bool True if token is valid, false otherwise.
     */
    private function validate_approval_token($token, $order_id, $email, $action)
    {
        $expected = hash_hmac('sha256', $order_id . $email . $action, $this->salt);
        return hash_equals($expected, $token);
    }

    /**
     * Check if the email is in the needed approvals list
     * Helper method used by handle_approval_click()
     *
     * @param \WC_Order $order Order object.
     * @param string    $email Approver email.
     * @return bool True if approval is needed from this email.
     */
    private function is_approval_needed($order, $email)
    {
        $needed = $order->get_meta('_wcso_approvals_needed') ?: array();
        return in_array($email, $needed);
    }

    /**
     * Add the email to granted approvals list
     * Helper method used by handle_approval_click()
     *
     * @param \WC_Order $order Order object.
     * @param string    $email Approver email.
     * @return void
     */
    private function grant_approval($order, $email)
    {
        $granted = $order->get_meta('_wcso_approvals_granted') ?: array();

        if (! in_array($email, $granted)) {
            $granted[] = $email;
            $order->update_meta_data('_wcso_approvals_granted', $granted);
            $order->add_order_note("Approval granted by: {$email} (via email link)");
            $order->save();
        }
    }

    /**
     * Check if all required approvals have been received
     * Helper method used by handle_approval_click()
     *
     * @param \WC_Order $order Order object.
     * @return bool True if all approvals are complete, false otherwise.
     */
    private function check_approval_completion($order)
    {
        $needed  = $order->get_meta('_wcso_approvals_needed') ?: array();
        $granted = $order->get_meta('_wcso_approvals_granted') ?: array();
        $missing = array_diff($needed, $granted);

        return empty($missing);
    }
}
