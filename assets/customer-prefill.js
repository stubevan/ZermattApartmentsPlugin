/**
 * "Copy details from a past guest" on the wp-admin Add Booking form (see includes/customer-prefill.php).
 * WPBS re-draws the form via AJAX, so all handlers are delegated from document.
 */
(function ($) {
	'use strict';

	function status($box, text, isError) {
		$box.find('.za-past-guest-status').text(text).css('color', isError ? '#d63638' : '#00a32a');
	}

	function setPhone($input, value) {
		var globals = window.intlTelInputGlobals;
		var iti = globals && (globals.getInstance ? globals.getInstance($input.get(0)) : globals.instances[$input.data('intl-tel-input-id')]);
		if (iti) {
			iti.setNumber(value);
		} else {
			$input.val(value);
		}
	}

	function fill($box, guest) {
		var formId = $box.data('form');
		var $form = $box.closest('form');
		$.each(guest.fields, function (fieldId, value) {
			var $input = $form.find('[name="wpbs-input-' + formId + '-' + fieldId + '"]');
			if (!$input.length || value === '') {
				return;
			}
			if ($input.closest('.wpbs-form-field-phone').length) {
				setPhone($input, value);
			} else {
				$input.val(value);
			}
			// Lets WPBS recalculate the price and tourist tax from the guest numbers.
			$input.trigger('input').trigger('change');
		});
		status($box, 'Filled from booking #' + guest.booking_id + '. Check the details, then choose dates and payment.', false);
	}

	function lookup($box, email) {
		if (typeof zaPrefill === 'undefined') {
			status($box, 'The picker is not working on this page. Please type the details.', true);
			return;
		}
		status($box, 'Loading…', false);
		$.post(zaPrefill.ajaxUrl, {
			action: 'za_past_guest_details',
			nonce: $box.data('nonce'),
			form_id: $box.data('form'),
			email: email
		}).done(function (response) {
			if (response && response.success) {
				fill($box, response.data);
			} else {
				status($box, (response && response.data && response.data.message) || 'Could not load that guest.', true);
			}
		}).fail(function (xhr) {
			var message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;
			status($box, message || 'Could not load that guest.', true);
		});
	}

	// A guest is chosen when the typed text matches one of the list entries exactly.
	$(document).on('input change', '.za-past-guest-input', function () {
		var $input = $(this);
		var $box = $input.closest('.za-past-guest');
		var value = $input.val();
		var $option = $('#' + $input.attr('list')).find('option').filter(function () {
			return this.value === value;
		});
		if ($option.length && $box.data('loaded') !== value) {
			$box.data('loaded', value);
			lookup($box, $option.data('email'));
		}
	});

	// Enter in the picker must not submit the booking.
	$(document).on('keydown', '.za-past-guest-input', function (e) {
		if (e.key === 'Enter') {
			e.preventDefault();
		}
	});
})(jQuery);
