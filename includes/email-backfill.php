<?php
/**
 * Queue timed emails (arrival, checkout, review…) that WP Booking System should have scheduled but didn't.
 *
 * WPBS schedules them only when a booking is saved, and since 5.12.6 only if "Send Emails" was ticked on a
 * manual booking. It also checks conditions only at save time. This works out the same jobs WPBS would have
 * queued and adds the missing ones, using WPBS's own date and condition logic.
 * Safe to re-run: anything already queued or in the past is skipped.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Trigger types WPBS schedules through cron (see WPBS_Email_Notifications::schedule_emails()). */
const ZA_TIMED_TRIGGERS = array( 'after_created', 'before_start', 'before_end', 'after_start', 'after_end' );

/** True if the booking has an email address (WPBS skips scheduling without one). */
function za_booking_has_email( $booking ) {
	foreach ( (array) $booking->get( 'fields' ) as $field ) {
		if ( isset( $field['type'] ) && 'email' === $field['type'] && ! empty( $field['user_value'] ) ) {
			return true;
		}
	}
	return false;
}

/**
 * @param bool      $dry_run
 * @param int[]     $only_emails  limit to these notification IDs (empty = all timed ones)
 * @param int|null  $only_booking limit to one booking
 * @return array{lines: string[], queued: int}
 */
function za_backfill_emails( $dry_run = true, $only_emails = array(), $only_booking = null ) {
	$now    = current_time( 'timestamp' ); // WPBS compares delivery dates with this.
	$lines  = array();
	$queued = 0;

	foreach ( za_apartments() as $apt ) {
		$emails = array();
		foreach ( wpbs_get_emails( array( 'form_id' => $apt['form'] ) ) as $email ) {
			if ( 'trash' === $email->get( 'status' ) || ! $email->get( 'is_enabled' ) ) {
				continue;
			}
			if ( $only_emails && ! in_array( (int) $email->get( 'id' ), $only_emails, true ) ) {
				continue;
			}
			if ( in_array( wpbs_get_email_meta( $email->get( 'id' ), 'notification_when_to_send', true ), ZA_TIMED_TRIGGERS, true ) ) {
				$emails[] = $email;
			}
		}

		$bookings = wpbs_get_bookings( array( 'calendar_id' => $apt['calendar'], 'status' => array( 'accepted' ) ) );

		foreach ( $bookings as $booking ) {
			$booking_id = (int) $booking->get( 'id' );
			if ( $only_booking && $booking_id !== (int) $only_booking ) {
				continue;
			}
			if ( (int) $booking->get( 'form_id' ) !== (int) $apt['form'] || ! za_booking_has_email( $booking ) ) {
				continue;
			}

			foreach ( $emails as $email ) {
				$email_id = (int) $email->get( 'id' );
				$when     = wpbs_get_email_delivery_date( $booking, $email );
				$args     = array( $booking_id, $email_id ); // ints, to match WPBS's own jobs

				if ( $when < $now || wp_next_scheduled( ZA_WPBS_EMAIL_HOOK, $args ) ) {
					continue;
				}

				if ( 'on' === wpbs_get_email_meta( $email_id, 'notification_enable_conditional_logic', true ) ) {
					$logic = new WPBS_Evaluate_Email_Logic( $email, $booking, true );
					if ( false === $logic->get_evaluation() ) {
						continue;
					}
				}

				$lines[] = sprintf(
					'%s booking #%d: "%s" (email %d) at %s',
					$apt['name'],
					$booking_id,
					$email->get( 'name' ),
					$email_id,
					gmdate( 'Y-m-d H:i', $when )
				);

				if ( ! $dry_run ) {
					wp_schedule_single_event( $when, ZA_WPBS_EMAIL_HOOK, $args );
				}
				$queued++;
			}
		}
	}

	return array( 'lines' => $lines, 'queued' => $queued );
}
