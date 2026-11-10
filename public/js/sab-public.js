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
		 * 0. Edit Address Navigation Handler
		 */
		$(document).on('click', '.sab-action-edit', function(e) {
			var url = $(this).data('href') || $(this).attr('href');
			if (url) {
				window.location.href = url;
			}
		});

		/**
		 * 1. Delete Address Handler
		 */
		$(document).on('click', '.sab-action-delete', function(e) {
			e.preventDefault();

			var $btn = $(this);
			var addressId = $btn.data('id');
			var $card = $btn.closest('.sab-card');
			var $section = $card.closest('.sab-section');
			var type = $card.data('type') || ($section.hasClass('sab-billing-section') ? 'billing' : 'shipping');

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

							// Update section count badge
							var remainingCount = $section.find('.sab-card').length;
							$section.find('.sab-count-badge').text(remainingCount);

							// If no addresses remain, show empty state
							if (remainingCount === 0) {
								var emptyText = (type === 'billing')
									? 'No saved billing addresses yet.'
									: 'No saved shipping addresses yet.';
								var addText = (type === 'billing')
									? 'Add Billing Address'
									: 'Add Shipping Address';
								var currentUrl = window.location.href.split('?')[0];
								var targetUrl = currentUrl + '?action=add&type=' + type;

								var emptyHtml = '<div class="sab-empty-state"><p>' + emptyText + '</p><a href="' + targetUrl + '" class="button">' + addText + '</a></div>';
								$section.find('.sab-address-grid').html(emptyHtml);
							}
						});

						// Refresh default badge and button when a new default address is assigned
						if (response.data && response.data.new_default_id) {
							var newDefId = parseInt(response.data.new_default_id, 10);
							var addrType = response.data.address_type || type;
							var $newDefaultCard = $section.find('.sab-card[data-id="' + newDefId + '"]');

							if ($newDefaultCard.length) {
								// Remove previous default classes and badges in this section
								$section.find('.sab-card').removeClass('sab-is-default');
								$section.find('.sab-default-badge').remove();

								// Mark the new default card
								$newDefaultCard.addClass('sab-is-default');

								// Append Default Badge to top of the card
								var defaultText = (addrType === 'billing')
									? ((sab_params.i18n && sab_params.i18n.default_billing) ? sab_params.i18n.default_billing : 'Default Billing')
									: ((sab_params.i18n && sab_params.i18n.default_shipping) ? sab_params.i18n.default_shipping : 'Default Shipping');

								if (!$newDefaultCard.find('.sab-default-badge').length) {
									$newDefaultCard.find('.sab-card-top').append('<span class="sab-badge sab-default-badge">' + defaultText + '</span>');
								}

								// Remove "Set as Default" button from the newly marked default card
								$newDefaultCard.find('.sab-action-set-default').remove();
							}
						}

						// If limit warning notice was visible, hide it since an address slot freed up
						$section.find('.sab-limit-notice').fadeOut();

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
							var $cardItem = $(this);
							var cardAddressId = $cardItem.data('id');
							var $actions = $cardItem.find('.sab-card-actions');
							if (!$actions.find('.sab-action-set-default').length && cardAddressId != addressId) {
								$actions.append('<button type="button" class="button button-small sab-action-set-default" data-id="' + cardAddressId + '" data-type="' + type + '">Set as Default</button>');
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
		 * Helper: Escape HTML strings for safe rendering
		 */
		function escapeHtml(str) {
			if (!str) return '';
			return $('<div>').text(str).html();
		}

		/**
		 * 5. CSV Import with Selection and Confirmation Preview
		 */
		var sabParsedImportAddresses = [];

		function updateImportSelection() {
			var total = sabParsedImportAddresses.length;
			var selected = $('.sab-import-row-cb:checked').length;

			$('#sab-import-total-count').text(total);
			$('#sab-import-selected-count').text(selected);
			$('.sab-confirm-count').text(selected);

			var $actionBtn = $('#sab-import-action-btn');
			$actionBtn.text('Import Selected (' + selected + ')');

			if (selected > 0) {
				$actionBtn.prop('disabled', false);
			} else {
				$actionBtn.prop('disabled', true);
			}

			// Update master checkbox
			var $masterCb = $('#sab-import-master-cb');
			if (selected === total && total > 0) {
				$masterCb.prop('checked', true).prop('indeterminate', false);
			} else if (selected === 0) {
				$masterCb.prop('checked', false).prop('indeterminate', false);
			} else {
				$masterCb.prop('checked', false).prop('indeterminate', true);
			}
		}

		$(document).on('click', '.sab-import-btn', function(e) {
			e.preventDefault();
			$('#sab-csv-file-input').val('').click();
		});

		$('#sab-csv-file-input').on('change', function() {
			var file = this.files[0];
			if (!file) {
				return;
			}

			var formData = new FormData();
			formData.append('action', 'sab_preview_import_csv');
			formData.append('nonce', sab_params.nonce);
			formData.append('file', file);

			showNotice('Reading CSV file and validating addresses...', false);

			$.ajax({
				url: sab_params.ajax_url,
				type: 'POST',
				data: formData,
				contentType: false,
				processData: false,
				dataType: 'json',
				success: function(response) {
					if (response.success && response.data) {
						sabParsedImportAddresses = response.data.addresses || [];
						var total = sabParsedImportAddresses.length;

						if (total === 0) {
							showNotice('No valid addresses found in CSV file.', true);
							return;
						}

						// Build table rows
						var rowsHtml = '';
						$.each(sabParsedImportAddresses, function(index, importAddress) {
							var isBilling = (importAddress.address_type === 'billing');
							var typeBadge = isBilling
								? '<span class="sab-badge sab-badge-billing">' + ( (importAddress.address_type_label) ? importAddress.address_type_label : 'Billing' ) + '</span>'
								: '<span class="sab-badge sab-badge-shipping">' + ( (importAddress.address_type_label) ? importAddress.address_type_label : 'Shipping' ) + '</span>';
							var defaultBadge = (parseInt(importAddress.is_default, 10) === 1)
								? ' <span class="sab-badge sab-default-badge" style="font-size:10px; padding:2px 5px; margin-left:4px;">Default</span>'
								: '';
							var name = $.trim((importAddress.first_name || '') + ' ' + (importAddress.last_name || ''));
							var addrParts = [];
							if (importAddress.address_1) addrParts.push(importAddress.address_1);
							if (importAddress.address_2) addrParts.push(importAddress.address_2);
							var cityParts = [];
							if (importAddress.city) cityParts.push(importAddress.city);
							if (importAddress.state) cityParts.push(importAddress.state);
							if (importAddress.postal_code) cityParts.push(importAddress.postal_code);
							if (importAddress.country) cityParts.push(importAddress.country);

							var contactParts = [];
							if (importAddress.phone) contactParts.push('Phone: ' + importAddress.phone);
							if (importAddress.email) contactParts.push('Email: ' + importAddress.email);

							rowsHtml += '<tr data-index="' + index + '">';
							rowsHtml += '  <td class="sab-td-cb" style="text-align:center;"><input type="checkbox" class="sab-import-row-cb" data-index="' + index + '" checked /></td>';
							rowsHtml += '  <td>' + typeBadge + '</td>';
							rowsHtml += '  <td><strong>' + escapeHtml(importAddress.nickname || '') + '</strong>' + defaultBadge + '</td>';
							rowsHtml += '  <td>' + escapeHtml(name) + (importAddress.company ? '<br/><small style="color:#666;">' + escapeHtml(importAddress.company) + '</small>' : '') + '</td>';
							rowsHtml += '  <td>' + escapeHtml(addrParts.join(', ')) + (cityParts.length ? '<br/><small style="color:#666;">' + escapeHtml(cityParts.join(', ')) + '</small>' : '') + '</td>';
							rowsHtml += '  <td><small>' + contactParts.map(escapeHtml).join('<br/>') + '</small></td>';
							rowsHtml += '</tr>';
						});

						$('#sab-import-table-body').html(rowsHtml);

						// Handle skipped rows notice
						if (response.data.skipped_count > 0 && response.data.skipped && response.data.skipped.length) {
							$('#sab-import-skipped-notice').html('<strong>Notice:</strong> ' + response.data.skipped_count + ' invalid row(s) in CSV were skipped:<br/>' + response.data.skipped.join('<br/>')).show();
						} else {
							$('#sab-import-skipped-notice').hide().empty();
						}

						// Reset confirmation box
						$('#sab-import-confirm-box').hide();
						$('#sab-import-action-btn').show();

						updateImportSelection();

						// Open modal
						$('#sab-csv-import-modal').fadeIn(200);
						$('body').addClass('sab-modal-open');
					} else {
						showNotice(response.data.message || 'Failed to read CSV file.', true);
					}
				},
				error: function() {
					showNotice('An error occurred while reading the CSV file.', true);
				}
			});
		});

		// Master checkbox toggle
		$(document).on('change', '#sab-import-master-cb', function() {
			var isChecked = $(this).is(':checked');
			$('.sab-import-row-cb').prop('checked', isChecked);
			$('#sab-import-confirm-box').slideUp(150);
			updateImportSelection();
		});

		// Select All button
		$(document).on('click', '.sab-import-select-all', function(e) {
			e.preventDefault();
			$('.sab-import-row-cb').prop('checked', true);
			$('#sab-import-confirm-box').slideUp(150);
			updateImportSelection();
		});

		// Deselect All button
		$(document).on('click', '.sab-import-deselect-all', function(e) {
			e.preventDefault();
			$('.sab-import-row-cb').prop('checked', false);
			$('#sab-import-confirm-box').slideUp(150);
			updateImportSelection();
		});

		// Individual row checkbox change
		$(document).on('change', '.sab-import-row-cb', function() {
			$('#sab-import-confirm-box').slideUp(150);
			updateImportSelection();
		});

		// Close modal
		$(document).on('click', '.sab-import-modal-close, .sab-import-cancel-btn', function(e) {
			e.preventDefault();
			$('#sab-csv-import-modal').fadeOut(200);
			$('body').removeClass('sab-modal-open');
			$('#sab-csv-file-input').val('');
		});

		// Click "Import Selected (count)" button -> triggers confirmation step
		$(document).on('click', '#sab-import-action-btn', function(e) {
			e.preventDefault();
			var selectedCount = $('.sab-import-row-cb:checked').length;
			if (selectedCount === 0) {
				alert('Please select at least one address to import.');
				return;
			}

			// Show confirmation message
			$('#sab-confirm-question-text').text('Do you want to import ' + selectedCount + ' selected address(es) into your address book?');
			$('#sab-import-confirm-box').slideDown(200);

			// Scroll modal body to reveal confirmation box smoothly
			var $modalBody = $('#sab-csv-import-modal .sab-modal-body');
			$modalBody.animate({ scrollTop: $modalBody.prop('scrollHeight') }, 300);
		});

		// Cancel confirmation and go back to selection
		$(document).on('click', '.sab-confirm-back-btn', function(e) {
			e.preventDefault();
			$('#sab-import-confirm-box').slideUp(200);
		});

		// Customer confirms import ("Yes, Confirm & Import")
		$(document).on('click', '.sab-confirm-proceed-btn', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var selectedData = [];

			$('.sab-import-row-cb:checked').each(function() {
				var idx = parseInt($(this).data('index'), 10);
				if (sabParsedImportAddresses[idx]) {
					selectedData.push(sabParsedImportAddresses[idx]);
				}
			});

			if (selectedData.length === 0) {
				alert('No addresses selected.');
				return;
			}

			$btn.prop('disabled', true).text('Importing addresses...');
			$('#sab-import-action-btn').prop('disabled', true);

			$.ajax({
				url: sab_params.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'sab_confirm_import_addresses',
					nonce: sab_params.nonce,
					selected_addresses: JSON.stringify(selectedData)
				},
				success: function(response) {
					$btn.prop('disabled', false).text('Yes, Confirm & Import');
					$('#sab-import-action-btn').prop('disabled', false);

					if (response.success && response.data) {
						var importResult = response.data;
						var msg = 'Import finished: ' + importResult.imported + ' imported, ' + importResult.skipped + ' skipped.';
						if (importResult.errors && importResult.errors.length) {
							msg += '<br/>Errors:<br/>' + importResult.errors.join('<br/>');
						}

						// Close modal
						$('#sab-csv-import-modal').fadeOut(200);
						$('body').removeClass('sab-modal-open');
						$('#sab-csv-file-input').val('');

						// Display import summary
						showNotice(msg, false);

						// Reload page after brief delay to show updated addresses
						setTimeout(function() {
							location.reload();
						}, 1500);
					} else {
						alert(response.data.message || 'Import failed.');
					}
				},
				error: function() {
					$btn.prop('disabled', false).text('Yes, Confirm & Import');
					$('#sab-import-action-btn').prop('disabled', false);
					alert('An error occurred during import. Please try again.');
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
		 * Prevent adding new address if customer has reached maximum allowed addresses
		 */
		$(document).on('click', '.sab-add-btn', function(e) {
			if (typeof sab_params !== 'undefined' && sab_params.can_add_address === false && sab_params.max_addresses > 0) {
				e.preventDefault();
				alert(sab_params.limit_message || 'You have reached the maximum limit of saved addresses.');
				return false;
			}
		});

		/**
		 * 7. Address Form Validation & Field Error Handling
		 */
		function validateAddressForm($form) {
			var errors = {};
			var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			var validateRequired = (typeof sab_params !== 'undefined' && typeof sab_params.validate_required_fields !== 'undefined')
				? !!sab_params.validate_required_fields
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
			}

			// Email validation: required only when validateRequired is true, format always validated if entered
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

			// Check per-type maximum limit if adding new address
			if ($form.data('is-edit') !== '1' && $form.data('is-edit') !== 1) {
				var maxAllowed = parseInt($form.data('max-allowed'), 10) || 0;
				if (maxAllowed > 0) {
					var addrType = $form.find('[name="address_type"]').val() || 'shipping';
					var canAddType = (addrType === 'billing') ? ($form.data('can-add-billing') == 1) : ($form.data('can-add-shipping') == 1);
					if (!canAddType) {
						e.preventDefault();
						var limitMsg = (addrType === 'billing') ? $form.data('limit-billing') : $form.data('limit-shipping');
						alert(limitMsg || 'You have reached the maximum limit for this address type.');
						return false;
					}
				}
			}

			var errors = validateAddressForm($form);

			if (!$.isEmptyObject(errors)) {
				e.preventDefault();
				applyFormErrors($form, errors);
				return false;
			}
		});

		// When address type changes in Add New Address form, dynamically update type-specific limit warning
		$(document).on('change', '#sab_address_type', function() {
			var $form = $('#sab-address-form');
			if ($form.data('is-edit') === '1' || $form.data('is-edit') === 1) {
				return;
			}
			var type = $(this).val();
			var maxAllowed = parseInt($form.data('max-allowed'), 10) || 0;
			if (maxAllowed <= 0) return;

			var canAdd = (type === 'billing') ? ($form.data('can-add-billing') == 1) : ($form.data('can-add-shipping') == 1);
			var msg = (type === 'billing') ? $form.data('limit-billing') : $form.data('limit-shipping');

			var $existingNotice = $('.sab-address-form-wrapper .sab-limit-notice');
			if (!canAdd) {
				if (!$existingNotice.length) {
					$existingNotice = $('<div class="sab-limit-notice woocommerce-error" style="margin-bottom: 20px;"><p style="margin: 0;"><strong>Maximum Limit Reached:</strong> <span class="sab-limit-text"></span></p></div>');
					$('.sab-form-header').after($existingNotice);
				}
				$existingNotice.find('.sab-limit-text').text(msg);
				$existingNotice.show();
			} else {
				if ($existingNotice.length) {
					$existingNotice.hide();
				}
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

