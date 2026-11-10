/**
 * Smart Address Book for WooCommerce - Classic Checkout Integration JS
 */

(function($) {
	'use strict';

	$(document).ready(function() {
		if (typeof window.sab_checkout_params === 'undefined') {
			if (typeof sab_blocks_checkout_params !== 'undefined') {
				window.sab_checkout_params = sab_blocks_checkout_params;
			} else if (window.wc && window.wc.wcSettings && typeof window.wc.wcSettings.getSetting === 'function') {
				window.sab_checkout_params = window.wc.wcSettings.getSetting('smart-address-book_data');
			}
		}

		/**
		 * Helper: Populate WooCommerce checkout input fields.
		 */
		function populateCheckoutFields(prefix, address) {
			if (!address) {
				return;
			}

			var fields = {
				'first_name': address.first_name || '',
				'last_name':  address.last_name || '',
				'company':    address.company || '',
				'country':    address.country || '',
				'address_1':  address.address_1 || '',
				'address_2':  address.address_2 || '',
				'city':       address.city || '',
				'state':      address.state || '',
				'postcode':   address.postal_code || (address.postcode || ''),
				'phone':      address.phone || '',
				'email':      address.email || ''
			};

			$.each(fields, function(key, val) {
				if (key === 'email') {
					if (prefix === 'billing') {
						var $billingEmail = $('#billing_email');
						if ($billingEmail.length && val && $.trim(val)) {
							$billingEmail.val(val);
						}
					}
					return;
				}
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
		 * Helper: Dynamically update country options in modal based on address type.
		 */
		function updateModalCountries(addressType, selectedCountry) {
			var $countrySelect = $('#sab_modal_country');
			if (!$countrySelect.length) {
				return;
			}

			var countryList = {};
			if (typeof sab_checkout_params !== 'undefined') {
				if (addressType === 'shipping' && sab_checkout_params.shipping_countries) {
					countryList = sab_checkout_params.shipping_countries;
				} else if (addressType === 'billing' && sab_checkout_params.billing_countries) {
					countryList = sab_checkout_params.billing_countries;
				} else if (sab_checkout_params.countries) {
					countryList = sab_checkout_params.countries;
				}
			}

			var currentCountry = selectedCountry || $countrySelect.val();
			if (!currentCountry) {
				if (addressType === 'shipping' && typeof sab_checkout_params !== 'undefined' && sab_checkout_params.default_shipping_country) {
					currentCountry = sab_checkout_params.default_shipping_country;
				} else if (addressType === 'billing' && typeof sab_checkout_params !== 'undefined' && sab_checkout_params.default_billing_country) {
					currentCountry = sab_checkout_params.default_billing_country;
				}
			}

			var countryKeys = Object.keys(countryList);
			if (countryKeys.length > 0 && (!currentCountry || !countryList[currentCountry])) {
				currentCountry = countryKeys[0];
			}

			// Clean up Select2 / SelectWoo if initialized on country select
			if ($.fn.select2 && $countrySelect.hasClass('select2-hidden-accessible')) {
				try {
					$countrySelect.select2('destroy');
				} catch (e) {}
			}

			$countrySelect.empty();
			$countrySelect.append('<option value="">Select a country / region&hellip;</option>');
			$.each(countryList, function(code, name) {
				var $opt = $('<option></option>').val(code).text(name);
				if (code === currentCountry) {
					$opt.prop('selected', true);
				}
				$countrySelect.append($opt);
			});

			if (currentCountry) {
				$countrySelect.val(currentCountry);
			}

			updateModalStates(currentCountry, '');
		}

		/**
		 * Helper: Dynamically update state dropdown or input in modal.
		 */
		function updateModalStates(countryCode, selectedState) {
			var states = (typeof sab_checkout_params !== 'undefined' && sab_checkout_params.states) ? sab_checkout_params.states : {};
			var $stateContainer = $('#sab_modal_state_field');
			if (!$stateContainer.length) {
				return;
			}

			// Safely clean up Select2 / SelectWoo if present
			if ($.fn.select2 && $stateContainer.find('.select2-container').length) {
				try {
					$stateContainer.find('select').select2('destroy');
				} catch (e) {}
				$stateContainer.find('.select2-container').remove();
			}

			var $currentInput = $('#sab_modal_state');
			var stateVal = selectedState || '';

			if (states[countryCode] && Object.keys(states[countryCode]).length > 0) {
				var countryStates = states[countryCode];
				var $select = $('<select name="state" id="sab_modal_state" class="state_select" style="width:100%; box-sizing:border-box;"></select>');
				var placeholder = (typeof sab_checkout_params !== 'undefined' && sab_checkout_params.i18n && sab_checkout_params.i18n.select_state_text)
					? sab_checkout_params.i18n.select_state_text
					: 'Select an option&hellip;';
				$select.append('<option value="">' + placeholder + '</option>');

				var matchedCode = '';
				$.each(countryStates, function(code, name) {
					var isMatch = (code === stateVal || name === stateVal || (stateVal && code.toLowerCase() === stateVal.toLowerCase()) || (stateVal && name.toLowerCase() === stateVal.toLowerCase()));
					var $opt = $('<option></option>').val(code).text(name);
					if (isMatch) {
						$opt.prop('selected', true);
						matchedCode = code;
					}
					$select.append($opt);
				});

				if (matchedCode) {
					$select.val(matchedCode);
				}

				if ($currentInput.length) {
					$currentInput.replaceWith($select);
				} else {
					$stateContainer.append($select);
				}
			} else {
				var $text = $('<input type="text" class="input-text" name="state" id="sab_modal_state" style="width:100%; box-sizing:border-box;" />');
				if (stateVal) {
					$text.val(stateVal);
				}
				if ($currentInput.length) {
					$currentInput.replaceWith($text);
				} else {
					$stateContainer.append($text);
				}
			}

			$(document.body).trigger('country_to_state_changed', [countryCode, $('#sab-checkout-modal-form')]);
		}

		/**
		 * Helper: Validate Address Form fields client-side.
		 */
		function validateAddressForm($form) {
			var errors = {};
			var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			var validateRequired = (typeof sab_checkout_params !== 'undefined' && typeof sab_checkout_params.validate_required_fields !== 'undefined')
				? !!sab_checkout_params.validate_required_fields
				: true;

			if (validateRequired) {
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
			}

			// Email validation: required only when validateRequired is true, format checked if entered
			var rawEmail = $.trim($form.find('[name="email"]').val());
			if (!rawEmail) {
				if (validateRequired) {
					errors.email = 'Email Address is a required field.';
				}
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
		function formatAddressSummary(address, addressType) {
			if (!address) {
				return '';
			}
			var lines = [];
			var name = $.trim((address.first_name || '') + ' ' + (address.last_name || ''));
			if (address.nickname) {
				name += ' (' + address.nickname + ')';
			}
			if (name) {
				lines.push('<strong>' + name + '</strong>');
			}
			if (address.company) {
				lines.push(address.company);
			}
			var addrLine = address.address_1 || '';
			if (address.address_2) {
				addrLine += ', ' + address.address_2;
			}
			if (addrLine) {
				lines.push(addrLine);
			}
			var cityLine = address.city || '';
			if (address.state) {
				cityLine += ', ' + address.state;
			}
			if (address.postal_code || address.postcode) {
				cityLine += ' ' + (address.postal_code || address.postcode);
			}
			if (cityLine) {
				lines.push(cityLine);
			}
			if (address.country) {
				lines.push(address.country);
			}
			var contact = [];
			if (address.phone) {
				contact.push('Phone: ' + address.phone);
			}
			if (address.email) {
				contact.push('Email: ' + address.email);
			}
			if (contact.length) {
				lines.push(contact.join(' | '));
			}
			return lines.join('<br>');
		}

		/**
		 * Helper: Update default badge on address summary card.
		 */
		function updateSummaryBadge(type, isDefault) {
			var $headerRow = $('#sab_' + type + '_summary_box .sab-summary-header-row');
			$headerRow.find('.sab-default-badge').remove();
			if (isDefault) {
				var badgeText = (typeof sab_checkout_params !== 'undefined' && sab_checkout_params.i18n && sab_checkout_params.i18n.default_badge)
					? sab_checkout_params.i18n.default_badge
					: 'Default';
				$headerRow.append('<span class="sab-badge sab-default-badge">' + badgeText + '</span>');
			}
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
			var $saveWrapper = $('.sab-checkout-save-' + type);
			var $summaryBox = $('#sab_' + type + '_summary_box');

			if (val === 'new') {
				var prevId = $select.data('current-val') || $('#sab_' + type + '_address_id').val();
				if (prevId && prevId !== 'new') {
					$select.data('prev-val', prevId);
				}
				$('#sab_' + type + '_address_id').val('');
				$summaryBox.hide();
				clearCheckoutFields(prefix);

				// Ensure action buttons are placed at bottom of form after fields and save checkbox
				if ($actions.length) {
					if ($saveWrapper.length) {
						$saveWrapper.after($actions);
					} else if ($fieldWrapper.length) {
						$fieldWrapper.after($actions);
					}
				}

				$fieldWrapper.addClass('sab-form-expanded').removeClass('sab-form-collapsed').slideDown(250);
				$saveWrapper.addClass('sab-form-expanded').removeClass('sab-form-collapsed').slideDown(250);
				$actions.addClass('sab-form-expanded').removeClass('sab-form-collapsed').slideDown(250);

				var $firstInput = $('#' + prefix + '_first_name');
				if ($firstInput.length) {
					$firstInput.trigger('focus');
				}
				return;
			}

			$select.data('current-val', val);
			$select.data('prev-val', val);
			$('#sab_' + type + '_address_id').val(val);

			var $selectedOption = $select.find('option:selected');
			var rawAddress = $selectedOption.data('address');

			if (rawAddress) {
				populateCheckoutFields(prefix, rawAddress);
				$('#sab_' + type + '_summary_text').html(formatAddressSummary(rawAddress, type));
				updateSummaryBadge(type, parseInt(rawAddress.is_default, 10) === 1);
				$summaryBox.show();
				$actions.removeClass('sab-form-expanded').addClass('sab-form-collapsed').slideUp(250);
				$fieldWrapper.removeClass('sab-form-expanded').addClass('sab-form-collapsed').slideUp(250);
				$saveWrapper.removeClass('sab-form-expanded').addClass('sab-form-collapsed').slideUp();
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
					success: function(response) {
						if (response.success && response.data.fields) {
							$.each(response.data.fields, function(fieldKey, fieldVal) {
								var $field = $('#' + fieldKey);
								if ($field.length) {
									$field.val(fieldVal).trigger('change');
								}
							});
							$(document.body).trigger('update_checkout');
							$summaryBox.show();
							$actions.removeClass('sab-form-expanded').addClass('sab-form-collapsed').slideUp(250);
							$fieldWrapper.removeClass('sab-form-expanded').addClass('sab-form-collapsed').slideUp(250);
							$saveWrapper.removeClass('sab-form-expanded').addClass('sab-form-collapsed').slideUp();
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

				// Ensure inline action buttons are placed at the bottom of the edit form, after all address fields
				var $saveWrapper = $('.sab-checkout-save-' + type);
				if ($actions.length) {
					if ($saveWrapper.length) {
						$saveWrapper.after($actions);
					} else if ($fieldWrapper.length) {
						$fieldWrapper.after($actions);
					}
				}

				if ($dropdown.length) {
					var val = $dropdown.val();
					if (val && val !== 'new') {
						$dropdown.data('current-val', val);
						$dropdown.data('prev-val', val);
						$('#sab_' + type + '_address_id').val(val);
						var $opt = $dropdown.find('option:selected');
						var raw = $opt.data('address');
						if (raw) {
							populateCheckoutFields(prefix, raw);
							$('#sab_' + type + '_summary_text').html(formatAddressSummary(raw, type));
							updateSummaryBadge(type, parseInt(raw.is_default, 10) === 1);
						}
						// Initially display selected address as address summary, hide form fields
						$summaryBox.show();
						$fieldWrapper.removeClass('sab-form-expanded').addClass('sab-form-collapsed').hide();
						$('.sab-checkout-save-' + type).removeClass('sab-form-expanded').addClass('sab-form-collapsed').hide();
						$actions.removeClass('sab-form-expanded').addClass('sab-form-collapsed').hide();
					} else if (val === 'new') {
						$summaryBox.hide();
						$fieldWrapper.addClass('sab-form-expanded').removeClass('sab-form-collapsed').show();
						$('.sab-checkout-save-' + type).addClass('sab-form-expanded').removeClass('sab-form-collapsed').show();
						$actions.addClass('sab-form-expanded').removeClass('sab-form-collapsed').show();
					}
				}
			});
		}

		/**
		 * Ensure "Billing address" and "Shipping address" headings appear above
		 * their respective saved address selector sections in Classic Checkout.
		 */
		function ensureCheckoutHeadingsOrder() {
			// 1. Classic Billing Heading: ensure heading is above selector
			var $billingSelector = $('.sab-checkout-billing-selector');
			if ($billingSelector.length) {
				var $billingParent = $billingSelector.closest('.woocommerce-billing-fields');
				if (!$billingParent.length) {
					$billingParent = $billingSelector.parent();
				}
				var $billingHeading = $billingParent.find('> h3, > h2, h3:first, h2:first').first();
				if ($billingHeading.length) {
					var bHeadNode = $billingHeading.get(0);
					var bSelNode = $billingSelector.get(0);
					if (bHeadNode && bSelNode && (bHeadNode.compareDocumentPosition(bSelNode) & 2)) {
						$billingSelector.before($billingHeading);
					}
				}
			}

			// 2. Classic Shipping Heading: ensure heading is above selector
			var $shippingSelector = $('.sab-checkout-shipping-selector');
			if ($shippingSelector.length) {
				var $shippingParent = $shippingSelector.closest('.woocommerce-shipping-fields, .shipping_address');
				if (!$shippingParent.length) {
					$shippingParent = $shippingSelector.parent();
				}
				var $shippingHeading = $shippingParent.find('#ship-to-different-address, > h3, > h2, h3:first, h2:first').first();
				if ($shippingHeading.length) {
					var sHeadNode = $shippingHeading.get(0);
					var sSelNode = $shippingSelector.get(0);
					if (sHeadNode && sSelNode && (sHeadNode.compareDocumentPosition(sSelNode) & 2)) {
						$shippingSelector.before($shippingHeading);
					}
				}
			}
		}

		/**
		 * Ensure Contact Information section is located at the top of Classic Checkout
		 * and prevent any duplicate billing email field in the DOM.
		 */
		function ensureContactInformationOrder() {
			var $contactWrapper = $('#sab_contact_information_wrapper');
			var $customerDetails = $('#customer_details');
			var $billingFields = $('.woocommerce-billing-fields');

			// If contact information was rendered via PHP, ensure it sits at the top before customer details
			if ($contactWrapper.length) {
				if ($customerDetails.length) {
					var cNode = $contactWrapper.get(0);
					var cdNode = $customerDetails.get(0);
					if (cNode && cdNode && (cNode.compareDocumentPosition(cdNode) & 2)) {
						$customerDetails.before($contactWrapper);
					}
				} else if ($billingFields.length) {
					var cNode2 = $contactWrapper.get(0);
					var bNode = $billingFields.first().get(0);
					if (cNode2 && bNode && (cNode2.compareDocumentPosition(bNode) & 2)) {
						$billingFields.first().before($contactWrapper);
					}
				}
			} else {
				// Client-side fallback if hook was not fired by a custom theme template
				var $emailField = $('#billing_email_field');
				if ($emailField.length) {
					var titleText = (typeof sab_checkout_params !== 'undefined' && sab_checkout_params.i18n && sab_checkout_params.i18n.contact_information)
						? sab_checkout_params.i18n.contact_information
						: 'Contact Information';

					$contactWrapper = $(
						'<div class="sab-contact-information-wrapper" id="sab_contact_information_wrapper">' +
						'  <div class="sab-contact-information-header">' +
						'    <h3 class="sab-contact-info-title">' + titleText + '</h3>' +
						'  </div>' +
						'  <div class="sab-contact-email-container"></div>' +
						'</div>'
					);

					if ($customerDetails.length) {
						$customerDetails.before($contactWrapper);
					} else if ($billingFields.length) {
						$billingFields.first().before($contactWrapper);
					} else {
						$('form.checkout').prepend($contactWrapper);
					}

					$contactWrapper.find('.sab-contact-email-container').append($emailField);
					$emailField.show();
				}
			}

			// Clean up any stray duplicate billing_email_field inside billing fields wrapper
			if ($('#sab_contact_information_wrapper #billing_email_field').length) {
				$('.woocommerce-billing-fields__field-wrapper #billing_email_field').remove();
			}
		}

		initCheckoutAddressSummaries();
		ensureContactInformationOrder();
		ensureCheckoutHeadingsOrder();

		$(document.body).on('updated_checkout', function() {
			$('#sab_shipping_summary_box').slice(1).remove();
			$('#sab_billing_summary_box').slice(1).remove();
			ensureContactInformationOrder();
			ensureCheckoutHeadingsOrder();
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
			var $saveWrapper = $('.sab-checkout-save-' + type);
			var $actions = $('#sab_' + type + '_inline_actions');

			// Ensure inline action buttons are placed at the bottom of the edit form, after all address fields
			if ($actions.length) {
				if ($saveWrapper.length) {
					$saveWrapper.after($actions);
				} else if ($fieldWrapper.length) {
					$fieldWrapper.after($actions);
				}
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
			$saveWrapper.addClass('sab-form-expanded').removeClass('sab-form-collapsed');
			$actions.addClass('sab-form-expanded').removeClass('sab-form-collapsed');
			$fieldWrapper.slideDown(250);
			$saveWrapper.slideDown(250);
			$actions.slideDown(250);

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
			var prefix = (type === 'shipping') ? 'shipping' : 'billing';
			var $dropdown = $('#sab_' + type + '_address_select');
			var $fieldWrapper = (type === 'billing')
				? $('.woocommerce-billing-fields__field-wrapper')
				: $('.woocommerce-shipping-fields__field-wrapper');
			var $actions = $('#sab_' + type + '_inline_actions');
			var $summaryBox = $('#sab_' + type + '_summary_box');

			// Clear validation error highlights
			$fieldWrapper.find('.sab-field-error-message').remove();
			$fieldWrapper.find('.sab-has-error').removeClass('sab-has-error woocommerce-invalid');
			$fieldWrapper.find('.sab-input-error').removeClass('sab-input-error');

			if ($dropdown.val() === 'new') {
				// Revert new address form without saving
				var prevVal = $dropdown.data('prev-val') || $dropdown.data('current-val');
				if (!prevVal || prevVal === 'new') {
					prevVal = $dropdown.find('option:not([value="new"]):first').val();
				}
				if (prevVal && prevVal !== 'new') {
					$dropdown.val(prevVal).trigger('change');
				} else {
					$fieldWrapper.removeClass('sab-form-expanded').addClass('sab-form-collapsed').slideUp(250);
					$('.sab-checkout-save-' + type).removeClass('sab-form-expanded').addClass('sab-form-collapsed').slideUp(250);
					$actions.removeClass('sab-form-expanded').addClass('sab-form-collapsed').slideUp(250);
					if (type === 'shipping') {
						$('.shipping_address').show();
					}
					$summaryBox.show();
				}
				return;
			}

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

			// Close editing form
			$fieldWrapper.removeClass('sab-form-expanded').addClass('sab-form-collapsed');
			$('.sab-checkout-save-' + type).removeClass('sab-form-expanded').addClass('sab-form-collapsed').slideUp(250);
			$actions.removeClass('sab-form-expanded').addClass('sab-form-collapsed').slideUp(250);
			$fieldWrapper.slideUp(250);
			if (type === 'shipping') {
				$('.shipping_address').show();
			}
			$summaryBox.show();
		});

		/**
		 * 5. Save Changes button validates and updates the selected address in current checkout
		 */
		$(document).on('click', '.sab-inline-save-btn', function(e) {
			e.preventDefault();
			var $saveBtn = $(this);
			var type = $saveBtn.data('type') || 'billing';
			var prefix = (type === 'shipping') ? 'shipping' : 'billing';
			var $dropdown = $('#sab_' + type + '_address_select');
			var $fieldWrapper = (type === 'billing')
				? $('.woocommerce-billing-fields__field-wrapper')
				: $('.woocommerce-shipping-fields__field-wrapper');
			var $actions = $('#sab_' + type + '_inline_actions');
			var isNew = ($dropdown.val() === 'new');

			// Validate fields
			var errors = {};
			var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			var validateRequired = (typeof sab_checkout_params !== 'undefined' && typeof sab_checkout_params.validate_required_fields !== 'undefined')
				? !!sab_checkout_params.validate_required_fields
				: true;

			if (validateRequired) {
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

				var state = $.trim($('#' + prefix + '_state').val());
				var $stateSelect = $('#' + prefix + '_state');
				if ($stateSelect.is('select') && !state) {
					errors[prefix + '_state'] = 'State / County is required for this country.';
				}
			}

			// Email address validation: required when validateRequired is true, format checked if entered
			var $emailInput = (type === 'billing' || $('#' + prefix + '_email').length)
				? $('#' + prefix + '_email')
				: $('#billing_email');
			var emailVal = $emailInput.length ? $.trim($emailInput.val()) : '';
			var emailFieldId = $emailInput.length ? $emailInput.attr('id') : prefix + '_email';

			if (!emailVal) {
				if (validateRequired) {
					errors[emailFieldId] = 'Email Address is a required field.';
				}
			} else if (!emailRegex.test(emailVal)) {
				errors[emailFieldId] = 'Please enter a valid email address.';
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

			// If customer is entering a new address, save it via AJAX only if saving at checkout is enabled and limit not reached
			if (isNew) {
				var enableCheckoutSave = (typeof sab_checkout_params !== 'undefined' && typeof sab_checkout_params.enable_checkout_save !== 'undefined')
					? !!sab_checkout_params.enable_checkout_save
					: true;
				var canAddAddress = true;
				if (typeof sab_checkout_params !== 'undefined') {
					if (type === 'billing' && typeof sab_checkout_params.can_add_billing !== 'undefined') {
						canAddAddress = !!sab_checkout_params.can_add_billing;
					} else if (type === 'shipping' && typeof sab_checkout_params.can_add_shipping !== 'undefined') {
						canAddAddress = !!sab_checkout_params.can_add_shipping;
					} else if (typeof sab_checkout_params.can_add_address !== 'undefined') {
						canAddAddress = !!sab_checkout_params.can_add_address;
					}
				}

				// If saving to address book is disabled or limit reached, apply directly to current checkout without saving to DB
				if (!enableCheckoutSave || !canAddAddress) {
					var tempAddr = {
						first_name:   firstName || $.trim($('#' + prefix + '_first_name').val()),
						last_name:    lastName || $.trim($('#' + prefix + '_last_name').val()),
						company:      $('#' + prefix + '_company').val() || '',
						country:      country || $.trim($('#' + prefix + '_country').val()),
						address_1:    address1 || $.trim($('#' + prefix + '_address_1').val()),
						address_2:    $('#' + prefix + '_address_2').val() || '',
						city:         city || $.trim($('#' + prefix + '_city').val()),
						state:        state || $.trim($('#' + prefix + '_state').val()),
						postal_code:  postcode || $.trim($('#' + prefix + '_postcode').val()),
						postcode:     postcode || $.trim($('#' + prefix + '_postcode').val()),
						phone:        $('#' + prefix + '_phone').val() || '',
						email:        emailVal,
						nickname:     $('#sab_nickname_' + type).val() || (type === 'billing' ? 'Billing Address' : 'Shipping Address')
					};

					populateCheckoutFields(prefix, tempAddr);
					$('#sab_' + type + '_summary_text').html(formatAddressSummary(tempAddr, type));
					updateSummaryBadge(type, false);
					if (type === 'shipping') {
						$('.shipping_address').show();
					}
					$('#sab_' + type + '_summary_box').show();
					$fieldWrapper.removeClass('sab-form-expanded').slideUp(250);
					$actions.removeClass('sab-form-expanded').slideUp(250);
					$(document.body).trigger('update_checkout');
					return;
				}

				var origText = $saveBtn.text();
				var savingText = (typeof sab_checkout_params !== 'undefined' && sab_checkout_params.i18n && sab_checkout_params.i18n.saving)
					? sab_checkout_params.i18n.saving
					: 'Saving...';
				$saveBtn.prop('disabled', true).text(savingText);

				var postData = {
					action:       'sab_save_checkout_address',
					nonce:        sab_checkout_params.nonce,
					address_type: type,
					first_name:   firstName,
					last_name:    lastName,
					company:      $('#' + prefix + '_company').val() || '',
					country:      country,
					address_1:    address1,
					address_2:    $('#' + prefix + '_address_2').val() || '',
					city:         city,
					state:        state,
					postal_code:  postcode,
					postcode:     postcode,
					phone:        $('#' + prefix + '_phone').val() || '',
					email:        emailVal,
					nickname:     $('#sab_nickname_' + type).val() || ''
				};

				$.ajax({
					url: sab_checkout_params.ajax_url,
					type: 'POST',
					dataType: 'json',
					data: postData,
					success: function(response) {
						$saveBtn.prop('disabled', false).text(origText);
						if (response.success && response.data) {
							var newId        = response.data.address_id;
							var savedAddress = response.data.address;
							var label        = response.data.label;

							// Update Dropdown with newly saved address option
							if ($dropdown.length) {
								var $existingOpt = $dropdown.find('option[value="' + newId + '"]');
								if (!$existingOpt.length) {
									var $newOpt = $('<option></option>')
										.val(newId)
										.text(label)
										.attr('data-address', JSON.stringify(savedAddress))
										.data('address', savedAddress);
									var $newOptionPlaceholder = $dropdown.find('option[value="new"]');
									if ($newOptionPlaceholder.length) {
										$newOptionPlaceholder.before($newOpt);
									} else {
										$dropdown.append($newOpt);
									}
								}
								$dropdown.val(newId);
								$dropdown.data('current-val', newId);
								$dropdown.data('prev-val', newId);
							}

							$('#sab_' + type + '_address_id').val(newId);

							// Update address summary text, badge, and display
							$('#sab_' + type + '_summary_text').html(formatAddressSummary(savedAddress, type));
							updateSummaryBadge(type, parseInt(response.data.is_default, 10) === 1);
							if (type === 'shipping') {
								$('.shipping_address').show();
							}
							$('#sab_' + type + '_summary_box').show();

							// Trigger checkout calculations update
							$(document.body).trigger('update_checkout');

							// Close form
							$fieldWrapper.removeClass('sab-form-expanded').addClass('sab-form-collapsed').slideUp(250);
							$('.sab-checkout-save-' + type).removeClass('sab-form-expanded').addClass('sab-form-collapsed').slideUp(250);
							$actions.removeClass('sab-form-expanded').addClass('sab-form-collapsed').slideUp(250);
						} else {
							if (response.data && response.data.errors) {
								var srvErrors = {};
								$.each(response.data.errors, function(fieldKey, errorMessage) {
									var inputId = prefix + '_' + fieldKey;
									if ($('#' + inputId).length) {
										srvErrors[inputId] = errorMessage;
									} else if ($('#' + fKey).length) {
										srvErrors[fKey] = fMsg;
									} else if (fKey === 'email') {
										srvErrors[emailFieldId] = fMsg;
									}
								});
								applyFormErrors($fieldWrapper, srvErrors);
							}
						}
					},
					error: function() {
						$saveBtn.prop('disabled', false).text(origText);
						alert('An error occurred while saving your address. Please try again.');
					}
				});
				return;
			}

			// Valid: editing existing address
			var updatedAddr = {
				first_name:  firstName,
				last_name:   lastName,
				company:     $('#' + prefix + '_company').val() || '',
				country:     country,
				address_1:   address1,
				address_2:   $('#' + prefix + '_address_2').val() || '',
				city:        city,
				state:       state,
				postal_code: postcode,
				postcode:    postcode,
				phone:       $('#' + prefix + '_phone').val() || '',
				email:       emailVal
			};

			// Update dropdown option data-address & label
			if ($dropdown.length) {
				var $opt = $dropdown.find('option:selected');
				if ($opt.length && $opt.val() !== 'new') {
					var origData = $opt.data('address');
					if (origData && origData.is_default !== undefined) {
						updatedAddr.is_default = origData.is_default;
					}
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
			if (type === 'shipping') {
				$('.shipping_address').show();
			}
			$('#sab_' + type + '_summary_box').show();

			// Trigger checkout calculations update
			$(document.body).trigger('update_checkout');

			// Close editing form
			$fieldWrapper.removeClass('sab-form-expanded').addClass('sab-form-collapsed');
			$('.sab-checkout-save-' + type).removeClass('sab-form-expanded').addClass('sab-form-collapsed').slideUp(250);
			$actions.removeClass('sab-form-expanded').addClass('sab-form-collapsed').slideUp(250);
			$fieldWrapper.slideUp(250);
		});

		/**
		 * 6. Harmonize "Ship to a different address?" checkbox with SAB Shipping Address Summary
		 */
		$(document).on('change', '#ship-to-different-address-checkbox', function() {
			var isChecked = $(this).is(':checked');
			if (isChecked) {
				var $shippingAddress = $('.shipping_address');
				$shippingAddress.show();
				var $dropdown = $('#sab_shipping_address_select');
				var $summaryBox = $('#sab_shipping_summary_box');
				var $fieldWrapper = $('.woocommerce-shipping-fields__field-wrapper');
				var $actions = $('#sab_shipping_inline_actions');
				var $saveWrapper = $('.sab-checkout-save-shipping');

				if ($dropdown.length) {
					var val = $dropdown.val();
					if (val && val !== 'new') {
						var $opt = $dropdown.find('option:selected');
						var raw = $opt.data('address');
						if (raw) {
							populateCheckoutFields('shipping', raw);
							$('#sab_shipping_summary_text').html(formatAddressSummary(raw, 'shipping'));
							updateSummaryBadge('shipping', parseInt(raw.is_default, 10) === 1);
						}
						if (!$fieldWrapper.hasClass('sab-form-expanded')) {
							$summaryBox.show();
							$fieldWrapper.removeClass('sab-form-expanded').addClass('sab-form-collapsed').hide();
							$actions.removeClass('sab-form-expanded').addClass('sab-form-collapsed').hide();
							$saveWrapper.removeClass('sab-form-expanded').addClass('sab-form-collapsed').hide();
						} else {
							$summaryBox.show();
							$fieldWrapper.show();
							$actions.show();
						}
					} else if (val === 'new') {
						$summaryBox.hide();
						$fieldWrapper.addClass('sab-form-expanded').removeClass('sab-form-collapsed').show();
						$actions.addClass('sab-form-expanded').removeClass('sab-form-collapsed').show();
						$saveWrapper.addClass('sab-form-expanded').removeClass('sab-form-collapsed').show();
					}
				}
			}
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

			if (typeof sab_checkout_params !== 'undefined') {
				if (sab_checkout_params.enable_checkout_save === false) {
					alert('Saving addresses at checkout is disabled.');
					return false;
				}
				var canAddForType = (type === 'billing') ? sab_checkout_params.can_add_billing : sab_checkout_params.can_add_shipping;
				if (typeof canAddForType === 'undefined') {
					canAddForType = sab_checkout_params.can_add_address;
				}
				var limitMsgForType = (type === 'billing') ? sab_checkout_params.limit_message_billing : sab_checkout_params.limit_message_shipping;
				if (!limitMsgForType) {
					limitMsgForType = sab_checkout_params.limit_message;
				}
				if (canAddForType === false && sab_checkout_params.max_addresses > 0) {
					alert(limitMsgForType || 'You have reached the maximum limit of saved addresses.');
					return false;
				}
			}
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
			$modal.find('input[name="address_type"]').prop('checked', false);
			$modal.find('input[name="address_type"][value="' + type + '"]').prop('checked', true);
			if (typeof sab_checkout_params !== 'undefined' && sab_checkout_params.max_addresses > 0) {
				$modal.find('input[name="address_type"][value="billing"]').prop('disabled', sab_checkout_params.can_add_billing === false);
				$modal.find('input[name="address_type"][value="shipping"]').prop('disabled', sab_checkout_params.can_add_shipping === false);
			}
			$('#sab_modal_type_row').show();
			$('#sab_modal_default_row').show();

			// Update countries & states based on address type (Billing vs Shipping)
			updateModalCountries(type, '');

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

			var country = addressData.country || '';
			updateModalCountries(type, country);

			var stateVal = addressData.state || '';
			updateModalStates(country || $('#sab_modal_country').val(), stateVal);

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

		$(document).on('change', '#sab-checkout-modal-form input[name="address_type"]', function() {
			var selectedType = $(this).val();
			updateModalCountries(selectedType, '');
			var $notice = $('.sab-modal-notice');
			if (typeof sab_checkout_params !== 'undefined' && sab_checkout_params.max_addresses > 0) {
				var canAddType = (selectedType === 'billing') ? sab_checkout_params.can_add_billing : sab_checkout_params.can_add_shipping;
				if (canAddType === false) {
					var limitMsg = (selectedType === 'billing') ? sab_checkout_params.limit_message_billing : sab_checkout_params.limit_message_shipping;
					$notice.removeClass('sab-notice-success').addClass('sab-notice-error').html(limitMsg || sab_checkout_params.limit_message).show();
					$('#sab-modal-submit-btn').prop('disabled', true);
				} else {
					$notice.hide().empty();
					$('#sab-modal-submit-btn').prop('disabled', false);
				}
			}
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

			if (mode === 'add' && typeof sab_checkout_params !== 'undefined') {
				var canAddType = (type === 'billing') ? sab_checkout_params.can_add_billing : sab_checkout_params.can_add_shipping;
				if (typeof canAddType === 'undefined') {
					canAddType = sab_checkout_params.can_add_address;
				}
				if (canAddType === false && sab_checkout_params.max_addresses > 0) {
					var limitMsg = (type === 'billing') ? sab_checkout_params.limit_message_billing : sab_checkout_params.limit_message_shipping;
					$notice.removeClass('sab-notice-success').addClass('sab-notice-error').html(limitMsg || sab_checkout_params.limit_message).show();
					return false;
				}
			}

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
				url: (typeof sab_checkout_params !== 'undefined' && sab_checkout_params.ajax_url) ? sab_checkout_params.ajax_url : '/wp-admin/admin-ajax.php',
				type: 'POST',
				dataType: 'json',
				data: $form.serialize(),
				success: function(response) {
					$submitBtn.prop('disabled', false).text('Save and Use Address');
					if (response.success && response.data) {
						var newId        = response.data.address_id;
						var savedAddress = response.data.address;
						var label        = response.data.label;

						// Ensure postcode property exists on address object
						if (savedAddress && !savedAddress.postcode && savedAddress.postal_code) {
							savedAddress.postcode = savedAddress.postal_code;
						}

						// Close modal immediately
						$('#sab-checkout-address-modal').fadeOut(200);
						$('body').removeClass('sab-modal-open');

						// 1. Update Classic Checkout Dropdown
						var $selectorContainer = $('.sab-checkout-' + type + '-selector');
						var $dropdown = $('#sab_' + type + '_address_select');
						if ($dropdown.length) {
							var $existingOpt = $dropdown.find('option[value="' + newId + '"]');
							if (!$existingOpt.length) {
								var $newOpt = $('<option></option>')
									.val(newId)
									.text(label)
									.attr('data-address', JSON.stringify(savedAddress))
									.data('address', savedAddress);

								var $firstOpt = $dropdown.find('option:first');
								if ($firstOpt.length) {
									$firstOpt.before($newOpt);
								} else {
									$dropdown.append($newOpt);
								}
							} else {
								$existingOpt
									.text(label)
									.attr('data-address', JSON.stringify(savedAddress))
									.data('address', savedAddress);
							}
							$dropdown.val(newId);
							$dropdown.data('current-val', newId);
							$dropdown.data('prev-val', newId);

							// Ensure dropdown row and label are visible
							$dropdown.closest('p.form-row').show();
							$selectorContainer.find('.sab-selector-label').show();
						}

						// 2. Update hidden address ID input
						$('#sab_' + type + '_address_id').val(newId);

						// 3. Populate checkout fields
						populateCheckoutFields(prefix, savedAddress);

						// 4. Update and display address summary box
						$('#sab_' + type + '_summary_text').html(formatAddressSummary(savedAddress, type));
						updateSummaryBadge(type, parseInt(response.data.is_default, 10) === 1);
						if (type === 'shipping') {
							$('.shipping_address').show();
						}
						$('#sab_' + type + '_summary_box').show();

						// 5. Hide native checkout fields and save options
						var $fieldWrapper = (type === 'billing')
							? $('.woocommerce-billing-fields__field-wrapper')
							: $('.woocommerce-shipping-fields__field-wrapper');
						var $actions = $('#sab_' + type + '_inline_actions');
						var $saveWrapper = $('.sab-checkout-save-' + type);

						$fieldWrapper.removeClass('sab-form-expanded').addClass('sab-form-collapsed').hide();
						$saveWrapper.removeClass('sab-form-expanded').addClass('sab-form-collapsed').hide();
						$actions.removeClass('sab-form-expanded').addClass('sab-form-collapsed').hide();

						// 6. Recalculate shipping & taxes
						$(document.body).trigger('update_checkout');

						// 7. Trigger custom event for Blocks and other listeners
						$(document).trigger('sab_address_created', {
							type: type,
							address_id: newId,
							address: savedAddress,
							label: label
						});

						// 8. Update WooCommerce Blocks Cart Store if in Block context
						if (window.wp && window.wp.data && window.wp.data.dispatch) {
							try {
								var cartStore = window.wp.data.dispatch('wc/store/cart');
								var checkoutStore = window.wp.data.dispatch('wc/store/checkout');
								if (cartStore) {
									var formattedBlockAddr = {
										first_name: savedAddress.first_name || '',
										last_name:  savedAddress.last_name || '',
										company:    savedAddress.company || '',
										address_1:  savedAddress.address_1 || '',
										address_2:  savedAddress.address_2 || '',
										city:       savedAddress.city || '',
										state:      savedAddress.state || '',
										postcode:   savedAddress.postal_code || (savedAddress.postcode || ''),
										country:    savedAddress.country || '',
										phone:      savedAddress.phone || ''
									};
									if (type === 'shipping' && cartStore.setShippingAddress) {
										cartStore.setShippingAddress(formattedBlockAddr);
									} else if (type === 'billing' && cartStore.setBillingAddress) {
										formattedBlockAddr.email = savedAddress.email || '';
										cartStore.setBillingAddress(formattedBlockAddr);
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
						if (response.data && response.data.errors) {
							applyFormErrors($form, response.data.errors, $modalBody);
						}

						var msg = (response.data && response.data.message)
							? response.data.message
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
