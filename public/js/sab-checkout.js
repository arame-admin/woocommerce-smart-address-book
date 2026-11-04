/**
 * Smart Address Book for WooCommerce - Classic Checkout Integration JS
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

			// Trigger WooCommerce update checkout to recalculate shipping & taxes
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
		 * Helper: Dynamically update state dropdown or input in modal.
		 */
		function updateModalStates(countryCode, selectedState) {
			var states = (typeof sab_checkout_params !== 'undefined' && sab_checkout_params.states) ? sab_checkout_params.states : {};
			var $stateContainer = $('#sab_modal_state_field');
			var $currentInput = $('#sab_modal_state');

			if (states[countryCode] && Object.keys(states[countryCode]).length > 0) {
				var countryStates = states[countryCode];
				var $select = $('<select name="state" id="sab_modal_state" class="state_select"></select>');
				var placeholder = (typeof sab_checkout_params !== 'undefined' && sab_checkout_params.i18n && sab_checkout_params.i18n.select_state_text)
					? sab_checkout_params.i18n.select_state_text
					: 'Select an option&hellip;';
				$select.append('<option value="">' + placeholder + '</option>');
				$.each(countryStates, function(code, name) {
					var $opt = $('<option></option>').val(code).text(name);
					if (code === selectedState) {
						$opt.prop('selected', true);
					}
					$select.append($opt);
				});
				$currentInput.replaceWith($select);
			} else {
				var $text = $('<input type="text" class="input-text" name="state" id="sab_modal_state" />');
				if (selectedState) {
					$text.val(selectedState);
				}
				$currentInput.replaceWith($text);
			}
		}

		/**
		 * Helper: Validate Address Form fields client-side.
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
				errors.country = 'Please choose a valid country / region.';
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

		/**
		 * Helper: Apply field errors, highlight only invalid fields, and scroll to first error.
		 */
		function applyFormErrors($form, errors, $scrollContainer) {
			// Clear previous error messages & classes
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
						$row.append($('<span class="sab-field-error-message">' + msg + '</span>'));
					}

					if (!$firstInvalidField) {
						$firstInvalidField = $input;
					}
				}
			});

			// Automatically scroll to the first invalid field
			if ($firstInvalidField && $firstInvalidField.length) {
				if ($scrollContainer && $scrollContainer.length) {
					var containerTop = $scrollContainer.scrollTop();
					var elementOffsetTop = $firstInvalidField.offset().top - $scrollContainer.offset().top + containerTop - 30;
					$scrollContainer.animate({ scrollTop: Math.max(0, elementOffsetTop) }, 300, function() {
						$firstInvalidField.trigger('focus');
					});
				} else {
					$('html, body').animate({
						scrollTop: Math.max(0, $firstInvalidField.offset().top - 100)
					}, 300, function() {
						$firstInvalidField.trigger('focus');
					});
				}
			}
		}

		/**
		 * Clear individual error when customer types or changes field
		 */
		$(document).on('input change', '.sab-input-error, .sab-has-error input, .sab-has-error select', function() {
			var $el = $(this);
			var $row = $el.closest('.form-row');
			$el.removeClass('sab-input-error');
			$row.removeClass('sab-has-error woocommerce-invalid');
			$row.find('.sab-field-error-message').remove();
		});

		/**
		 * Helper: Format address object into HTML lines for summary card.
		 */
		function formatAddressSummary(addr, type) {
			if (!addr) {
				return '';
			}
			var lines = [];
			var name = $.trim((addr.first_name || '') + ' ' + (addr.last_name || ''));
			if (addr.nickname) {
				name += ' (' + addr.nickname + ')';
			}
			if (name) {
				lines.push('<strong>' + name + '</strong>');
			}
			if (addr.company) {
				lines.push(addr.company);
			}
			var addrLine = addr.address_1 || '';
			if (addr.address_2) {
				addrLine += ', ' + addr.address_2;
			}
			if (addrLine) {
				lines.push(addrLine);
			}
			var cityLine = addr.city || '';
			if (addr.state) {
				cityLine += ', ' + addr.state;
			}
			if (addr.postal_code || addr.postcode) {
				cityLine += ' ' + (addr.postal_code || addr.postcode);
			}
			if (cityLine) {
				lines.push(cityLine);
			}
			if (addr.country) {
				lines.push(addr.country);
			}
			var contact = [];
			if (addr.phone) {
				contact.push('Phone: ' + addr.phone);
			}
			if (addr.email) {
				contact.push('Email: ' + addr.email);
			}
			if (contact.length) {
				lines.push(contact.join(' | '));
			}
			return lines.join('<br>');
		}

		/**
		 * 1. Checkout Address Selector Dropdown Change Handler
		 */
		$(document).on('change', '.sab-checkout-address-dropdown', function() {
			var $select = $(this);
			var type = $select.data('type') || 'billing';
			var prefix = (type === 'shipping') ? 'shipping' : 'billing';
			var val = $select.val();
			var $fieldWrapper = (type === 'billing')
				? $('.woocommerce-billing-fields__field-wrapper')
				: $('.woocommerce-shipping-fields__field-wrapper');
			var $actions = $('#sab_' + type + '_inline_actions');
			var $summaryBox = $('#sab_' + type + '_summary_box');

			if (val === 'new') {
				$('#sab_' + type + '_address_id').val('');
				$summaryBox.hide();
				clearCheckoutFields(prefix);
				$fieldWrapper.addClass('sab-form-expanded').removeClass('sab-form-collapsed').slideDown(250);
				$actions.hide();
				$('.sab-checkout-save-' + type).addClass('sab-form-expanded').removeClass('sab-form-collapsed').slideDown();
				return;
			}

			$('#sab_' + type + '_address_id').val(val);

			var $selectedOption = $select.find('option:selected');
			var rawAddress = $selectedOption.data('address');

			if (rawAddress) {
				populateCheckoutFields(prefix, rawAddress);
				$('#sab_' + type + '_summary_text').html(formatAddressSummary(rawAddress, type));
				$summaryBox.show();
				$actions.hide();
				$fieldWrapper.removeClass('sab-form-expanded').addClass('sab-form-collapsed').slideUp(250);
				$('.sab-checkout-save-' + type).removeClass('sab-form-expanded').addClass('sab-form-collapsed').slideUp();
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
							$summaryBox.show();
							$actions.hide();
							$fieldWrapper.removeClass('sab-form-expanded').addClass('sab-form-collapsed').slideUp(250);
							$('.sab-checkout-save-' + type).removeClass('sab-form-expanded').addClass('sab-form-collapsed').slideUp();
						}
					}
				});
			}
		});

		/**
		 * 2. Auto-populate default address on initial load & Setup Address Summaries
		 * Both Billing and Shipping follow identical behavior:
		 * Display address summary, hide form fields initially.
		 */
		function initCheckoutAddressSummaries() {
			// Remove any duplicate shipping summaries
			$('#sab_shipping_summary_box').slice(1).remove();
			$('#sab_billing_summary_box').slice(1).remove();

			['billing', 'shipping'].forEach(function(type) {
				var prefix = (type === 'shipping') ? 'shipping' : 'billing';
				var $dropdown = $('#sab_' + type + '_address_select');
				var $summaryBox = $('#sab_' + type + '_summary_box');
				var $fieldWrapper = (type === 'billing')
					? $('.woocommerce-billing-fields__field-wrapper')
					: $('.woocommerce-shipping-fields__field-wrapper');
				var $actions = $('#sab_' + type + '_inline_actions');

				// Ensure inline action buttons are placed at the bottom below all fields
				if ($actions.length && $fieldWrapper.length && !$fieldWrapper.find('#sab_' + type + '_inline_actions').length) {
					$fieldWrapper.append($actions);
				}

				if ($dropdown.length) {
					var val = $dropdown.val();
					if (val && val !== 'new') {
						$('#sab_' + type + '_address_id').val(val);
						var $opt = $dropdown.find('option:selected');
						var raw = $opt.data('address');
						if (raw) {
							populateCheckoutFields(prefix, raw);
							$('#sab_' + type + '_summary_text').html(formatAddressSummary(raw, type));
						}
						// Initially display selected address as address summary, hide form fields
						$summaryBox.show();
						$fieldWrapper.removeClass('sab-form-expanded').addClass('sab-form-collapsed').hide();
						$actions.hide();
						$('.sab-checkout-save-' + type).removeClass('sab-form-expanded').addClass('sab-form-collapsed').hide();
					} else if (val === 'new') {
						$summaryBox.hide();
						$fieldWrapper.addClass('sab-form-expanded').removeClass('sab-form-collapsed').show();
					}
				}
			});
		}

		initCheckoutAddressSummaries();

		$(document.body).on('updated_checkout', function() {
			$('#sab_shipping_summary_box').slice(1).remove();
			$('#sab_billing_summary_box').slice(1).remove();
		});

		/**
		 * 3. Clicking "Edit" beside Address Summary opens form below that summary
		 */
		$(document).on('click', '.sab-summary-edit-btn', function(e) {
			e.preventDefault();
			var type = $(this).data('type') || 'billing';
			var prefix = (type === 'shipping') ? 'shipping' : 'billing';
			var $fieldWrapper = (type === 'billing')
				? $('.woocommerce-billing-fields__field-wrapper')
				: $('.woocommerce-shipping-fields__field-wrapper');
			var $actions = $('#sab_' + type + '_inline_actions');

			// Make sure actions are inside the bottom of the field wrapper
			if ($actions.length && !$fieldWrapper.find('#sab_' + type + '_inline_actions').length) {
				$fieldWrapper.append($actions);
			}

			// Save backup snapshot of current field values in case user clicks Cancel
			var backup = {};
			$fieldWrapper.find('input, select, textarea').each(function() {
				var name = $(this).attr('name');
				if (name) {
					backup[name] = $(this).val();
				}
			});
			$fieldWrapper.data('sab-backup', backup);

			// Open complete corresponding address form below that address summary
			$fieldWrapper.addClass('sab-form-expanded').removeClass('sab-form-collapsed');
			$('.sab-checkout-save-' + type).addClass('sab-form-expanded').removeClass('sab-form-collapsed');
			$actions.show();
			$fieldWrapper.slideDown(250);

			var $firstInput = $('#' + prefix + '_first_name');
			if ($firstInput.length) {
				$firstInput.trigger('focus');
			}
		});

		/**
		 * 4. Cancel button closes editing form without saving changes
		 */
		$(document).on('click', '.sab-inline-cancel-btn', function(e) {
			e.preventDefault();
			var type = $(this).data('type') || 'billing';
			var $fieldWrapper = (type === 'billing')
				? $('.woocommerce-billing-fields__field-wrapper')
				: $('.woocommerce-shipping-fields__field-wrapper');
			var $actions = $('#sab_' + type + '_inline_actions');

			// Restore backup values
			var backup = $fieldWrapper.data('sab-backup');
			if (backup) {
				$.each(backup, function(name, val) {
					var $el = $fieldWrapper.find('[name="' + name + '"]');
					if ($el.length) {
						$el.val(val);
						if ($el.is('select')) {
							$el.trigger('change');
						}
					}
				});
			}

			// Clear validation error highlights
			$fieldWrapper.find('.sab-field-error-message').remove();
			$fieldWrapper.find('.sab-has-error').removeClass('sab-has-error woocommerce-invalid');
			$fieldWrapper.find('.sab-input-error').removeClass('sab-input-error');

			// Close editing form
			$fieldWrapper.removeClass('sab-form-expanded').addClass('sab-form-collapsed');
			$('.sab-checkout-save-' + type).removeClass('sab-form-expanded').addClass('sab-form-collapsed').slideUp(250);
			$actions.hide();
			$fieldWrapper.slideUp(250);
		});

		/**
		 * 5. Save Changes button validates and updates the selected address in current checkout
		 */
		$(document).on('click', '.sab-inline-save-btn', function(e) {
			e.preventDefault();
			var type = $(this).data('type') || 'billing';
			var prefix = (type === 'shipping') ? 'shipping' : 'billing';
			var $fieldWrapper = (type === 'billing')
				? $('.woocommerce-billing-fields__field-wrapper')
				: $('.woocommerce-shipping-fields__field-wrapper, .shipping_address');
			var $actions = $('#sab_' + type + '_inline_actions');

			// Validate fields
			var errors = {};
			var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

			var firstName = $.trim($('#' + prefix + '_first_name').val());
			if (!firstName) errors[prefix + '_first_name'] = 'First Name is a required field.';

			var lastName = $.trim($('#' + prefix + '_last_name').val());
			if (!lastName) errors[prefix + '_last_name'] = 'Last Name is a required field.';

			var country = $.trim($('#' + prefix + '_country').val());
			if (!country) errors[prefix + '_country'] = 'Country / Region is a required field.';

			var address1 = $.trim($('#' + prefix + '_address_1').val());
			if (!address1) errors[prefix + '_address_1'] = 'Street Address is a required field.';

			var city = $.trim($('#' + prefix + '_city').val());
			if (!city) errors[prefix + '_city'] = 'Town / City is a required field.';

			var postcode = $.trim($('#' + prefix + '_postcode').val());
			if (!postcode) errors[prefix + '_postcode'] = 'Postcode / ZIP is a required field.';

			if (type === 'billing') {
				var email = $.trim($('#billing_email').val());
				if (!email) {
					errors['billing_email'] = 'Email Address is a required field.';
				} else if (!emailRegex.test(email)) {
					errors['billing_email'] = 'Please enter a valid email address.';
				}
			}

			// Clear old error highlights
			$fieldWrapper.find('.sab-field-error-message').remove();
			$fieldWrapper.find('.sab-has-error').removeClass('sab-has-error woocommerce-invalid');
			$fieldWrapper.find('.sab-input-error').removeClass('sab-input-error');

			if (Object.keys(errors).length > 0) {
				var $firstErr = null;
				$.each(errors, function(fieldId, msg) {
					var $input = $('#' + fieldId);
					if ($input.length) {
						var $row = $input.closest('.form-row');
						$row.addClass('sab-has-error woocommerce-invalid');
						$input.addClass('sab-input-error');
						if ($row.find('.sab-field-error-message').length === 0) {
							$row.append($('<span class="sab-field-error-message">' + msg + '</span>'));
						}
						if (!$firstErr) {
							$firstErr = $input;
						}
					}
				});
				if ($firstErr && $firstErr.length) {
					$('html, body').animate({ scrollTop: Math.max(0, $firstErr.offset().top - 100) }, 300);
					$firstErr.trigger('focus');
				}
				return false;
			}

			// Valid: construct updated address object
			var updatedAddr = {
				first_name:  $('#' + prefix + '_first_name').val() || '',
				last_name:   $('#' + prefix + '_last_name').val() || '',
				company:     $('#' + prefix + '_company').val() || '',
				country:     $('#' + prefix + '_country').val() || '',
				address_1:   $('#' + prefix + '_address_1').val() || '',
				address_2:   $('#' + prefix + '_address_2').val() || '',
				city:        $('#' + prefix + '_city').val() || '',
				state:       $('#' + prefix + '_state').val() || '',
				postal_code: $('#' + prefix + '_postcode').val() || '',
				postcode:    $('#' + prefix + '_postcode').val() || '',
				phone:       $('#' + prefix + '_phone').val() || '',
				email:       $('#' + prefix + '_email').val() || ''
			};

			// Update dropdown option data-address & label
			var $dropdown = $('#sab_' + type + '_address_select');
			if ($dropdown.length) {
				var $opt = $dropdown.find('option:selected');
				if ($opt.length && $opt.val() !== 'new') {
					$opt.data('address', updatedAddr);
					$opt.attr('data-address', JSON.stringify(updatedAddr));
					var curText = $opt.text();
					if (curText.indexOf('(Edited)') === -1) {
						$opt.text(curText + ' (Edited)');
					}
				}
			}

			// Update address summary text and display
			$('#sab_' + type + '_summary_text').html(formatAddressSummary(updatedAddr, type));
			$('#sab_' + type + '_summary_box').show();

			// Trigger checkout calculations update
			$(document.body).trigger('update_checkout');

			// Close editing form
			$fieldWrapper.removeClass('sab-form-expanded').addClass('sab-form-collapsed');
			$('.sab-checkout-save-' + type).removeClass('sab-form-expanded').addClass('sab-form-collapsed').slideUp(250);
			$actions.hide();
			$fieldWrapper.slideUp(250);
		});

		/**
		 * 6. Toggle Nickname input field when "Save this address" is toggled
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
		 * 4. Modal Event Handlers for "Add New Address" & "Edit Address" during checkout
		 */
		// Country change inside modal
		$(document).on('change', '#sab_modal_country', function() {
			updateModalStates($(this).val(), '');
		});

		// Click "Add New Address" button
		$(document).on('click', '.sab-add-new-address-btn', function(e) {
			e.preventDefault();
			var type = $(this).data('type') || 'shipping';
			var $modal = $('#sab-checkout-address-modal');
			if (!$modal.length) {
				return;
			}

			// Clear previous errors & field highlights
			$modal.find('.sab-modal-notice').hide().empty();
			$modal.find('.sab-field-error-message').remove();
			$modal.find('.sab-has-error').removeClass('sab-has-error woocommerce-invalid');
			$modal.find('.sab-input-error').removeClass('sab-input-error');

			var formEl = $modal.find('#sab-checkout-modal-form')[0];
			if (formEl) {
				formEl.reset();
			}

			// Configure modal for 'add' mode
			$('#sab_modal_mode').val('add');
			$('#sab_modal_editing_type').val(type);
			$('#sab_modal_editing_address_id').val('');

			var titleText = (type === 'billing') ? 'Add New Billing Address' : 'Add New Shipping Address';
			$('#sab-modal-title').text(titleText);

			$('#sab-modal-submit-btn').text('Save and Use Address');
			$('#sab-modal-cancel-btn').text('Cancel');

			// Pre-select type radio & show options
			$modal.find('input[name="address_type"][value="' + type + '"]').prop('checked', true);
			$('#sab_modal_type_row').show();
			$('#sab_modal_default_row').show();

			// Init states for default country
			var defaultCountry = $('#sab_modal_country').val() || 'US';
			updateModalStates(defaultCountry, '');

			$modal.fadeIn(200);
			$('body').addClass('sab-modal-open');
		});

		// Click "Edit" address button
		$(document).on('click', '.sab-edit-address-btn', function(e) {
			e.preventDefault();
			var type = $(this).data('type') || 'shipping';
			var prefix = (type === 'shipping') ? 'shipping' : 'billing';
			var $modal = $('#sab-checkout-address-modal');
			if (!$modal.length) {
				return;
			}

			// Clear previous errors & field highlights
			$modal.find('.sab-modal-notice').hide().empty();
			$modal.find('.sab-field-error-message').remove();
			$modal.find('.sab-has-error').removeClass('sab-has-error woocommerce-invalid');
			$modal.find('.sab-input-error').removeClass('sab-input-error');

			// Determine selected address data from Classic or Blocks dropdown
			var $classicDropdown = $('#sab_' + type + '_address_select');
			var $blocksDropdown = $('#sab_blocks_' + type + '_address_select');
			var $dropdown = $classicDropdown.length ? $classicDropdown : $blocksDropdown;

			var selectedVal = $dropdown.length ? $dropdown.val() : '';
			var addressData = null;

			if ($dropdown.length && selectedVal && selectedVal !== 'new') {
				var $opt = $dropdown.find('option:selected');
				addressData = $opt.data('address');
				if (typeof addressData === 'string') {
					try {
						addressData = JSON.parse(addressData);
					} catch (err) {}
				}
			}

			// Fallback to active checkout form values
			if (!addressData || typeof addressData !== 'object') {
				addressData = {
					first_name:  $('#' + prefix + '_first_name').val() || '',
					last_name:   $('#' + prefix + '_last_name').val() || '',
					company:     $('#' + prefix + '_company').val() || '',
					country:     $('#' + prefix + '_country').val() || '',
					address_1:   $('#' + prefix + '_address_1').val() || '',
					address_2:   $('#' + prefix + '_address_2').val() || '',
					city:        $('#' + prefix + '_city').val() || '',
					state:       $('#' + prefix + '_state').val() || '',
					postal_code: $('#' + prefix + '_postcode').val() || '',
					phone:       $('#' + prefix + '_phone').val() || '',
					email:       $('#' + prefix + '_email').val() || ''
				};
			}

			// Configure modal for 'edit' mode
			$('#sab_modal_mode').val('edit');
			$('#sab_modal_editing_type').val(type);
			$('#sab_modal_editing_address_id').val(selectedVal || '');

			var editTitle = (type === 'billing') ? 'Edit Billing Address' : 'Edit Shipping Address';
			$('#sab-modal-title').text(editTitle);

			// Both Billing and Shipping forms display Save Changes and Cancel buttons
			var saveText = (typeof sab_checkout_params !== 'undefined' && sab_checkout_params.i18n && sab_checkout_params.i18n.save_changes)
				? sab_checkout_params.i18n.save_changes
				: 'Save Changes';
			var cancelText = (typeof sab_checkout_params !== 'undefined' && sab_checkout_params.i18n && sab_checkout_params.i18n.cancel)
				? sab_checkout_params.i18n.cancel
				: 'Cancel';

			$('#sab-modal-submit-btn').text(saveText);
			$('#sab-modal-cancel-btn').text(cancelText);

			// Hide type switcher and default checkbox in edit mode
			$modal.find('input[name="address_type"][value="' + type + '"]').prop('checked', true);
			$('#sab_modal_type_row').hide();
			$('#sab_modal_default_row').hide();

			// Pre-populate address fields
			$('#sab_modal_nickname').val(addressData.nickname || '');
			$('#sab_modal_first_name').val(addressData.first_name || '');
			$('#sab_modal_last_name').val(addressData.last_name || '');
			$('#sab_modal_company').val(addressData.company || '');

			var country = addressData.country || $('#sab_modal_country').val() || 'US';
			$('#sab_modal_country').val(country);

			var stateVal = addressData.state || '';
			updateModalStates(country, stateVal);

			$('#sab_modal_address_1').val(addressData.address_1 || '');
			$('#sab_modal_address_2').val(addressData.address_2 || '');
			$('#sab_modal_city').val(addressData.city || '');
			$('#sab_modal_postcode').val(addressData.postal_code || (addressData.postcode || ''));
			$('#sab_modal_phone').val(addressData.phone || '');
			$('#sab_modal_email').val(addressData.email || '');

			$modal.fadeIn(200);
			$('body').addClass('sab-modal-open');
		});

		// Close modal (Cancel button, close icon, or backdrop)
		$(document).on('click', '.sab-modal-close, .sab-modal-cancel-btn', function(e) {
			e.preventDefault();
			$('#sab-checkout-address-modal').fadeOut(200);
			$('body').removeClass('sab-modal-open');
		});

		// Close modal when backdrop clicked
		$(document).on('click', '#sab-checkout-address-modal', function(e) {
			if ($(e.target).is('#sab-checkout-address-modal')) {
				$('#sab-checkout-address-modal').fadeOut(200);
				$('body').removeClass('sab-modal-open');
			}
		});

		// Submit Modal Form (Add New or Edit)
		$(document).on('submit', '#sab-checkout-modal-form', function(e) {
			e.preventDefault();
			var $form = $(this);
			var mode = $('#sab_modal_mode').val() || 'add';
			var $submitBtn = $('#sab-modal-submit-btn');
			var $notice = $('.sab-modal-notice');
			var $modalBody = $('#sab-checkout-address-modal .sab-modal-body');
			var type = (mode === 'edit')
				? ($('#sab_modal_editing_type').val() || 'shipping')
				: ($form.find('input[name="address_type"]:checked').val() || 'shipping');
			var prefix = (type === 'shipping') ? 'shipping' : 'billing';

			$notice.hide().empty();

			// 1. Perform client-side validation
			var clientErrors = validateAddressForm($form);
			if (Object.keys(clientErrors).length > 0) {
				applyFormErrors($form, clientErrors, $modalBody);
				return false;
			}

			// Clear previous field errors before processing
			$form.find('.sab-field-error-message').remove();
			$form.find('.sab-has-error').removeClass('sab-has-error woocommerce-invalid');
			$form.find('.sab-input-error').removeClass('sab-input-error');

			var addressObj = {
				first_name:  $.trim($form.find('[name="first_name"]').val()),
				last_name:   $.trim($form.find('[name="last_name"]').val()),
				company:     $.trim($form.find('[name="company"]').val()),
				country:     $.trim($form.find('[name="country"]').val()),
				address_1:   $.trim($form.find('[name="address_1"]').val()),
				address_2:   $.trim($form.find('[name="address_2"]').val()),
				city:        $.trim($form.find('[name="city"]').val()),
				state:       $.trim($form.find('[name="state"]').val()),
				postal_code: $.trim($form.find('[name="postal_code"]').val()),
				postcode:    $.trim($form.find('[name="postal_code"]').val()),
				phone:       $.trim($form.find('[name="phone"]').val()),
				email:       $.trim($form.find('[name="email"]').val()),
				nickname:    $.trim($form.find('[name="nickname"]').val())
			};

			if (mode === 'edit') {
				// Requirement 4: Save Changes should apply the edited details to the current checkout.
				// Requirement 6: Editing during checkout must not automatically overwrite the saved Address Book entry.

				// 1. Populate Classic Checkout fields & refresh calculations
				populateCheckoutFields(prefix, addressObj);

				// 2. Dispatch to Checkout Blocks Cart Store
				if (window.wp && window.wp.data && window.wp.data.dispatch) {
					try {
						var cartStore = window.wp.data.dispatch('wc/store/cart');
						if (cartStore) {
							if (type === 'shipping' && cartStore.setShippingAddress) {
								cartStore.setShippingAddress(addressObj);
							} else if (type === 'billing' && cartStore.setBillingAddress) {
								cartStore.setBillingAddress(addressObj);
							}
						}
					} catch (err) {
						console.error('[SAB] Error dispatching to cart store:', err);
					}
				}

				// 3. Update dropdown options data-address attribute and label
				var $classicDropdown = $('#sab_' + type + '_address_select');
				if ($classicDropdown.length) {
					var $opt = $classicDropdown.find('option:selected');
					if ($opt.length && $opt.val() !== 'new') {
						$opt.data('address', addressObj);
						$opt.attr('data-address', JSON.stringify(addressObj));
						var curText = $opt.text();
						if (curText.indexOf('(Edited)') === -1) {
							$opt.text(curText + ' (Edited)');
						}
					}
				}

				var $blocksDropdown = $('#sab_blocks_' + type + '_address_select');
				if ($blocksDropdown.length) {
					var $bOpt = $blocksDropdown.find('option:selected');
					if ($bOpt.length && $bOpt.val() !== 'new') {
						$bOpt.data('address', addressObj);
						$bOpt.attr('data-address', JSON.stringify(addressObj));
						var curBText = $bOpt.text();
						if (curBText.indexOf('(Edited)') === -1) {
							$bOpt.text(curBText + ' (Edited)');
						}
					}
				}

				// 4. Trigger custom event for other listeners
				$(document).trigger('sab_address_edited', {
					type: type,
					address: addressObj
				});

				// 5. Close modal without saving to DB
				$('#sab-checkout-address-modal').fadeOut(200);
				$('body').removeClass('sab-modal-open');
				return;
			}

			// Mode is 'add': proceed with AJAX save to DB
			$submitBtn.prop('disabled', true).text(
				(typeof sab_checkout_params !== 'undefined' && sab_checkout_params.i18n && sab_checkout_params.i18n.saving)
					? sab_checkout_params.i18n.saving
					: 'Saving...'
			);

			$.ajax({
				url: sab_checkout_params.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: $form.serialize(),
				success: function(res) {
					$submitBtn.prop('disabled', false).text('Save and Use Address');
					if (res.success && res.data) {
						var newId = res.data.address_id;
						var rawAddr = res.data.address;
						var label = res.data.label;

						// Close modal immediately
						$('#sab-checkout-address-modal').fadeOut(200);
						$('body').removeClass('sab-modal-open');

						// Update Classic Checkout Dropdown
						var $dropdown = $('#sab_' + type + '_address_select');
						if ($dropdown.length) {
							var $existingOpt = $dropdown.find('option:value="' + newId + '"');
							if (!$existingOpt.length) {
								var $newOpt = $('<option></option>')
									.val(newId)
									.text(label)
									.attr('data-address', JSON.stringify(rawAddr));
								var $firstOpt = $dropdown.find('option:first');
								if ($firstOpt.length) {
									$firstOpt.after($newOpt);
								} else {
									$dropdown.prepend($newOpt);
								}
							}
							$dropdown.val(newId);
						}

						// Update hidden address ID input
						$('#sab_' + type + '_address_id').val(newId);

						// Populate checkout fields
						populateCheckoutFields(prefix, rawAddr);
						$('.sab-checkout-save-' + type).slideUp();
						$('.sab-checkout-' + type + '-selector .sab-edit-address-btn').show();

						// Trigger custom event for Blocks and other listeners
						$(document).trigger('sab_address_created', {
							type: type,
							address_id: newId,
							address: rawAddr,
							label: label
						});
						if (window.wp && window.wp.data && window.wp.data.dispatch) {
							try {
								var cartStore = window.wp.data.dispatch('wc/store/cart');
								var checkoutStore = window.wp.data.dispatch('wc/store/checkout');
								if (cartStore) {
									if (type === 'shipping' && cartStore.setShippingAddress) {
										cartStore.setShippingAddress(rawAddr);
									} else if (type === 'billing' && cartStore.setBillingAddress) {
										cartStore.setBillingAddress(rawAddr);
									}
								}
								if (checkoutStore && checkoutStore.setExtensionData) {
									var extData = {};
									extData[type + '_address_id'] = newId;
									checkoutStore.setExtensionData('smart-address-book', extData);
								}
							} catch (err) {
								console.error(err);
							}
						}
					} else {
						// Server validation error
						if (res.data && res.data.errors) {
							applyFormErrors($form, res.data.errors, $modalBody);
						}

						var msg = (res.data && res.data.message)
							? res.data.message
							: ((sab_checkout_params.i18n && sab_checkout_params.i18n.error) ? sab_checkout_params.i18n.error : 'Error saving address.');
						$notice.html(msg).fadeIn();
					}
				},
				error: function() {
					$submitBtn.prop('disabled', false).text('Save and Use Address');
					$notice.html(
						(sab_checkout_params.i18n && sab_checkout_params.i18n.error)
							? sab_checkout_params.i18n.error
							: 'An error occurred. Please try again.'
					).fadeIn();
				}
			});
		});

	});

})(jQuery);
