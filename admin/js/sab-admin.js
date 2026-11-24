/**
 * Smart Address Book for WooCommerce - Admin JS
 */

(function($) {
	'use strict';

	function escapeHtml(str) {
		if (!str || typeof str !== 'string') {
			return '';
		}
		return $('<div>').text(str).html();
	}

	$(document).ready(function() {

		/* ========================================================
		 * 1. User Profile Address Book Handlers
		 * ======================================================== */

		var $modal = $('#sab-admin-modal');

		// Ensure modal is detached from any parent form (such as #your-profile)
		// and appended to body to avoid invalid nested form or event collision issues.
		if ($modal.length) {
			$modal.appendTo('body');
		}

		// Helper: Build dynamic HTML table row for an address
		function buildAddressRowHtml(address, formattedHtml) {
			var id = address.id;
			var nickname = (address.nickname && $.trim(address.nickname) !== '') ? address.nickname : '—';
			var type = address.address_type || 'shipping';
			var typeCap = type.charAt(0).toUpperCase() + type.slice(1);
			var isDefault = parseInt(address.is_default, 10) === 1;

			var defaultLabel = (typeof sab_admin_params !== 'undefined' && sab_admin_params.i18n && sab_admin_params.i18n.default)
				? sab_admin_params.i18n.default : 'Default';
			var setDefaultLabel = (typeof sab_admin_params !== 'undefined' && sab_admin_params.i18n && sab_admin_params.i18n.set_default)
				? sab_admin_params.i18n.set_default : 'Set Default';
			var editLabel = (typeof sab_admin_params !== 'undefined' && sab_admin_params.i18n && sab_admin_params.i18n.edit)
				? sab_admin_params.i18n.edit : 'Edit';
			var deleteLabel = (typeof sab_admin_params !== 'undefined' && sab_admin_params.i18n && sab_admin_params.i18n.delete)
				? sab_admin_params.i18n.delete : 'Delete';

			var defaultColHtml = isDefault
				? '<span class="dashicons dashicons-star-filled sab-star-icon"></span> <strong>' + defaultLabel + '</strong>'
				: '<button type="button" class="button button-small sab-admin-set-default" data-id="' + id + '" data-type="' + type + '">' + setDefaultLabel + '</button>';

			return '<tr id="sab-admin-row-' + id + '" data-id="' + id + '" data-type="' + type + '">' +
				'<td><strong>' + escapeHtml(nickname) + '</strong></td>' +
				'<td><span class="sab-type-tag sab-type-' + escapeHtml(type) + '">' + escapeHtml(typeCap) + '</span></td>' +
				'<td class="sab-admin-address-details">' + (formattedHtml || '') + '</td>' +
				'<td class="sab-admin-default-cell">' + defaultColHtml + '</td>' +
				'<td>' +
					'<button type="button" class="button button-small sab-admin-edit-btn" data-id="' + id + '">' + editLabel + '</button> ' +
					'<button type="button" class="button button-small sab-admin-del-btn" data-id="' + id + '">' + deleteLabel + '</button>' +
				'</td>' +
			'</tr>';
		}

		// Helper: Dynamically update state field to dropdown or text input
		function updateAdminModalState(countryCode, selectedState) {
			var states = (typeof sab_admin_params !== 'undefined' && sab_admin_params.states) ? sab_admin_params.states : {};
			var $wrapper = $('#sab_modal_state_wrapper');
			if (!$wrapper.length) {
				return;
			}

			var countryCodeUpper = countryCode ? countryCode.toUpperCase() : '';
			var countryStates = countryCodeUpper && states[countryCodeUpper] ? states[countryCodeUpper] : null;
			var selectStateText = (typeof sab_admin_params !== 'undefined' && sab_admin_params.i18n && sab_admin_params.i18n.select_state)
				? sab_admin_params.i18n.select_state
				: 'Select state / province...';

			if (countryStates && typeof countryStates === 'object' && Object.keys(countryStates).length > 0) {
				var $select = $('<select name="state" id="sab_modal_state" class="widefat"></select>');
				$select.append($('<option value=""></option>').text(selectStateText));
				var selUpper = selectedState ? String(selectedState).toUpperCase() : '';
				$.each(countryStates, function(code, name) {
					var $opt = $('<option></option>').val(code).text(name);
					if (selUpper && (selUpper === String(code).toUpperCase() || selUpper === String(name).toUpperCase())) {
						$opt.prop('selected', true);
					}
					$select.append($opt);
				});
				$wrapper.find('#sab_modal_state').replaceWith($select);
			} else {
				var $input = $('<input type="text" name="state" id="sab_modal_state" class="widefat" />');
				if (selectedState) {
					$input.val(selectedState);
				}
				$wrapper.find('#sab_modal_state').replaceWith($input);
			}
		}

		// Helper: Reset modal fields
		function resetAdminModal() {
			$('#sab_modal_address_id').val('');
			$('#sab_modal_type').val('shipping');
			$('#sab_modal_nickname').val('');
			$('#sab_modal_first_name').val('');
			$('#sab_modal_last_name').val('');
			$('#sab_modal_company').val('');
			$('#sab_modal_phone').val('');
			$('#sab_modal_email').val('');
			$('#sab_modal_country').val('');
			updateAdminModalState('', '');
			$('#sab_modal_address_1').val('');
			$('#sab_modal_address_2').val('');
			$('#sab_modal_city').val('');
			$('#sab_modal_postal_code').val('');
			$('#sab_modal_is_default').prop('checked', false);
			var saveText = (typeof sab_admin_params !== 'undefined' && sab_admin_params.i18n && sab_admin_params.i18n.save_address)
				? sab_admin_params.i18n.save_address : 'Save Address';
			$('#sab-modal-save-btn').prop('disabled', false).text(saveText);
		}

		// Country change listener
		$(document).on('change', '#sab_modal_country', function() {
			var country = $(this).val();
			var currentState = $('#sab_modal_state').val();
			updateAdminModalState(country, currentState);
		});

		// Open Modal for Add
		$(document).on('click', '#sab-admin-add-address-btn', function(e) {
			e.preventDefault();
			resetAdminModal();
			var targetUserId = $(this).attr('data-user-id') || $(this).data('user-id') || $('#sab_modal_user_id').val();
			$('#sab_modal_user_id').val(targetUserId);
			$('#sab_modal_address_id').val('');
			$('#sab-modal-title').text('Add New Address');
			$modal.fadeIn(200);
		});

		// Close Modal
		$(document).on('click', '.sab-modal-close-btn, .sab-modal-backdrop', function(e) {
			e.preventDefault();
			$modal.fadeOut(200);
		});

		// Open Modal for Edit
		$(document).on('click', '.sab-admin-edit-btn', function(e) {
			e.preventDefault();
			var addressId = $(this).data('id');
			var $btn = $(this);
			$btn.prop('disabled', true);

			$.ajax({
				url: sab_admin_params.ajax_url,
				type: 'GET',
				dataType: 'json',
				data: {
					action: 'sab_get_address',
					address_id: addressId,
					nonce: sab_admin_params.nonce
				},
				success: function(response) {
					$btn.prop('disabled', false);
					if (response.success && response.data.address) {
						var address = response.data.address;
						$('#sab_modal_address_id').val(address.id);
						$('#sab_modal_user_id').val(address.user_id);
						$('#sab_modal_type').val(address.address_type);
						$('#sab_modal_nickname').val(address.nickname || '');
						$('#sab_modal_first_name').val(address.first_name || '');
						$('#sab_modal_last_name').val(address.last_name || '');
						$('#sab_modal_company').val(address.company || '');
						$('#sab_modal_phone').val(address.phone || '');
						$('#sab_modal_email').val(address.email || '');

						var countryCode = address.country ? address.country.toUpperCase() : '';
						$('#sab_modal_country').val(countryCode);
						updateAdminModalState(countryCode, address.state || '');

						$('#sab_modal_address_1').val(address.address_1 || '');
						$('#sab_modal_address_2').val(address.address_2 || '');
						$('#sab_modal_city').val(address.city || '');
						$('#sab_modal_postal_code').val(address.postal_code || '');
						$('#sab_modal_is_default').prop('checked', parseInt(address.is_default, 10) === 1);

						$('#sab-modal-title').text('Edit Address (ID #' + address.id + ')');
						var saveText = (typeof sab_admin_params !== 'undefined' && sab_admin_params.i18n && sab_admin_params.i18n.save_address)
							? sab_admin_params.i18n.save_address : 'Save Address';
						$('#sab-modal-save-btn').prop('disabled', false).text(saveText);
						$modal.fadeIn(200);
					} else {
						alert(response.data && response.data.message ? response.data.message : 'Could not load address.');
					}
				},
				error: function() {
					$btn.prop('disabled', false);
					alert(sab_admin_params.i18n.error);
				}
			});
		});

		// Modal Save Button Click (Add or Update)
		$(document).on('click', '#sab-modal-save-btn', function(e) {
			e.preventDefault();

			var addressId = $('#sab_modal_address_id').val();
			var actionName = addressId ? 'sab_update_address' : 'sab_add_address';

			// Client-side required field check
			var requiredFields = [
				{ id: '#sab_modal_first_name', name: 'First Name' },
				{ id: '#sab_modal_last_name', name: 'Last Name' },
				{ id: '#sab_modal_country', name: 'Country' },
				{ id: '#sab_modal_address_1', name: 'Street Address' },
				{ id: '#sab_modal_city', name: 'City' }
			];

			for (var i = 0; i < requiredFields.length; i++) {
				var $f = $(requiredFields[i].id);
				if (!$f.val() || !$.trim($f.val())) {
					alert(requiredFields[i].name + ' is required.');
					$f.focus();
					return;
				}
			}

			// Validate email format if provided
			var emailVal = $.trim($('#sab_modal_email').val());
			if (emailVal && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
				alert('Please enter a valid email address.');
				$('#sab_modal_email').focus();
				return;
			}

			// Validate state if country has state dropdown and dropdown has options
			var $stateField = $('#sab_modal_state');
			if ($stateField.is('select') && $stateField.find('option').length > 1 && !$stateField.val()) {
				alert('State / Province is required.');
				$stateField.focus();
				return;
			}

			var $inputs = $modal.find('input, select, textarea').not('#sab_modal_is_default');
			var formData = $inputs.serializeArray();

			// Target user ID validation and attachment
			var targetUserId = $('#sab_modal_user_id').val() || $('#sab-admin-add-address-btn').attr('data-user-id');
			var hasUserId = false;
			for (var k = 0; k < formData.length; k++) {
				if (formData[k].name === 'user_id') {
					if (!formData[k].value && targetUserId) {
						formData[k].value = targetUserId;
					}
					hasUserId = true;
					break;
				}
			}
			if (!hasUserId && targetUserId) {
				formData.push({ name: 'user_id', value: targetUserId });
			}

			// Explicitly handle is_default checkbox (1 or 0)
			var isDefChecked = $('#sab_modal_is_default').is(':checked') ? 1 : 0;
			var hasDefault = false;
			for (var m = 0; m < formData.length; m++) {
				if (formData[m].name === 'is_default') {
					formData[m].value = isDefChecked;
					hasDefault = true;
					break;
				}
			}
			if (!hasDefault) {
				formData.push({ name: 'is_default', value: isDefChecked });
			}

			formData.push({ name: 'action', value: actionName });
			formData.push({ name: 'nonce', value: sab_admin_params.nonce });

			var $saveBtn = $('#sab-modal-save-btn');
			$saveBtn.prop('disabled', true).text(sab_admin_params.i18n.saving);

			$.ajax({
				url: sab_admin_params.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: formData,
				success: function(response) {
					var saveText = (typeof sab_admin_params !== 'undefined' && sab_admin_params.i18n && sab_admin_params.i18n.save_address)
						? sab_admin_params.i18n.save_address : 'Save Address';
					$saveBtn.prop('disabled', false).text(saveText);

					if (response.success && response.data) {
						$modal.fadeOut(200);

						var addr = response.data.address;
						var formatted = response.data.formatted;
						var isDefault = parseInt(addr.is_default, 10) === 1;
						var type = addr.address_type || 'shipping';
						var setDefaultLabel = (typeof sab_admin_params !== 'undefined' && sab_admin_params.i18n && sab_admin_params.i18n.set_default)
							? sab_admin_params.i18n.set_default : 'Set Default';

						// If this address is default, clear default status from other rows of the same type
						if (isDefault) {
							$('#sab-admin-address-tbody tr[data-type="' + type + '"]').each(function() {
								var otherId = $(this).data('id');
								if (String(otherId) !== String(addr.id)) {
									var $defCell = $(this).find('.sab-admin-default-cell').length
										? $(this).find('.sab-admin-default-cell')
										: $(this).find('td:nth-child(4)');
									$defCell.html('<button type="button" class="button button-small sab-admin-set-default" data-id="' + otherId + '" data-type="' + type + '">' + setDefaultLabel + '</button>');
								}
							});
						}

						if (actionName === 'sab_update_address') {
							// Update existing row in place
							var $existingRow = $('#sab-admin-row-' + addr.id);
							if ($existingRow.length) {
								var updatedRowHtml = buildAddressRowHtml(addr, formatted);
								$existingRow.replaceWith(updatedRowHtml);
								var $newRow = $('#sab-admin-row-' + addr.id);
								$newRow.addClass('sab-row-highlight');
								setTimeout(function() { $newRow.removeClass('sab-row-highlight'); }, 2000);
							}
						} else {
							// Add new row: remove empty placeholder if present
							$('#sab-admin-address-tbody tr.sab-no-addresses').remove();
							var newRowHtml = buildAddressRowHtml(addr, formatted);
							$('#sab-admin-address-tbody').append(newRowHtml);
							var $newRow = $('#sab-admin-row-' + addr.id);
							$newRow.addClass('sab-row-highlight');
							setTimeout(function() { $newRow.removeClass('sab-row-highlight'); }, 2000);
						}

						resetAdminModal();
					} else {
						alert(response.data && response.data.message ? response.data.message : sab_admin_params.i18n.error);
					}
				},
				error: function(xhr) {
					var saveText = (typeof sab_admin_params !== 'undefined' && sab_admin_params.i18n && sab_admin_params.i18n.save_address)
						? sab_admin_params.i18n.save_address : 'Save Address';
					$saveBtn.prop('disabled', false).text(saveText);
					var msg = sab_admin_params.i18n.error;
					if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						msg = xhr.responseJSON.data.message;
					}
					alert(msg);
				}
			});
		});

		// Submit modal on pressing Enter inside text inputs
		$modal.on('keydown', 'input:not([type="checkbox"])', function(e) {
			if (e.which === 13) {
				e.preventDefault();
				$('#sab-modal-save-btn').trigger('click');
			}
		});

		// Delete Address
		$(document).on('click', '.sab-admin-del-btn', function(e) {
			e.preventDefault();
			if (!confirm(sab_admin_params.i18n.confirm_delete)) {
				return;
			}

			var addressId = $(this).data('id');
			var $row = $('#sab-admin-row-' + addressId);
			var $btn = $(this);
			$btn.prop('disabled', true);

			$.ajax({
				url: sab_admin_params.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'sab_delete_address',
					address_id: addressId,
					nonce: sab_admin_params.nonce
				},
				success: function(response) {
					if (response.success) {
						$row.fadeOut(300, function() {
							$(this).remove();
							if ($('#sab-admin-address-tbody tr:not(.sab-no-addresses)').length === 0) {
								var noAddrText = (typeof sab_admin_params !== 'undefined' && sab_admin_params.i18n && sab_admin_params.i18n.no_addresses)
									? sab_admin_params.i18n.no_addresses
									: 'No addresses saved for this customer yet.';
								$('#sab-admin-address-tbody').html('<tr class="sab-no-addresses"><td colspan="5">' + noAddrText + '</td></tr>');
							}
						});

						if (response.data && response.data.new_default_id) {
							var $newRow = $('#sab-admin-row-' + response.data.new_default_id);
							if ($newRow.length) {
								var defaultLabel = (typeof sab_admin_params !== 'undefined' && sab_admin_params.i18n && sab_admin_params.i18n.default)
									? sab_admin_params.i18n.default
									: 'Default';
								var $defCell = $newRow.find('.sab-admin-default-cell').length
									? $newRow.find('.sab-admin-default-cell')
									: $newRow.find('td:nth-child(4)');
								$defCell.html(
									'<span class="dashicons dashicons-star-filled sab-star-icon"></span> <strong>' + defaultLabel + '</strong>'
								);
							}
						}
					} else {
						$btn.prop('disabled', false);
						alert(response.data.message || sab_admin_params.i18n.error);
					}
				},
				error: function() {
					$btn.prop('disabled', false);
					alert(sab_admin_params.i18n.error);
				}
			});
		});

		// Set Default Address
		$(document).on('click', '.sab-admin-set-default', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var addressId = $btn.data('id');
			var type = $btn.data('type');
			$btn.prop('disabled', true).text(sab_admin_params.i18n.saving || 'Saving...');

			$.ajax({
				url: sab_admin_params.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'sab_set_default',
					address_id: addressId,
					type: type,
					nonce: sab_admin_params.nonce
				},
				success: function(response) {
					if (response.success) {
						var defaultLabel = (typeof sab_admin_params !== 'undefined' && sab_admin_params.i18n && sab_admin_params.i18n.default)
							? sab_admin_params.i18n.default : 'Default';
						var setDefaultLabel = (typeof sab_admin_params !== 'undefined' && sab_admin_params.i18n && sab_admin_params.i18n.set_default)
							? sab_admin_params.i18n.set_default : 'Set Default';

						// Update all rows of this type in the table without page reload
						$('#sab-admin-address-tbody tr[data-type="' + type + '"]').each(function() {
							var rowId = $(this).data('id');
							var $defaultCell = $(this).find('.sab-admin-default-cell').length
								? $(this).find('.sab-admin-default-cell')
								: $(this).find('td:nth-child(4)');
							if (String(rowId) === String(addressId)) {
								$defaultCell.html('<span class="dashicons dashicons-star-filled sab-star-icon"></span> <strong>' + defaultLabel + '</strong>');
							} else {
								$defaultCell.html('<button type="button" class="button button-small sab-admin-set-default" data-id="' + rowId + '" data-type="' + type + '">' + setDefaultLabel + '</button>');
							}
						});
					} else {
						var defBtnText = (typeof sab_admin_params !== 'undefined' && sab_admin_params.i18n && sab_admin_params.i18n.set_default)
							? sab_admin_params.i18n.set_default : 'Set Default';
						$btn.prop('disabled', false).text(defBtnText);
						alert(response.data.message || sab_admin_params.i18n.error);
					}
				},
				error: function() {
					var defBtnText = (typeof sab_admin_params !== 'undefined' && sab_admin_params.i18n && sab_admin_params.i18n.set_default)
						? sab_admin_params.i18n.set_default : 'Set Default';
					$btn.prop('disabled', false).text(defBtnText);
					alert(sab_admin_params.i18n.error);
				}
			});
		});

		/* ========================================================
		 * 2. Admin Order Screen Address Picker Handler
		 * ======================================================== */

		$(document).on('change', '.sab-order-address-select', function() {
			var $select = $(this);
			var type = $select.data('type') || 'billing';
			var prefix = (type === 'shipping') ? 'shipping' : 'billing';
			var $opt = $select.find('option:selected');
			var selectedAddressData = $opt.data('address');

			if (!selectedAddressData) {
				return;
			}

			// Map into WooCommerce admin order fields (supporting both classic meta fields and HPOS)
			var fieldMap = {
				'first_name': selectedAddressData.first_name || '',
				'last_name':  selectedAddressData.last_name || '',
				'company':    selectedAddressData.company || '',
				'address_1':  selectedAddressData.address_1 || '',
				'address_2':  selectedAddressData.address_2 || '',
				'city':       selectedAddressData.city || '',
				'state':      selectedAddressData.state || '',
				'postcode':   selectedAddressData.postal_code || '',
				'country':    selectedAddressData.country || '',
				'phone':      selectedAddressData.phone || '',
				'email':      selectedAddressData.email || ''
			};

			$.each(fieldMap, function(key, val) {
				// Target classic field names: _billing_first_name, etc.
				var $classic = $('[name="_' + prefix + '_' + key + '"]');
				if ($classic.length) {
					$classic.val(val).trigger('change');
				}

				// Target HPOS / general WooCommerce field IDs and names
				var $hpos = $('[name="' + prefix + '_' + key + '"], #' + prefix + '_' + key);
				if ($hpos.length) {
					$hpos.val(val).trigger('change');
				}
			});

			// If edit address modal / section is open, update visible preview text with HTML escaping (Point 14)
			var fullName = (escapeHtml(selectedAddressData.first_name) + ' ' + escapeHtml(selectedAddressData.last_name)).trim();
			var lines = [];
			if (fullName) {
				lines.push(fullName);
			}
			if (selectedAddressData.company) {
				lines.push(escapeHtml(selectedAddressData.company));
			}
			if (selectedAddressData.address_1) {
				lines.push(escapeHtml(selectedAddressData.address_1));
			}
			if (selectedAddressData.address_2) {
				lines.push(escapeHtml(selectedAddressData.address_2));
			}
			var cityLine = escapeHtml(selectedAddressData.city) + ', ' + escapeHtml(selectedAddressData.state) + ' ' + escapeHtml(selectedAddressData.postal_code || '');
			if (cityLine.trim() !== ',') {
				lines.push(cityLine);
			}
			if (selectedAddressData.country) {
				lines.push(escapeHtml(selectedAddressData.country));
			}

			$('.order_data_column .' + prefix + '_address p').html(lines.join('<br/>'));
		});

	});

})(jQuery);

