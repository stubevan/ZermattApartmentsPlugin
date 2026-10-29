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

/**
 * Fields the Add Booking "past guest" picker copies from the guest's latest booking: names, both email boxes, address,
 * phone, guest counts and the tourist-tax guest list. Forms 1, 5 and 6 use the same field IDs, so a value from one
 * apartment's booking fits the other's form.
 */
const ZA_PREFILL_FIELDS = array( 1, 34, 2, 33, 10, 3, 5, 23, 24, 26 );

/** Calendars whose bookings the picker searches for past guests, with a short name. 5 = the sold Studio. */
const ZA_PAST_GUEST_CALENDARS = array(
	7 => 'Gornerwald',
	6 => 'Imperial',
	5 => 'Imperial Studio',
);

/** Email field ID on the booking forms (used to find a guest's bookings). */
const ZA_EMAIL_FIELD = 2;
