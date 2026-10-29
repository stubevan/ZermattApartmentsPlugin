<?php
/**
 * "Payment received" email (each apartment's "07 - Final Payment Received" notification).
 *
 * WPBS already sends it when a bank-transfer booking is marked paid. This file fills two gaps:
 * - Once per booking. Unmarking and re-marking a final payment made WPBS send it again. The first successful send
 *   sets booking meta `za_paid_email_sent`; after that, its "send to" address reads as empty for that booking, so
 *   WPBS_Mailer::send() skips it without sending or logging.
 * - Payment on arrival. WPBS sends nothing when those bookings are marked paid, so we send the same
 *   final-payment emails ourselves after WPBS has saved the change.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ZA_PAID_EMAIL_FLAG = 'za_paid_email_sent';

/** The "paid" notification IDs: each apartment's final_payment_email in config.php. */
function za_paid_email_ids() {
	return array_map( 'intval', array_column( za_apartments(), 'final_payment_email' ) );
}

/** Email ID to silence for the booking currently being emailed, or 0. Set per email in send_email(). */
function za_paid_email_suppress( $set = null ) {
	static $email_id = 0;
	if ( null !== $set ) {
		$email_id = (int) $set;
	}
	return $email_id;
}

// Before WPBS reads an email's fields: silence a paid email the booking has already had.
add_action(
	'wpbs_email_notifications_before',
	function ( $email, $booking ) {
		$id = (int) $email->get( 'id' );
		$already = in_array( $id, za_paid_email_ids(), true ) && wpbs_get_booking_meta( $booking->get( 'id' ), ZA_PAID_EMAIL_FLAG, true );
		za_paid_email_suppress( $already ? $id : 0 );
	},
	10,
	2
);

// WPBS reads email settings via get_metadata( 'email', … ). An empty "send to" makes the mailer skip the email.
add_filter(
	'get_email_metadata',
	function ( $value, $object_id, $meta_key ) {
		$suppress = za_paid_email_suppress();
		// "send to" and its translations only, not the cc/bcc fields.
		if ( $suppress && (int) $object_id === $suppress && preg_match( '/^notification_send_to(_translation_[a-z]+)?$/', (string) $meta_key ) ) {
			return array( '' );
		}
		return $value;
	},
	10,
	3
);

// After a successful send: remember that this booking has had its paid email.
add_action(
	'wpbs_mailer_end',
	function ( $mailer ) {
		if ( in_array( (int) $mailer->email_id, za_paid_email_ids(), true ) && ! empty( $mailer->booking ) ) {
			wpbs_update_booking_meta( $mailer->booking->get( 'id' ), ZA_PAID_EMAIL_FLAG, current_time( 'timestamp' ) );
		}
	}
);

// Payment on arrival: when "mark as paid" is clicked, send the final-payment emails once WPBS has saved it.
add_action(
	'wp_ajax_wpbs_booking_change_status',
	function () {
		if ( ! check_ajax_referer( 'wpbs_change_payment_status', 'wpbs_token', false ) || empty( $_POST['id'] ) ) {
			return;
		}
		$payment = wpbs_get_payment( absint( $_POST['id'] ) );
		if ( ! $payment || 'payment_on_arrival' !== $payment->get( 'gateway' ) || $payment->is_paid() ) {
			return; // Not payment on arrival, or this click marks it unpaid.
		}
		$payment_id = (int) $payment->get( 'id' );
		add_action(
			'shutdown',
			function () use ( $payment_id ) {
				$payment = wpbs_get_payment( $payment_id );
				if ( $payment && $payment->is_paid() ) {
					( new WPBS_Email_Notifications( $payment->get( 'booking_id' ) ) )->send_final_payment_emails();
				}
			}
		);
	},
	1
);

/** Payment state of one booking, for `wp zermatt paid-email status`. */
function za_paid_booking_state( $booking_id ) {
	$booking = wpbs_get_booking( (int) $booking_id );
	if ( ! $booking ) {
		return null;
	}
	$payment = wpbs_get_payment_by_booking_id( $booking->get( 'id' ) );
	$sent    = wpbs_get_booking_meta( $booking->get( 'id' ), ZA_PAID_EMAIL_FLAG, true );

	return array(
		'booking'    => (int) $booking->get( 'id' ),
		'status'     => $booking->get( 'status' ),
		'form'       => (int) $booking->get( 'form_id' ),
		'gateway'    => $payment ? $payment->get( 'gateway' ) : '-',
		'paid'       => $payment && ( $payment->is_paid() || ( $payment->is_part_payment() && $payment->is_final_payment_paid() ) ) ? 'yes' : 'no',
		'email_sent' => $sent ? wp_date( 'Y-m-d H:i', (int) $sent ) : 'no',
	);
}

/**
 * Flag bookings that already received a paid email before this guard existed (email log entries starting "07 -").
 *
 * @return array{lines: string[], count: int}
 */
function za_paid_email_mark_history( $dry_run = true ) {
	global $wpdb;
	$rows  = $wpdb->get_results( "SELECT booking_id, meta_value FROM {$wpdb->prefix}wpbs_booking_meta WHERE meta_key = 'email_log'" );
	$found = array();
	foreach ( $rows as $row ) {
		$log = maybe_unserialize( $row->meta_value );
		if ( is_string( $log ) ) {
			$log = json_decode( $log, true );
		}
		if ( is_array( $log ) && 0 === strpos( (string) ( $log['email_type'] ?? '' ), '07 -' ) ) {
			$found[ (int) $row->booking_id ] = (int) ( $log['send_date'] ?? current_time( 'timestamp' ) );
		}
	}
	ksort( $found );

	$lines = array();
	$count = 0;
	foreach ( $found as $booking_id => $sent ) {
		if ( wpbs_get_booking_meta( $booking_id, ZA_PAID_EMAIL_FLAG, true ) ) {
			continue;
		}
		$lines[] = sprintf( 'Booking #%d: paid email sent %s', $booking_id, wp_date( 'Y-m-d', $sent ) );
		if ( ! $dry_run ) {
			wpbs_update_booking_meta( $booking_id, ZA_PAID_EMAIL_FLAG, $sent );
		}
		$count++;
	}
	return array( 'lines' => $lines, 'count' => $count );
}

/** EN/FR/DE subject and message of the first apartment's paid email, for the manual templates. */
function za_paid_email_text() {
	$id    = za_paid_email_ids()[0];
	$items = array( 'en' => array(
		'subject' => wpbs_get_email_meta( $id, 'notification_subject', true ),
		'message' => wpbs_get_email_meta( $id, 'notification_message', true ),
	) );
	foreach ( array( 'fr', 'de' ) as $lang ) {
		$subject = wpbs_get_email_meta( $id, 'notification_subject_translation_' . $lang, true );
		$message = wpbs_get_email_meta( $id, 'notification_message_translation_' . $lang, true );
		if ( $subject && $message ) {
			$items[ $lang ] = array( 'subject' => $subject, 'message' => $message );
		}
	}
	return $items;
}
