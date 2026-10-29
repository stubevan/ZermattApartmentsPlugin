<?php
/*
 * Plugin Name:  Zermatt Apartments Website Support
 * Plugin URI:   https://github.com/stubevan/ZermattApartmentsPlugin
 * Description:  Support functions for the Zermatt Apartments booking system: booking form checks, the Deposit Received email, email backfill tools, past guest picker on Add Booking, apartment structured data.
 * Version:      1.5
 * Requires PHP: 8.0
 * Author:       Stu Bevan
 * Author URI:   https://mees.st
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZA_PLUGIN_VERSION', '1.5' );

require_once ZA_PLUGIN_DIR . 'includes/config.php';
require_once ZA_PLUGIN_DIR . 'includes/booking-validation.php';
require_once ZA_PLUGIN_DIR . 'includes/deposit-email.php';
require_once ZA_PLUGIN_DIR . 'includes/email-backfill.php';
require_once ZA_PLUGIN_DIR . 'includes/coupons.php';
require_once ZA_PLUGIN_DIR . 'includes/paid-email.php';
require_once ZA_PLUGIN_DIR . 'includes/customer-prefill.php';
require_once ZA_PLUGIN_DIR . 'includes/schema.php';
require_once ZA_PLUGIN_DIR . 'includes/performance.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once ZA_PLUGIN_DIR . 'includes/class-za-cli.php';
}
