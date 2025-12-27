<?php

/**
 * Email Handler Class
 *
 * @package WPHelpZone\WCSO
 */

namespace WPHelpZone\WCSO;

use WPHelpZone\WCSO\Abstracts\WCSO_Singleton;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * WCSO Email Handler Class
 *
 * Handles email notifications and logging.
 */
class WCSO_Email_Handler extends WCSO_Singleton
{

    /**
     * Log file path
     *
     * @var string
     */
    private $log_file;

    /**
     * Initialize the class
     *
     * @return void
     */
    protected function init()
    {
        $this->log_file = WCSO_PLUGIN_DIR . 'email-log.txt';

        add_action('wcso_sample_order_created', array($this, 'send_notifications'), 10, 1);

        if (get_option('wcso_email_logging') === '1') {
            add_action('wp_mail', array($this, 'log_email'));
        }
    }

    /**
     * Send email notifications
     *
     * @param int $order_id Order ID.
     * @return void
     */
    public function send_notifications($order_id)
    {
        $order = wc_get_order($order_id);
        if (! $order) {
            return;
        }

        $tier   = $order->get_meta('_wcso_tier');
        $config = WCSO_Settings::get_tier_config();

        // Get billing email and CC emails.
        $billing_email = $order->get_billing_email();
        $cc_emails     = $this->get_cc_emails($tier, $config);

        // Send order confirmation email to customer.
        if ($billing_email) {
            $this->send_order_confirmation_email($order, $billing_email, $cc_emails, $tier);
        }

        // Send approval request emails to approvers.
        $needed_approvals = $order->get_meta('_wcso_approvals_needed') ?: array();
        $this->send_approval_request_emails($order, $needed_approvals);
    }

    /**
     * Get CC emails for the specified tier
     * Helper method used by send_notifications()
     *
     * @param string $tier   Tier code (t1so, t2so, t3so).
     * @param array  $config Tier configuration array.
     * @return array Array of CC email addresses.
     */
    private function get_cc_emails($tier, $config)
    {
        $cc_emails = array();

        if ($tier === 't1so' && ! empty($config['t1']['cc'])) {
            $cc_emails = explode(',', $config['t1']['cc']);
        }
        if ($tier === 't2so' && ! empty($config['t2']['cc'])) {
            $cc_emails = explode(',', $config['t2']['cc']);
        }
        if ($tier === 't3so' && ! empty($config['t3']['cc'])) {
            $cc_emails = explode(',', $config['t3']['cc']);
        }

        return $cc_emails;
    }

    /**
     * Send order confirmation email to customer
     * Helper method used by send_notifications()
     *
     * @param \WC_Order $order         Order object.
     * @param string    $billing_email Customer's billing email.
     * @param array     $cc_emails     Array of CC email addresses.
     * @param string    $tier          Tier code (t1so, t2so, t3so).
     * @return void
     */
    private function send_order_confirmation_email($order, $billing_email, $cc_emails, $tier)
    {
        $headers = array('Content-Type: text/html; charset=UTF-8');
        foreach ($cc_emails as $cc) {
            if (is_email(trim($cc))) {
                $headers[] = 'Cc: ' . trim($cc);
            }
        }

        // Pending approval for t2so, t3so.
        $status_msg = ($tier === 't1so') ? 'Processing' : 'Pending Approval';
        $subject    = "[Sample Order] Request Received #{$order->get_id()} ({$status_msg})";

        // Prepare template variables.
        $order_id = $order->get_id();
        $total    = $order->get_meta('_original_total');

        // Load template and capture output.
        ob_start();
        include WCSO_PLUGIN_DIR . 'templates/emails/order-confirmation.php';
        $msg = ob_get_clean();

        wp_mail($billing_email, $subject, $msg, $headers);
    }

    /**
     * Send approval request emails to all required approvers
     * Helper method used by send_notifications()
     *
     * @param \WC_Order $order            Order object.
     * @param array     $needed_approvals Array of approver email addresses.
     * @return void
     */
    private function send_approval_request_emails($order, $needed_approvals)
    {
        $tier = $order->get_meta('_wcso_tier');

        foreach ($needed_approvals as $approver_email) {
            if (! is_email($approver_email)) {
                continue;
            }

            // Generates action links for specific approver.
            $approve_link = WCSO_Approval::get_instance()->get_action_url($order->get_id(), $approver_email, 'approve');
            $reject_link  = WCSO_Approval::get_instance()->get_action_url($order->get_id(), $approver_email, 'reject');

            $subject = "[Action Required] Approve Sample Order #{$order->get_id()}";

            // Prepare template variables.
            $order_id   = $order->get_id();
            $created_by = $order->get_meta('_wcso_origin');
            $total      = $order->get_meta('_original_total');

            // Load template and capture output.
            ob_start();
            include WCSO_PLUGIN_DIR . 'templates/emails/approval-request.php';
            $msg = ob_get_clean();

            $headers = array('Content-Type: text/html; charset=UTF-8');
            wp_mail($approver_email, $subject, $msg, $headers);
        }
    }

    /**
     * Log email to Text File
     *
     * @param array $args Email arguments.
     * @return void
     */
    public function log_email($args)
    {
        $entry  = "--------------------------------------------------\n";
        $entry .= '[' . current_time('mysql') . '] TO: ' . $args['to'] . ' | SUBJ: ' . $args['subject'] . "\n";

        // Extract links for easier testing.
        // We look for single quotes because that's how we built the HTML string.
        if (strpos($args['message'], 'wcso_action') !== false) {
            preg_match_all("/href='(.*?)'/", $args['message'], $matches);
            if (isset($matches[1]) && ! empty($matches[1])) {
                foreach ($matches[1] as $link) {
                    $clean_link = html_entity_decode($link);
                    $entry     .= '   >>> ACTION LINK: ' . $clean_link . "\n";
                }
            }
        }
        $entry .= "\n";

        // Append to file.
        file_put_contents($this->log_file, $entry, FILE_APPEND);
    }

    /**
     * Get log content
     *
     * @return string
     */
    public function get_email_log()
    {
        if (! file_exists($this->log_file)) {
            return 'No emails logged yet.';
        }
        return file_get_contents($this->log_file);
    }

    /**
     * Clear log (Safely empty file instead of deleting)
     *
     * @return void
     */
    public function clear_email_log()
    {
        file_put_contents($this->log_file, '');
    }
}
