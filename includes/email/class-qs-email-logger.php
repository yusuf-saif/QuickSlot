<?php
/**
 * Email log writer.
 *
 * @package QuickSlot
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
	return;
}

final class QS_Email_Logger {
	/**
	 * Writes an email log row.
	 */
	public static function log(int $booking_id, string $email_type, string $recipient_email, string $subject, string $status): void {
		global $wpdb;

		if (! isset($wpdb) || ! ($wpdb instanceof wpdb)) {
			return;
		}

		$allowed_types    = array('confirmation', 'notification', 'reminder', 'cancellation');
		$allowed_statuses = array('sent', 'failed');
		$email_type       = sanitize_key($email_type);
		$status           = sanitize_key($status);

		if (! in_array($email_type, $allowed_types, true) || ! in_array($status, $allowed_statuses, true)) {
			return;
		}

		$wpdb->insert(
			$wpdb->prefix . 'qs_email_logs',
			array(
				'booking_id'       => $booking_id > 0 ? $booking_id : null,
				'email_type'       => $email_type,
				'recipient_email'  => sanitize_email($recipient_email),
				'subject'          => sanitize_text_field($subject),
				'status'           => $status,
				'sent_at'          => 'sent' === $status ? current_time('mysql') : null,
			),
			array('%d', '%s', '%s', '%s', '%s', '%s')
		);
	}
}
