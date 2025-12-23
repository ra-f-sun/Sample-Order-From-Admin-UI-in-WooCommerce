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

        // Billing Information.
        $billing_email = $order->get_billing_email();
        $cc_emails     = array();

        if ($tier === 't1so' && ! empty($config['t1']['cc'])) {
            $cc_emails = explode(',', $config['t1']['cc']);
        }
        if ($tier === 't2so' && ! empty($config['t2']['cc'])) {
            $cc_emails = explode(',', $config['t2']['cc']);
        }
        if ($tier === 't3so' && ! empty($config['t3']['cc'])) {
            $cc_emails = explode(',', $config['t3']['cc']);
        }

        if ($billing_email) {
            $headers = array('Content-Type: text/html; charset=UTF-8');
            foreach ($cc_emails as $cc) {
                if (is_email(trim($cc))) {
                    $headers[] = 'Cc: ' . trim($cc);
                }
            }

            // Pending approval for t2so, t3so.
            $status_msg = ($tier === 't1so') ? 'Processing' : 'Pending Approval';
            $subject    = "[Sample Order] Request Received #{$order->get_id()} ({$status_msg})";

            $msg  = '<h2>Sample Order Received</h2>';
            $msg .= "<p>Your order #{$order->get_id()} has been placed.</p>";
            $msg .= "<p><strong>Current Status:</strong> {$status_msg}</p>";
            $msg .= '<p><strong>Total Value:</strong> ' . $order->get_meta('_original_total') . '</p>';

            wp_mail($billing_email, $subject, $msg, $headers);
        }

        // Action Mail (Approval).
        $needed = $order->get_meta('_wcso_approvals_needed') ?: array();

        foreach ($needed as $approver_email) {
            if (! is_email($approver_email)) {
                continue;
            }

            // Generates action links for specific approver.
            $approve_link = WCSO_Approval::get_instance()->get_action_url($order_id, $approver_email, 'approve');
            $reject_link  = WCSO_Approval::get_instance()->get_action_url($order_id, $approver_email, 'reject');

            $subject = "[Action Required] Approve Sample Order #{$order->get_id()}";

            $msg  = '<h2>Approval Required</h2>';
            $msg .= '<p>A Tier ' . strtoupper(str_replace('so', '', $tier)) . ' sample order requires your approval.</p>';
            $msg .= '<p><strong>Created By:</strong> ' . $order->get_meta('_wcso_origin') . '</p>';
            $msg .= '<p><strong>Total Value:</strong> ' . $order->get_meta('_original_total') . '</p>';
            $msg .= '<div style="margin:20px 0;">';
            $msg .= "<a href='{$approve_link}' style='background:green;color:white;padding:10px 15px;text-decoration:none;margin-right:10px;'>APPROVE</a>";
            $msg .= "<a href='{$reject_link}' style='background:red;color:white;padding:10px 15px;text-decoration:none;'>REJECT</a>";
            $msg .= '</div>';
            $msg .= '<p><small>Clicking these links simulates a login-free approval action.</small></p>';

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
