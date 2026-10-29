<?php
/**
 * WP-CLI commands. On the live site:
 *   ssh zermattapartments "cd wordpress && wp --skip-plugins=wordfence zermatt …"
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Set up and check the "Deposit Received" email.
 */
class ZA_Deposit_Email_Command {

	/**
	 * Create the "07a - Deposit Received" notification on each apartment's form, and the manual templates.
	 *
	 * Skips anything that already exists, so it never overwrites edits made in wp-admin.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Show what would be done.
	 *
	 * [--enable]
	 * : Create the notifications enabled (default: disabled).
	 */
	public function install( $args, $assoc ) {
		$dry_run = ! empty( $assoc['dry-run'] );
		foreach ( za_deposit_email_install( $dry_run, ! empty( $assoc['enable'] ) ) as $line ) {
			WP_CLI::log( $line );
		}
		WP_CLI::success( $dry_run ? 'Dry run, nothing changed.' : 'Done.' );
	}

	/**
	 * Show the deposit notifications and, optionally, one booking's deposit state.
	 *
	 * ## OPTIONS
	 *
	 * [--booking=<id>]
	 * : Booking to show.
	 */
	public function status( $args, $assoc ) {
		foreach ( za_apartments() as $apt ) {
			$email = za_find_deposit_email( $apt['form'] );
			WP_CLI::log(
				$email
					? sprintf( '%s (form %d): email %d, %s', $apt['name'], $apt['form'], $email->get( 'id' ), $email->get( 'is_enabled' ) ? 'enabled' : 'DISABLED' )
					: sprintf( '%s (form %d): no "%s" email', $apt['name'], $apt['form'], ZA_DEPOSIT_EMAIL_NAME )
			);
		}

		$settings = get_option( 'wpbs_settings', array() );
		$names    = isset( $settings['form_email_template_name'] ) ? (array) $settings['form_email_template_name'] : array();
		foreach ( za_deposit_email_text() as $lang => $text ) {
			$index = array_search( $text['subject'], $names, true );
			WP_CLI::log( sprintf( 'Manual template (%s): %s', strtoupper( $lang ), false === $index ? 'missing' : '#' . ( $index + 1 ) ) );
		}

		if ( ! empty( $assoc['booking'] ) ) {
			$state = za_deposit_booking_state( $assoc['booking'] );
			if ( ! $state ) {
				WP_CLI::error( 'Booking not found.' );
			}
			foreach ( $state as $key => $value ) {
				WP_CLI::log( sprintf( '  %-13s %s', $key, $value ) );
			}
		}
	}

	/**
	 * Clear a booking's "deposit email sent" flag so marking the deposit paid sends it again.
	 *
	 * ## OPTIONS
	 *
	 * --booking=<id>
	 * : Booking ID.
	 *
	 * @subcommand reset-flag
	 */
	public function reset_flag( $args, $assoc ) {
		za_deposit_reset_flag( $assoc['booking'] );
		WP_CLI::success( sprintf( 'Flag cleared for booking #%d.', $assoc['booking'] ) );
	}
}

/**
 * The "payment received" email (each apartment's "07 - Final Payment Received"), sent once per booking.
 */
class ZA_Paid_Email_Command {

	/**
	 * Show the paid emails, how many bookings have had one, and optionally one booking's state.
	 *
	 * ## OPTIONS
	 *
	 * [--booking=<id>]
	 * : Booking to show.
	 */
	public function status( $args, $assoc ) {
		foreach ( za_paid_email_ids() as $id ) {
			$email = wpbs_get_email( $id );
			WP_CLI::log( $email
				? sprintf( 'Email %d "%s" (form %d): %s', $id, $email->get( 'name' ), $email->get( 'form_id' ), $email->get( 'is_enabled' ) ? 'enabled' : 'DISABLED' )
				: sprintf( 'Email %d: not found', $id ) );
		}
		global $wpdb;
		$flagged = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT booking_id) FROM {$wpdb->prefix}wpbs_booking_meta WHERE meta_key = %s", ZA_PAID_EMAIL_FLAG ) );
		WP_CLI::log( sprintf( 'Bookings marked as sent: %d', $flagged ) );

		if ( ! empty( $assoc['booking'] ) ) {
			$state = za_paid_booking_state( $assoc['booking'] );
			if ( ! $state ) {
				WP_CLI::error( 'Booking not found.' );
			}
			foreach ( $state as $key => $value ) {
				WP_CLI::log( sprintf( '  %-11s %s', $key, $value ) );
			}
		}
	}

	/**
	 * Mark bookings that already got a paid email (email log entries starting "07 -"), so they never get another.
	 *
	 * Dry run unless --yes is given.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Actually set the flags.
	 *
	 * @subcommand mark-history
	 */
	public function mark_history( $args, $assoc ) {
		$dry_run = empty( $assoc['yes'] );
		$result  = za_paid_email_mark_history( $dry_run );
		foreach ( $result['lines'] as $line ) {
			WP_CLI::log( $line );
		}
		WP_CLI::success( sprintf( '%s %d booking(s).', $dry_run ? 'Dry run: would mark' : 'Marked', $result['count'] ) );
	}

	/**
	 * Allow the paid email to be sent again for a booking.
	 *
	 * ## OPTIONS
	 *
	 * --booking=<id>
	 * : Booking ID.
	 *
	 * @subcommand reset-flag
	 */
	public function reset_flag( $args, $assoc ) {
		wpbs_delete_booking_meta( (int) $assoc['booking'], ZA_PAID_EMAIL_FLAG );
		WP_CLI::success( sprintf( 'Flag cleared for booking #%d.', $assoc['booking'] ) );
	}

	/**
	 * Copy the paid email's EN/FR/DE text into the manual "Load Template" list, if not there yet.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Show what would be added.
	 */
	public function templates( $args, $assoc ) {
		$dry_run = ! empty( $assoc['dry-run'] );
		foreach ( za_add_manual_templates( za_paid_email_text(), $dry_run ) as $line ) {
			WP_CLI::log( $line );
		}
		WP_CLI::success( $dry_run ? 'Dry run, nothing changed.' : 'Done.' );
	}
}

/**
 * Queue arrival / checkout / review emails that WP Booking System didn't schedule.
 *
 * Dry run unless --yes is given. Skips jobs already queued or in the past.
 *
 * ## OPTIONS
 *
 * [--yes]
 * : Actually queue the jobs.
 *
 * [--email=<ids>]
 * : Comma-separated notification IDs to limit to.
 *
 * [--booking=<id>]
 * : Limit to one booking.
 */
function za_cli_backfill_emails( $args, $assoc ) {
	$dry_run = empty( $assoc['yes'] );
	$emails  = empty( $assoc['email'] ) ? array() : array_map( 'intval', explode( ',', $assoc['email'] ) );
	$result  = za_backfill_emails( $dry_run, $emails, empty( $assoc['booking'] ) ? null : (int) $assoc['booking'] );

	foreach ( $result['lines'] as $line ) {
		WP_CLI::log( $line );
	}
	WP_CLI::success( sprintf( '%s %d job(s).', $dry_run ? 'Dry run: would queue' : 'Queued', $result['queued'] ) );
}

WP_CLI::add_command( 'zermatt deposit-email', 'ZA_Deposit_Email_Command' );
WP_CLI::add_command( 'zermatt paid-email', 'ZA_Paid_Email_Command' );
WP_CLI::add_command( 'zermatt backfill-emails', 'za_cli_backfill_emails' );
