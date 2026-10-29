<?php
/**
 * "Deposit Received" email.
 *
 * No runtime hook is needed: WP Booking System already sends every "final_payment_made" notification
 * whose conditions match, once per booking, right after a deposit is marked as paid
 * (wpbs_action_ajax_booking_part_payment_update_status() in modules/pricing/functions-part-payments.php,
 * guarded by the booking meta "deposit_paid_email_sent"). So the email is just a form notification with
 * that trigger and the condition "Payment Status = Deposit Paid". This file creates it, and the matching
 * manual templates, so the setup can be repeated.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Text for each language, from templates/deposit-received.php. */
function za_deposit_email_text() {
	return require ZA_PLUGIN_DIR . 'templates/deposit-received.php';
}

/** The existing (not trashed) deposit notification on a form, or null. */
function za_find_deposit_email( $form_id ) {
	foreach ( wpbs_get_emails( array( 'form_id' => (int) $form_id ) ) as $email ) {
		if ( 'trash' !== $email->get( 'status' ) && ZA_DEPOSIT_EMAIL_NAME === $email->get( 'name' ) ) {
			return $email;
		}
	}
	return null;
}

/** Condition rule: Payment Status equal Deposit Paid (same shape WPBS saves from the editor). */
function za_deposit_condition() {
	return array(
		array(
			array(
				'condition'             => 'payment_status',
				'form_field'            => '',
				'comparison'            => 'equal',
				'value'                 => '',
				'value-weekday'         => '1',
				'value-payment_method'  => 'bank_transfer',
				'value-payment_status'  => 'deposit_paid',
				'value-booking_type'    => 'customer',
				'value-user-role'       => '',
				'value-date'            => '',
			),
		),
	);
}

/**
 * Create the form notifications (one per apartment) and the manual Settings templates, if missing.
 *
 * @return string[] lines describing what was (or would be) done
 */
function za_deposit_email_install( $dry_run = true, $enable = false ) {
	$text  = za_deposit_email_text();
	$lines = array();

	foreach ( za_apartments() as $apt ) {
		$existing = za_find_deposit_email( $apt['form'] );
		if ( $existing ) {
			$lines[] = sprintf( '%s (form %d): already has email %d, skipped.', $apt['name'], $apt['form'], $existing->get( 'id' ) );
			continue;
		}

		$source = wpbs_get_email( $apt['final_payment_email'] );
		if ( ! $source || (int) $source->get( 'form_id' ) !== (int) $apt['form'] ) {
			$lines[] = sprintf( '%s: source email %d not found on form %d, skipped.', $apt['name'], $apt['final_payment_email'], $apt['form'] );
			continue;
		}

		if ( $dry_run ) {
			$lines[] = sprintf( '%s (form %d): would copy email %d as "%s" (%s).', $apt['name'], $apt['form'], $source->get( 'id' ), ZA_DEPOSIT_EMAIL_NAME, $enable ? 'enabled' : 'disabled' );
			continue;
		}

		$now = current_time( 'Y-m-d H:i:s' );
		$id  = wpbs_insert_email(
			array(
				'name'          => ZA_DEPOSIT_EMAIL_NAME,
				'form_id'       => (int) $apt['form'],
				'is_enabled'    => $enable ? 1 : 0,
				'date_created'  => $now,
				'date_modified' => $now,
				'status'        => 'active',
			)
		);

		if ( ! $id ) {
			$lines[] = sprintf( '%s: inserting the email failed.', $apt['name'] );
			continue;
		}

		// Copy every setting of the source, as WPBS's own "Duplicate email" does.
		foreach ( wpbs_get_email_meta( $source->get( 'id' ) ) as $key => $values ) {
			$value = $values[0];
			if ( is_serialized( $value, false ) ) {
				$value = unserialize( $value );
			}
			wpbs_add_email_meta( $id, $key, $value );
		}

		$overrides = array(
			'notification_when_to_send'             => 'final_payment_made',
			'notification_enable_conditional_logic' => 'on',
			'notification_conditional_logic'        => za_deposit_condition(),
			'notification_attach_invoice'           => '0',
			'notification_ical_file'                => '0',
			'notification_subject'                  => $text['en']['subject'],
			'notification_message'                  => $text['en']['message'],
		);
		foreach ( array( 'fr', 'de' ) as $lang ) {
			$overrides[ 'notification_subject_translation_' . $lang ] = $text[ $lang ]['subject'];
			$overrides[ 'notification_message_translation_' . $lang ] = $text[ $lang ]['message'];
		}
		foreach ( $overrides as $key => $value ) {
			wpbs_update_email_meta( $id, $key, $value );
		}

		$lines[] = sprintf( '%s (form %d): created email %d from %d (%s).', $apt['name'], $apt['form'], $id, $source->get( 'id' ), $enable ? 'enabled' : 'disabled' );
	}

	// Manual templates: Settings → Email → Email Templates (used by "Load Template" on a booking).
	$settings = get_option( 'wpbs_settings', array() );
	$names    = isset( $settings['form_email_template_name'] ) ? (array) $settings['form_email_template_name'] : array();
	$bodies   = isset( $settings['form_email_template_body'] ) ? (array) $settings['form_email_template_body'] : array();
	$added    = 0;

	foreach ( array( 'en', 'fr', 'de' ) as $lang ) {
		$subject = $text[ $lang ]['subject'];
		$index   = array_search( $subject, $names, true );
		if ( false !== $index ) {
			$lines[] = sprintf( 'Manual template #%d (%s) already exists, skipped.', $index + 1, strtoupper( $lang ) );
			continue;
		}
		$names[]  = $subject;
		$bodies[] = wpautop( $text[ $lang ]['message'] );
		$lines[]  = sprintf( '%s manual template #%d (%s): "%s".', $dry_run ? 'Would add' : 'Added', count( $names ), strtoupper( $lang ), $subject );
		$added++;
	}

	if ( $added && ! $dry_run ) {
		$settings['form_email_template_name'] = array_values( $names );
		$settings['form_email_template_body'] = array_values( $bodies );
		update_option( 'wpbs_settings', $settings );
	}

	return $lines;
}

/**
 * Deposit state of one booking: what WPBS will see when the deposit is marked paid.
 *
 * @return array|null
 */
function za_deposit_booking_state( $booking_id ) {
	$booking = wpbs_get_booking( (int) $booking_id );
	if ( ! $booking ) {
		return null;
	}
	$payment = wpbs_get_payment_by_booking_id( $booking->get( 'id' ) );

	return array(
		'booking'       => (int) $booking->get( 'id' ),
		'status'        => $booking->get( 'status' ),
		'calendar'      => (int) $booking->get( 'calendar_id' ),
		'form'          => (int) $booking->get( 'form_id' ),
		'gateway'       => $payment ? $payment->get( 'gateway' ) : '-',
		'part_payment'  => $payment && $payment->is_part_payment() ? 'yes' : 'no',
		'deposit_paid'  => $payment && $payment->is_deposit_paid() ? 'yes' : 'no',
		'final_paid'    => $payment && $payment->is_final_payment_paid() ? 'yes' : 'no',
		'email_sent'    => wpbs_get_booking_meta( $booking->get( 'id' ), 'deposit_paid_email_sent', true ) ? 'yes' : 'no',
	);
}

/** Allow the email to be sent again for a booking (WPBS only sends it once). */
function za_deposit_reset_flag( $booking_id ) {
	return wpbs_delete_booking_meta( (int) $booking_id, 'deposit_paid_email_sent' );
}
