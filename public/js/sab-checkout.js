/**
 * Smart Address Book for WooCommerce - Checkout Integration JS
 */

(function($) {
	'use strict';

	$(document).ready(function() {

		/**
		 * Helper: Populate WooCommerce checkout input fields.
		 */
		function populateCheckoutFields(prefix, addr) {
			if (!addr) {
				return;
			}

			var fields = {
				'first_name': addr.first_name || '',
				'last_name':  addr.last_name || '',
				'company':    addr.company || '',
				'country':    addr.country || '',
				'address_1':  addr.address_1 || '',
				'address_2':  addr.address_2 || '',
				'city':       addr.city || '',
				'state':      addr.state || '',
				'postcode':   addr.postal_code || (addr.postcode || ''),
				'phone':      addr.phone || '',
				'email':      addr.email || ''
			};

			$.each(fields, function(key, val) {
				var $input = $('#' + prefix + '_' + key);
				if ($input.length) {
					$input.val(val);
					// For select fields like country / state, trigger change
					if ($input.is('select')) {
						$input.trigger('change');
					}
				}
			});

			// If state is not updated immediately due to dynamic state refresh, set timeout
			if (fields.state) {
				setTimeout(function() {
					var $stateInput = $('#' + prefix + '_state');
					if ($stateInput.length && $stateInput.val() !== fields.state) {
						$stateInput.val(fields.state).trigger('change');
					}
				}, 250);
			}

			// Trigger WooCommerce update checkout
			$(document.body).trigger('update_checkout');
		}

		/**
		 * Helper: Clear fields when "+ Enter a new address" is chosen.
		 */
		function clearCheckoutFields(prefix) {
			var keys = ['first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode'];
			$.each(keys, function(i, key) {
				var $input = $('#' + prefix + '_' + key);
				if ($input.length && !$input.is('select')) {
					$input.val('');
				}
			});
			$(document.body).trigger('update_checkout');
		}

		/**
		 * 1. Checkout Address Selector Change Handler
		 */
		$(document).on('change', '.sab-checkout-address-dropdown', function() {
			var $select = $(this);
			var type = $select.data('type') || 'billing';
			var prefix = (type === 'shipping') ? 'shipping' : 'billing';
			var val = $select.val();

			if (val === 'new') {
				clearCheckoutFields(prefix);
				$('.sab-checkout-save-' + type).slideDown();
				return;
			}

			var $selectedOption = $select.find('option:selected');
			var rawAddress = $selectedOption.data('address');

			if (rawAddress) {
				populateCheckoutFields(prefix, rawAddress);
				$('.sab-checkout-save-' + type).slideUp();
			} else if (val) {
				// Fallback AJAX fetch
				$.ajax({
					url: sab_checkout_params.ajax_url,
					type: 'GET',
					dataType: 'json',
					data: {
						action: 'sab_get_checkout_address',
						address_id: val,
						type: type,
						nonce: sab_checkout_params.nonce
					},
					success: function(res) {
						if (res.success && res.data.fields) {
							$.each(res.data.fields, function(fieldKey, fieldVal) {
								var $f = $('#' + fieldKey);
								if ($f.length) {
									$f.val(fieldVal).trigger('change');
								}
							});
							$(document.body).trigger('update_checkout');
							$('.sab-checkout-save-' + type).slideUp();
						}
					}
				});
			}
		});

		/**
		 * 2. Auto-populate default address on initial load
		 */
		$('.sab-checkout-address-dropdown').each(function() {
			var $dropdown = $(this);
			var val = $dropdown.val();
			if (val && val !== 'new') {
				var $opt = $dropdown.find('option:selected');
				var raw = $opt.data('address');
				var type = $dropdown.data('type') || 'billing';
				var prefix = (type === 'shipping') ? 'shipping' : 'billing';
				if (raw) {
					populateCheckoutFields(prefix, raw);
					$('.sab-checkout-save-' + type).hide();
				}
			}
		});

		/**
		 * 3. Toggle Nickname input field when "Save this address" is toggled
		 */
		$(document).on('change', '.sab-save-checkout-checkbox', function() {
			var $cb = $(this);
			var id = $cb.attr('id');
			var type = (id === 'sab_save_billing') ? 'billing' : 'shipping';
			var $nicknameRow = $('#sab_nickname_row_' + type);

			if ($cb.is(':checked')) {
				$nicknameRow.slideDown();
			} else {
				$nicknameRow.slideUp();
			}
		});

		/**
		 * 4. WooCommerce Blocks Checkout Progressive Enhancement
		 */
		if (window.wp && window.wp.hooks && window.wp.data) {
			// Hook into checkout store changes if available
			var unsubscribe = wp.data.subscribe(function() {
				var checkoutStore = wp.data.select('wc/store/checkout');
				if (checkoutStore && checkoutStore.getCustomerData) {
					// Block checkout initialized
				}
			});
		}

	});

})(jQuery);

