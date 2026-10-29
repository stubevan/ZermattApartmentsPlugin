<?php
/**
 * Queue the missing "08/09 Arrival Instructions" and "10 Check out Guide" emails
 * for accepted manual bookings that WP Booking System 5.12.6+ didn't schedule
 * (because "Send Emails" was unticked when the booking was added).
 *
 * Uses the same cron hook, args and timing the plugin uses itself:
 *   08 = 7 days before arrival, 09 = 3 days before arrival, 10 = 1 day before departure, all at 09:00 UTC.
 * Skips anything already queued or already in the past, so it is safe to run more than once.
 *
 * Run with DRY_RUN = true first to see what it would do, then set it to false.
 *   wp-cli:      wp eval-file wpbs-backfill-scheduled-emails.php
 *   wp-admin:    Tools → Cron Events → Add Cron Event → PHP Cron Event, paste everything below the
 *                opening "<?php" line, set "Next run" to "Now", no recurrence. Output goes to the PHP error log.
 *                The site has DISABLE_WP_CRON, so trigger it with: curl https://zermattapartments.ch/wp-cron.php?doing_wp_cron
 *
 * Ran on the live site 2026-10-23: queued 39 jobs for 13 bookings.
 */

$wpbs_backfill_dry_run = true;

$wpbs_backfill = function ( $dry_run ) {
	global $wpdb;

	$hook = 'wpbs_send_scheduled_email_notification';

	// calendar_id => [ 08 Arrival email id, 09 Arrival email id, 10 Check out email id ]
	$emails = array(
		7 => array( 20, 19, 23 ), // Haus Gornerwald (form 1)
		5 => array( 43, 42, 45 ), // Haus Imperial Studio (form 5)
		6 => array( 54, 53, 56 ), // Haus Imperial (form 6)
	);

	$bookings = $wpdb->get_results(
		"SELECT id, calendar_id, start_date, end_date
		   FROM {$wpdb->prefix}wpbs_bookings
		  WHERE status = 'accepted' AND calendar_id IN (5, 6, 7)
		  ORDER BY start_date"
	);

	$utc    = new DateTimeZone( 'UTC' );
	$now    = time();
	$queued = 0;
	$lines  = array();

	foreach ( $bookings as $b ) {
		$start = new DateTimeImmutable( substr( $b->start_date, 0, 10 ) . ' 09:00:00', $utc );
		$end   = new DateTimeImmutable( substr( $b->end_date, 0, 10 ) . ' 09:00:00', $utc );
		list( $arrival_7, $arrival_3, $checkout ) = $emails[ (int) $b->calendar_id ];

		$jobs = array(
			array( $arrival_7, $start->modify( '-7 days' ) ),
			array( $arrival_3, $start->modify( '-3 days' ) ),
			array( $checkout, $end->modify( '-1 day' ) ),
		);

		foreach ( $jobs as $job ) {
			list( $email_id, $when ) = $job;
			$ts   = $when->getTimestamp();
			$args = array( (int) $b->id, (int) $email_id ); // must be ints to match the plugin's own jobs

			if ( $ts <= $now || wp_next_scheduled( $hook, $args ) ) {
				continue;
			}

			$lines[] = sprintf( 'booking #%d (calendar %d): email %d at %s UTC', $b->id, $b->calendar_id, $email_id, $when->format( 'Y-m-d H:i' ) );

			if ( ! $dry_run ) {
				wp_schedule_single_event( $ts, $hook, $args );
			}
			$queued++;
		}
	}

	$lines[] = sprintf( '%s %d job(s).', $dry_run ? 'DRY RUN - would queue' : 'Queued', $queued );

	foreach ( $lines as $line ) {
		error_log( '[wpbs-backfill] ' . $line );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::log( $line );
		}
	}
};

$wpbs_backfill( $wpbs_backfill_dry_run );
