<?php
/**
 * Coupon tweaks WP Booking System can't do in its settings.
 *
 * A percentage coupon either discounts the nights only ("calendar") or the whole total ("all"). Some codes need
 * "everything free except the cleaning fee", so for those this replaces the discount amount with
 * -(subtotal - cleaning fee). The coupon must be a percentage coupon applied to the whole total ("all").
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Codes where the guest pays only the cleaning fee. */
const ZA_COUPONS_PAY_CLEANING_ONLY = array( 'SZABADOSFAM' );

/** Total of the cleaning-fee line(s) in a WPBS price array (matched by label, so it survives small renames). */
function za_cleaning_fee_total( $prices ) {
	$total = 0;
	foreach ( (array) ( $prices['extras'] ?? array() ) as $extra ) {
		$label = $extra['original_label'] ?? ( $extra['label'] ?? '' );
		if ( false !== stripos( $label, 'clean' ) ) {
			$total += (float) $extra['total'];
		}
	}
	return $total;
}

add_filter(
	'wpbs_discounts_coupon_percentage_value',
	function ( $value, $coupon, $prices ) {
		$options = $coupon->get( 'options' );
		$code    = strtoupper( (string) ( $options['code'] ?? '' ) );

		if ( ! in_array( $code, ZA_COUPONS_PAY_CLEANING_ONLY, true ) || 'all' !== ( $options['apply_to'] ?? '' ) ) {
			return $value;
		}

		$discount = (float) $prices['subtotal'] - za_cleaning_fee_total( $prices );
		return -1 * max( 0, $discount );
	},
	10,
	3
);
