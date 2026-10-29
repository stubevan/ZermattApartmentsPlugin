<?php
/**
 * Queue the "11 - Review Request" email (3 days after departure, 09:00 UTC) for accepted bookings
 * that were saved while the email's broken "calendar_id = 1" condition was still on, so the plugin
 * never queued it. Skips anything already queued or already in the past; safe to re-run.
 *   wp-cli:   wp eval-file wpbs-backfill-review-emails.php
 *   wp-admin: WP Crontrol → Add Cron Event → PHP cron event (paste everything after "<?php"),
 *             then trigger: curl https://zermattapartments.ch/wp-cron.php?doing_wp_cron
 *
 * Ran on the live site 2026-10-23: queued 17 jobs.
 */

$wpbs_review_dry_run = true;

$wpbs_review_backfill = function ( $dry_run ) {
	global $wpdb;

	$hook = 'wpbs_send_scheduled_email_notification';

	// calendar_id => "11 - Review Request" email id
	$emails = array(
		7 => 22, // Haus Gornerwald (form 1)
		5 => 44, // Haus Imperial Studio (form 5)
		6 => 55, // Haus Imperial (form 6)
	);

	$bookings = $wpdb->get_results(
		"SELECT id, calendar_id, end_date
		   FROM {$wpdb->prefix}wpbs_bookings
		  WHERE status = 'accepted' AND calendar_id IN (5, 6, 7)
		  ORDER BY end_date"
	);

	$utc    = new DateTimeZone( 'UTC' );
	$now    = time();
	$queued = 0;
	$lines  = array();

	foreach ( $bookings as $b ) {
		$when = ( new DateTimeImmutable( substr( $b->end_date, 0, 10 ) . ' 09:00:00', $utc ) )->modify( '+3 days' );
		$ts   = $when->getTimestamp();
		$args = array( (int) $b->id, (int) $emails[ (int) $b->calendar_id ] );

		if ( $ts <= $now || wp_next_scheduled( $hook, $args ) ) {
			continue;
		}

		$lines[] = sprintf( 'booking #%d (calendar %d): email %d at %s UTC', $b->id, $b->calendar_id, $args[1], $when->format( 'Y-m-d H:i' ) );

		if ( ! $dry_run ) {
			wp_schedule_single_event( $ts, $hook, $args );
		}
		$queued++;
	}

	$lines[] = sprintf( '%s %d job(s).', $dry_run ? 'DRY RUN - would queue' : 'Queued', $queued );

	foreach ( $lines as $line ) {
		error_log( '[wpbs-review-backfill] ' . $line );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::log( $line );
		}
	}
};

$wpbs_review_backfill( $wpbs_review_dry_run );
