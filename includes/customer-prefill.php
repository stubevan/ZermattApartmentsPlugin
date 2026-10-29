<?php
/**
 * "Copy details from a past guest" picker on the wp-admin Add Booking form.
 *
 * WPBS can't start a booking from an existing guest, so returning guests had to be retyped. This adds a picker
 * above the form fields (manual bookings only, never the public form). Choosing a guest copies the contact and
 * guest fields (ZA_PREFILL_FIELDS) from their most recent booking into the form; everything stays editable.
 *
 * Guests are found by email (field ZA_EMAIL_FIELD) across ZA_PAST_GUEST_CALENDARS. Guest data is only sent to
 * logged-in users who can access the calendar the form belongs to.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ZA_PREFILL_NONCE = 'za_past_guest_details';

/** A booking field's saved value, or ''. */
function za_booking_field_value( $booking, $field_id ) {
	foreach ( (array) $booking->get( 'fields' ) as $field ) {
		if ( (int) ( $field['id'] ?? 0 ) === (int) $field_id ) {
			$value = $field['user_value'] ?? '';
			return is_scalar( $value ) ? trim( (string) $value ) : '';
		}
	}
	return '';
}

/**
 * Past guests: lowercased email => latest booking (highest ID) for that email, sorted by surname.
 * Each entry: booking_id, first, surname, email, start (Y-m-d), apartment. Trashed bookings are skipped.
 */
function za_past_guests() {
	$guests = array();
	foreach ( ZA_PAST_GUEST_CALENDARS as $calendar_id => $apartment ) {
		$bookings = wpbs_get_bookings(
			array(
				'calendar_id' => $calendar_id,
				'status'      => array( 'accepted', 'pending' ),
				'number'      => -1,
			)
		);
		foreach ( (array) $bookings as $booking ) {
			$email = strtolower( za_booking_field_value( $booking, ZA_EMAIL_FIELD ) );
			if ( ! is_email( $email ) ) {
				continue; // VRBO/Airbnb blocks and the like.
			}
			$id = (int) $booking->get( 'id' );
			if ( isset( $guests[ $email ] ) && $guests[ $email ]['booking_id'] > $id ) {
				continue;
			}
			$guests[ $email ] = array(
				'booking_id' => $id,
				'first'      => za_booking_field_value( $booking, 1 ),
				'surname'    => za_booking_field_value( $booking, 34 ),
				'email'      => $email,
				'start'      => substr( (string) $booking->get( 'start_date' ), 0, 10 ),
				'apartment'  => $apartment,
			);
		}
	}
	uasort(
		$guests,
		function ( $a, $b ) {
			return strcasecmp( $a['surname'] . ' ' . $a['first'], $b['surname'] . ' ' . $b['first'] );
		}
	);
	return $guests;
}

/** The calendar a live apartment form belongs to, or 0. */
function za_calendar_for_form( $form_id ) {
	foreach ( za_apartments() as $apartment ) {
		if ( (int) $apartment['form'] === (int) $form_id ) {
			return (int) $apartment['calendar'];
		}
	}
	return 0;
}

/** Can the current user see past guests for this form? */
function za_can_prefill( $form_id ) {
	$calendar_id = za_calendar_for_form( $form_id );
	return $calendar_id && is_user_logged_in() && function_exists( 'wpbs_current_user_can_access_calendar' )
		&& wpbs_current_user_can_access_calendar( $calendar_id );
}

// The picker, above the fields of the admin Add Booking form only.
add_filter(
	'wpbs_form_outputter_form_fields_before',
	function ( $html, $form, $args ) {
		$form_id = (int) $form->get( 'id' );
		if ( empty( $args['manual_booking'] ) || ! za_can_prefill( $form_id ) ) {
			return $html;
		}

		$list_id = 'za-past-guests-' . $form_id;
		$options = '';
		foreach ( za_past_guests() as $guest ) {
			$name  = trim( $guest['surname'] . ' ' . $guest['first'] );
			$when  = $guest['start'] ? date_i18n( 'M Y', strtotime( $guest['start'] ) ) : '';
			$label = $name . ' – ' . $guest['email'] . ' – ' . $guest['apartment'] . ( $when ? ', ' . $when : '' );
			$options .= '<option value="' . esc_attr( $label ) . '" data-email="' . esc_attr( $guest['email'] ) . '"></option>';
		}

		// No name attributes, so nothing here is posted with the booking.
		$html .= '<div class="za-past-guest" data-form="' . $form_id . '" data-nonce="' . esc_attr( wp_create_nonce( ZA_PREFILL_NONCE ) ) . '"'
			. ' style="margin:0 0 20px;padding:12px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px">'
			. '<label for="' . esc_attr( $list_id ) . '-input" style="display:block;font-weight:600;margin-bottom:6px">Copy details from a past guest</label>'
			. '<input type="text" id="' . esc_attr( $list_id ) . '-input" class="za-past-guest-input" list="' . esc_attr( $list_id ) . '" autocomplete="off"'
			. ' placeholder="Start typing a surname or email" style="width:100%;max-width:520px">'
			. '<datalist id="' . esc_attr( $list_id ) . '">' . $options . '</datalist>'
			. '<div class="za-past-guest-status" style="margin-top:6px;font-size:12px;color:#50575e">Copies name, email, address, phone, guest numbers and the guest list from their latest booking. Check them before saving.</div>'
			. '</div>';
		return $html;
	},
	10,
	3
);

// AJAX: the copyable fields from a past guest's latest booking.
add_action(
	'wp_ajax_' . ZA_PREFILL_NONCE,
	function () {
		$form_id = absint( $_POST['form_id'] ?? 0 );
		if ( ! check_ajax_referer( ZA_PREFILL_NONCE, 'nonce', false ) || ! za_can_prefill( $form_id ) ) {
			wp_send_json_error( array( 'message' => 'Not allowed.' ), 403 );
		}

		$email  = strtolower( sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ) );
		$guests = za_past_guests();
		if ( ! isset( $guests[ $email ] ) ) {
			wp_send_json_error( array( 'message' => 'No past booking found for that guest.' ), 404 );
		}

		$booking = wpbs_get_booking( $guests[ $email ]['booking_id'] );
		$fields  = array();
		foreach ( ZA_PREFILL_FIELDS as $field_id ) {
			$fields[ $field_id ] = za_booking_field_value( $booking, $field_id );
		}
		wp_send_json_success(
			array(
				'booking_id' => $guests[ $email ]['booking_id'],
				'fields'     => $fields,
			)
		);
	}
);

// The picker's script, on the WPBS calendar screens (where the Add Booking modal opens).
add_action(
	'admin_enqueue_scripts',
	function () {
		if ( 'wpbs-calendars' !== ( $_GET['page'] ?? '' ) ) {
			return;
		}
		wp_enqueue_script( 'za-customer-prefill', plugins_url( 'assets/customer-prefill.js', ZA_PLUGIN_DIR . 'zermatt-apartments-plugin.php' ), array( 'jquery' ), ZA_PLUGIN_VERSION, true );
		wp_localize_script( 'za-customer-prefill', 'zaPrefill', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ) );
	}
);
