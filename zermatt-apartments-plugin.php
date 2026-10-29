<?php
/*
 * Plugin Name:  Zermatt Apartments Website Support
 * Plugin URI:   https://github.com/stubevan/ZermattApartmentsPlugin
 * Description:  Support functions for the Zermatt Apartments booking system: booking form checks, the Deposit Received email, email backfill tools.
 * Version:      1.0
 * Requires PHP: 8.0
 * Author:       Stu Bevan
 * Author URI:   https://mees.st
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once ZA_PLUGIN_DIR . 'includes/config.php';
require_once ZA_PLUGIN_DIR . 'includes/booking-validation.php';
require_once ZA_PLUGIN_DIR . 'includes/deposit-email.php';
require_once ZA_PLUGIN_DIR . 'includes/email-backfill.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once ZA_PLUGIN_DIR . 'includes/class-za-cli.php';
}
