<?php
if (!defined('ABSPATH')) exit;

class WCSO_Approval
{

    private static $instance = null;
    private $salt = 'wcso_v2_secure_salt_string'; // Change this in production

    public static function get_instance()
    {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct()
    {
        // FIXED: Changed from 'init' to 'template_redirect' to prevent homepage redirects
        add_action('template_redirect', array($this, 'handle_approval_click'));
    }

    /**
     * Generate signed URL for Approve/Reject
     */
    public function get_action_url($order_id, $approver_email, $action = 'approve')
    {
        // Create a hash to verify the request later
        $token = hash_hmac('sha256', $order_id . $approver_email . $action, $this->salt);

        return add_query_arg(array(
            'wcso_action'   => $action,
            'wcso_oid'      => $order_id,
            'wcso_email'    => urlencode($approver_email),
            'wcso_token'    => $token
        ), home_url('/'));
    }

    /**
     * Handle the click event
     */
    public function handle_approval_click()
    {

        // --- DEBUGGER: UNCOMMENT THESE 4 LINES TO TEST ---
        // if (isset($_GET['wcso_action'])) {
        //     echo "<h1>DEBUG MODE ACTIVE</h1>";
        //     echo "<pre>"; var_dump($_GET); echo "</pre>";
        //     die("Stopped here to prevent redirect."); 
        // }
        // --------------------------------------------------

        if (!isset($_GET['wcso_action'], $_GET['wcso_token'])) return;

        $action   = sanitize_text_field($_GET['wcso_action']);
        $order_id = absint($_GET['wcso_oid']);
        $email    = urldecode($_GET['wcso_email']); // Make sure to urldecode
        $token    = $_GET['wcso_token'];

        // 1. Verify Token
        $expected = hash_hmac('sha256', $order_id . $email . $action, $this->salt);

        if (!hash_equals($expected, $token)) {
            // Debug hint: If this fails, check if the email in URL matches exactly what was hashed
            wp_die('Invalid security token. <br>Hash received: ' . esc_html($token), 'Security Error', array('response' => 403));
        }

        $order = wc_get_order($order_id);
        if (!$order) wp_die('Order not found.');

        // 2. Reject Logic
        if ($action === 'reject') {
            $order->update_status('cancelled', "Rejected by {$email} via email link.");
            wp_die('<h1>Order Rejected</h1><p>The order has been cancelled.</p>');
        }

        // 3. Approve Logic
        if ($action === 'approve') {
            $needed = $order->get_meta('_wcso_approvals_needed') ?: array();
            $granted = $order->get_meta('_wcso_approvals_granted') ?: array();

            // Check if this person actually needs to approve
            if (!in_array($email, $needed)) {
                // If the email in the link isn't in the "Needed" list, show info
                wp_die("<h1>Approval Not Required</h1><p>The email <strong>{$email}</strong> is not listed as a required approver for Order #{$order_id}.</p>");
            }

            // Add to granted
            if (!in_array($email, $granted)) {
                $granted[] = $email;
                $order->update_meta_data('_wcso_approvals_granted', $granted);
                $order->add_order_note("Approval granted by: {$email} (via email link)");
                $order->save();
            }

            // Check if Complete
            $missing = array_diff($needed, $granted);

            if (empty($missing)) {
                $order->update_status('processing', 'All tier approvals granted.');
                wp_die('<h1 style="color:green">Approval Successful</h1><p>All approvals received. Order is now <strong>Processing</strong>.</p>');
            } else {
                wp_die('<h1>Approval Recorded</h1><p>Thank you. Order is still waiting for: ' . implode(', ', $missing) . '</p>');
            }
        }
    }
}
