/**
 * Smart Address Book for WooCommerce - Public JS
 */

(function($) {
	'use strict';

	$(document).ready(function() {

		/**
		 * Helper: Display inline notification.
		 */
		function showNotice(message, isError) {
			var $container = $('#sab-notices');
			if (!$container.length) {
				$container = $('<div id="sab-notices"></div>').prependTo('.sab-address-book-wrapper');
			}
			var cssClass = isError ? 'sab-notice sab-notice-error' : 'sab-notice sab-notice-success';
			var $notice = $('<div class="' + cssClass + '">' + message + '</div>');
			$container.html($notice);

			setTimeout(function() {
				$notice.fadeOut(function() {
					$(this).remove();
				});
			}, 4000);
		}

		/**
		 * 1. Delete Address Handler
		 */
		$(document).on('click', '.sab-action-delete', function(e) {
			e.preventDefault();

			var $btn = $(this);
			var addressId = $btn.data('id');
			var $card = $btn.closest('.sab-card');

			if (!confirm(sab_params.i18n.confirm_delete || 'Are you sure you want to delete this address?')) {
				return;
			}

			$btn.prop('disabled', true).text(sab_params.i18n.deleting || 'Deleting...');

			$.ajax({
				url: sab_params.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'sab_delete_address',
					address_id: addressId,
					nonce: sab_params.nonce
				},
				success: function(response) {
					if (response.success) {
						$card.fadeOut(300, function() {
							$(this).remove();
						});
						showNotice(response.data.message || 'Address deleted.', false);
					} else {
						alert(response.data.message || sab_params.i18n.error);
						$btn.prop('disabled', false).text('Delete');
					}
				},
				error: function() {
					alert(sab_params.i18n.error || 'Server error.');
					$btn.prop('disabled', false).text('Delete');
				}
			});
		});

		/**
		 * 2. Set as Default Address Handler
		 */
		$(document).on('click', '.sab-action-set-default', function(e) {
			e.preventDefault();

			var $btn = $(this);
			var addressId = $btn.data('id');
			var type = $btn.data('type');
			var $card = $btn.closest('.sab-card');
			var $section = $card.closest('.sab-section');

			$btn.prop('disabled', true).text('Setting...');

			$.ajax({
				url: sab_params.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'sab_set_default',
					address_id: addressId,
					type: type,
					nonce: sab_params.nonce
				},
				success: function(response) {
					if (response.success) {
						// Remove previous default badge & re-enable "Set Default" button in this section
						$section.find('.sab-card').removeClass('sab-is-default');
						$section.find('.sab-default-badge').remove();

						$section.find('.sab-card').each(function() {
							var $c = $(this);
							var cid = $c.data('id');
							var $actions = $c.find('.sab-card-actions');
							if (!$actions.find('.sab-action-set-default').length && cid != addressId) {
								$actions.append('<button type="button" class="button button-small sab-action-set-default" data-id="' + cid + '" data-type="' + type + '">Set as Default</button>');
							}
						});

						// Add default badge to current card
						$card.addClass('sab-is-default');
						var defaultText = (type === 'billing') ? 'Default Billing' : 'Default Shipping';
						$card.find('.sab-card-top').append('<span class="sab-badge sab-default-badge">' + defaultText + '</span>');
						$btn.remove();

						showNotice(response.data.message || 'Default address updated.', false);
					} else {
						alert(response.data.message || sab_params.i18n.error);
						$btn.prop('disabled', false).text('Set as Default');
					}
				},
				error: function() {
					alert(sab_params.i18n.error || 'Server error.');
					$btn.prop('disabled', false).text('Set as Default');
				}
			});
		});

		/**
		 * 3. FR-09: Live Address Search Filter
		 */
		var searchTimeout;
		$('#sab-address-search-input').on('keyup input', function() {
			var query = $(this).val().toLowerCase().trim();

			clearTimeout(searchTimeout);
			searchTimeout = setTimeout(function() {
				if (query === '') {
					$('.sab-card').show();
					return;
				}

				$('.sab-card').each(function() {
					var $card = $(this);
					var text = $card.text().toLowerCase();
					if (text.indexOf(query) !== -1) {
						$card.fadeIn(150);
					} else {
						$card.fadeOut(150);
					}
				});
			}, 200);
		});

		/**
		 * 4. CSV Export
		 */
		$(document).on('click', '.sab-export-btn', function(e) {
			e.preventDefault();
			var exportUrl = sab_params.ajax_url + '?action=sab_export_addresses&nonce=' + encodeURIComponent(sab_params.nonce);
			window.location.href = exportUrl;
		});

		/**
		 * 5. CSV Import
		 */
		$(document).on('click', '.sab-import-btn', function(e) {
			e.preventDefault();
			$('#sab-csv-file-input').click();
		});

		$('#sab-csv-file-input').on('change', function() {
			var file = this.files[0];
			if (!file) {
				return;
			}

			var formData = new FormData();
			formData.append('action', 'sab_import_addresses');
			formData.append('nonce', sab_params.nonce);
			formData.append('file', file);

			showNotice('Importing addresses...', false);

			$.ajax({
				url: sab_params.ajax_url,
				type: 'POST',
				data: formData,
				contentType: false,
				processData: false,
				dataType: 'json',
				success: function(response) {
					if (response.success) {
						var res = response.data;
						var msg = 'Import finished: ' + res.imported + ' imported, ' + res.skipped + ' skipped.';
						if (res.errors && res.errors.length) {
							msg += '<br/>Errors:<br/>' + res.errors.join('<br/>');
						}
						showNotice(msg, false);
						setTimeout(function() {
							location.reload();
						}, 2000);
					} else {
						showNotice(response.data.message || 'Import failed.', true);
					}
				},
				error: function() {
					showNotice('An error occurred during import.', true);
				}
			});
		});

		/**
		 * 6. Dynamic Country-State Dependent Dropdown
		 */
		function updateStateField(country, currentStateVal) {
			var $stateField = $('#sab_state_field');
			var $state = $('#sab_state');
			if (!$stateField.length) {
				return;
			}

			// Retrieve WooCommerce built-in country-state data from sab_params or wc_country_select_params
			var allStates = {};
			if (typeof sab_params !== 'undefined' && sab_params.states) {
				allStates = sab_params.states;
			} else if (typeof wc_country_select_params !== 'undefined' && wc_country_select_params.countries) {
				try {
					allStates = JSON.parse(wc_country_select_params.countries.replace(/&quot;/g, '"'));
				} catch (e) {
					allStates = {};
				}
			}

			var countryStates = (country && allStates[country]) ? allStates[country] : null;
			var placeholder = (typeof sab_params !== 'undefined' && sab_params.i18n && sab_params.i18n.select_state_text)
				? sab_params.i18n.select_state_text
				: 'Select an option…';

			// Remove any select2/selectWoo instance attached to previous state element
			if ($().selectWoo && $state.data('select2')) {
				$state.selectWoo('destroy');
			}
			$stateField.find('.select2-container').remove();

			if (countryStates && !$.isEmptyObject(countryStates)) {
				var $select = $('<select name="state" id="sab_state" class="state_select"></select>');
				$select.append($('<option value=""></option>').text(placeholder));

				$.each(countryStates, function(code, name) {
					var $opt = $('<option></option>').val(code).text(name);
					if (currentStateVal && (currentStateVal === code || currentStateVal === name)) {
						$opt.prop('selected', true);
					}
					$select.append($opt);
				});

				if ($state.length) {
					$state.replaceWith($select);
				} else {
					$stateField.append($select);
				}

				if ($().selectWoo) {
					$select.selectWoo();
				}
			} else {
				var $input = $('<input type="text" class="input-text" name="state" id="sab_state" />');
				if (currentStateVal) {
					$input.val(currentStateVal);
				}

				if ($state.length) {
					$state.replaceWith($input);
				} else {
					$stateField.append($input);
				}
			}

			$(document.body).trigger('country_to_state_changed', [country, $('#sab-address-form')]);
		}

		// Whenever selected country changes, dynamically update state dropdown and clear previous state selection
		$(document).on('change', '#sab_country', function() {
			var country = $(this).val();
			updateStateField(country, '');
		});

		/**
		 * 7. Address Form Validation & Field Error Handling
		 */
		function validateAddressForm($form) {
			var errors = {};
			var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

			var firstName = $.trim($form.find('[name="first_name"]').val());
			if (!firstName) {
				errors.first_name = 'First Name is a required field.';
			}

			var lastName = $.trim($form.find('[name="last_name"]').val());
			if (!lastName) {
				errors.last_name = 'Last Name is a required field.';
			}

			var country = $.trim($form.find('[name="country"]').val());
			if (!country) {
				errors.country = 'Country / Region is a required field.';
			}

			var address1 = $.trim($form.find('[name="address_1"]').val());
			if (!address1) {
				errors.address_1 = 'Street Address is a required field.';
			}

			var city = $.trim($form.find('[name="city"]').val());
			if (!city) {
				errors.city = 'Town / City is a required field.';
			}

			var postalCode = $.trim($form.find('[name="postal_code"]').length ? $form.find('[name="postal_code"]').val() : $form.find('[name="postcode"]').val());
			if (!postalCode) {
				errors.postal_code = 'Postcode / ZIP is a required field.';
			}

			var state = $.trim($form.find('[name="state"]').val());
			var $stateSelect = $form.find('select[name="state"]');
			if ($stateSelect.length && !state) {
				errors.state = 'State / County is required for this country.';
			}

			// Email Address is strictly mandatory
			var rawEmail = $.trim($form.find('[name="email"]').val());
			if (!rawEmail) {
				errors.email = 'Email Address is a required field.';
			} else if (!emailRegex.test(rawEmail)) {
				errors.email = 'Please enter a valid email address.';
			}

			return errors;
		}

		function applyFormErrors($form, errors) {
			// Clear prior error indicators
			$form.find('.sab-field-error-message').remove();
			$form.find('.sab-has-error').removeClass('sab-has-error woocommerce-invalid');
			$form.find('.sab-input-error').removeClass('sab-input-error');

			var $firstInvalidField = null;

			$.each(errors, function(field, msg) {
				var possibleNames = [field];
				if (field === 'postal_code') possibleNames.push('postcode');
				if (field === 'postcode') possibleNames.push('postal_code');

				var $input = null;
				for (var i = 0; i < possibleNames.length; i++) {
					var $found = $form.find('[name="' + possibleNames[i] + '"]');
					if ($found.length) {
						$input = $found;
						break;
					}
				}

				if ($input && $input.length) {
					var $row = $input.closest('.form-row');
					$row.addClass('sab-has-error woocommerce-invalid');
					$input.addClass('sab-input-error');

					if ($row.find('.sab-field-error-message').length === 0) {
						$row.append($('<span class="sab-field-error-message" role="alert">' + msg + '</span>'));
					}

					if (!$firstInvalidField) {
						$firstInvalidField = $input;
					}
				}
			});

			if ($firstInvalidField && $firstInvalidField.length) {
				$('html, body').animate({
					scrollTop: Math.max(0, $firstInvalidField.offset().top - 120)
				}, 300, function() {
					$firstInvalidField.trigger('focus');
				});
			}
		}

		$('#sab-address-form').on('submit', function(e) {
			var $form = $(this);
			var errors = validateAddressForm($form);

			if (!$.isEmptyObject(errors)) {
				e.preventDefault();
				applyFormErrors($form, errors);
				return false;
			}
		});

		// Clear individual error highlight when user edits field
		$(document).on('input change', '#sab-address-form .sab-input-error, #sab-address-form .sab-has-error input, #sab-address-form .sab-has-error select', function() {
			var $el = $(this);
			var $row = $el.closest('.form-row');
			$el.removeClass('sab-input-error');
			$row.removeClass('sab-has-error woocommerce-invalid');
			$row.find('.sab-field-error-message').remove();
		});

		// Detect and highlight server-side validation notices on page load
		if ($('#sab-address-form').length) {
			var $wcErrors = $('.woocommerce-error li, .woocommerce-notices-wrapper .woocommerce-error li');
			if ($wcErrors.length) {
				var backendErrors = {};
				$wcErrors.each(function() {
					var text = $(this).text().toLowerCase();
					if (text.indexOf('email') !== -1) {
						backendErrors.email = $(this).text();
					} else if (text.indexOf('first name') !== -1) {
						backendErrors.first_name = $(this).text();
					} else if (text.indexOf('last name') !== -1) {
						backendErrors.last_name = $(this).text();
					} else if (text.indexOf('street address') !== -1 || text.indexOf('address_1') !== -1) {
						backendErrors.address_1 = $(this).text();
					} else if (text.indexOf('town') !== -1 || text.indexOf('city') !== -1) {
						backendErrors.city = $(this).text();
					} else if (text.indexOf('postcode') !== -1 || text.indexOf('zip') !== -1) {
						backendErrors.postal_code = $(this).text();
					} else if (text.indexOf('country') !== -1) {
						backendErrors.country = $(this).text();
					} else if (text.indexOf('state') !== -1) {
						backendErrors.state = $(this).text();
					}
				});
				if (!$.isEmptyObject(backendErrors)) {
					applyFormErrors($('#sab-address-form'), backendErrors);
				}
			}
		}

	});

})(jQuery);

