<?php
/**
 * Front-end speed: WP Booking System loads its scripts and styles (about 80 KB of CSS plus moment.js, the phone
 * library and its main script) on every page. Only the calendar and payment pages use them, so they are removed
 * everywhere else. Add a page ID to ZA_WPBS_ASSET_PAGES if a calendar is ever put on another page.
 *
 * The same IDs are excluded from the IONOS page cache (Performance → Caching → "without IDs"), because a cached
 * calendar would show out-of-date availability.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Pages that show a WPBS calendar or form: booking + availability pages (EN/DE/FR) and the online payments page. */
const ZA_WPBS_ASSET_PAGES = array( 451, 2019, 754, 760, 2021, 2020, 623, 618, 666, 654, 406, 398, 446 );

/** Does this request need WPBS's front-end assets? When unsure, keep them. */
function za_page_needs_wpbs() {
	if ( ! is_singular() || isset( $_GET['elementor-preview'] ) ) {
		return true;
	}
	foreach ( array_keys( $_GET ) as $key ) {
		if ( false !== stripos( (string) $key, 'wpbs' ) ) {
			return true; // Invoice, payment or cancellation links.
		}
	}
	$id = (int) get_queried_object_id();
	if ( in_array( $id, ZA_WPBS_ASSET_PAGES, true ) ) {
		return true;
	}
	$post = get_post( $id );
	if ( $post && false !== strpos( $post->post_content, '[wpbs' ) ) {
		return true;
	}
	return false !== strpos( (string) get_post_meta( $id, '_elementor_data', true ), 'wpbs' );
}

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( za_page_needs_wpbs() ) {
			return;
		}
		foreach ( wp_scripts()->queue as $handle ) {
			if ( 0 === strpos( $handle, 'wpbs' ) ) {
				wp_dequeue_script( $handle );
			}
		}
		foreach ( wp_styles()->queue as $handle ) {
			if ( 0 === strpos( $handle, 'wpbs' ) ) {
				wp_dequeue_style( $handle );
			}
		}
	},
	100
);
