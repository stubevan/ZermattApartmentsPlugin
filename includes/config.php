<?php
/**
 * Site IDs in one place. See NOTES.md in the site support folder for the full history.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Live apartments.
 * - calendar / form: WP Booking System IDs
 * - final_payment_email: the "07 - Final Payment Received" notification the deposit email is copied from
 */
function za_apartments() {
	return array(
		'gornerwald' => array(
			'name'                => 'Haus Gornerwald',
			'calendar'            => 7,
			'form'                => 1,
			'final_payment_email' => 17,
		),
		'imperial'   => array(
			'name'                => 'Haus Imperial',
			'calendar'            => 6,
			'form'                => 6,
			'final_payment_email' => 52,
		),
	);
}

/** Name of the per-form "Deposit Received" notification. The installer finds existing ones by this name. */
const ZA_DEPOSIT_EMAIL_NAME = '07a - Deposit Received';

/** WPBS's own cron hook for timed emails; args are [ (int) booking_id, (int) email_id ]. */
const ZA_WPBS_EMAIL_HOOK = 'wpbs_send_scheduled_email_notification';
