/**
 * Smart Address Book for WooCommerce - Admin JS
 */

(function($) {
	'use strict';

	$(document).ready(function() {

		/* ========================================================
		 * 1. User Profile Address Book Handlers
		 * ======================================================== */

		var $modal = $('#sab-admin-modal');
		var $modalForm = $('#sab-admin-modal-form');

		// Open Modal for Add
		$('#sab-admin-add-address-btn').on('click', function(e) {
			e.preventDefault();
			$modalForm[0].reset();
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

			$.ajax({
				url: sab_admin_params.ajax_url,
				type: 'GET',
				dataType: 'json',
				data: {
					action: 'sab_get_address',
					address_id: addressId,
					nonce: sab_admin_params.nonce
				},
				success: function(res) {
					if (res.success && res.data.address) {
						var a = res.data.address;
						$('#sab_modal_address_id').val(a.id);
						$('#sab_modal_type').val(a.address_type);
						$('#sab_modal_nickname').val(a.nickname || '');
						$('#sab_modal_first_name').val(a.first_name || '');
						$('#sab_modal_last_name').val(a.last_name || '');
						$('#sab_modal_company').val(a.company || '');
						$('#sab_modal_phone').val(a.phone || '');
						$('#sab_modal_email').val(a.email || '');
						$('#sab_modal_country').val(a.country || '').trigger('change');
						$('#sab_modal_address_1').val(a.address_1 || '');
						$('#sab_modal_address_2').val(a.address_2 || '');
						$('#sab_modal_city').val(a.city || '');
						$('#sab_modal_state').val(a.state || '');
						$('#sab_modal_postal_code').val(a.postal_code || '');
						$('#sab_modal_is_default').prop('checked', parseInt(a.is_default, 10) === 1);

						$('#sab-modal-title').text('Edit Address (ID #' + a.id + ')');
						$modal.fadeIn(200);
					} else {
						alert(res.data.message || 'Could not load address.');
					}
				}
			});
		});

		// Modal Form Submit (Add or Update)
		$modalForm.on('submit', function(e) {
			e.preventDefault();

			var addressId = $('#sab_modal_address_id').val();
			var actionName = addressId ? 'sab_update_address' : 'sab_add_address';
			var formData = $modalForm.serializeArray();

			formData.push({ name: 'action', value: actionName });
			formData.push({ name: 'nonce', value: sab_admin_params.nonce });

			$('#sab-modal-save-btn').prop('disabled', true).text(sab_admin_params.i18n.saving);

			$.ajax({
				url: sab_admin_params.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: formData,
				success: function(res) {
					$('#sab-modal-save-btn').prop('disabled', false).text('Save Address');
					if (res.success) {
						$modal.fadeOut(200);
						location.reload();
					} else {
						alert(res.data.message || sab_admin_params.i18n.error);
					}
				},
				error: function() {
					$('#sab-modal-save-btn').prop('disabled', false).text('Save Address');
					alert(sab_admin_params.i18n.error);
				}
			});
		});

		// Delete Address
		$(document).on('click', '.sab-admin-del-btn', function(e) {
			e.preventDefault();
			if (!confirm(sab_admin_params.i18n.confirm_delete)) {
				return;
			}

			var addressId = $(this).data('id');
			var $row = $('#sab-admin-row-' + addressId);

			$.ajax({
				url: sab_admin_params.ajax_url,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'sab_delete_address',
					address_id: addressId,
					nonce: sab_admin_params.nonce
				},
				success: function(res) {
					if (res.success) {
						$row.fadeOut(300, function() {
							$(this).remove();
						});
					} else {
						alert(res.data.message || sab_admin_params.i18n.error);
					}
				}
			});
		});

		// Set Default Address
		$(document).on('click', '.sab-admin-set-default', function(e) {
			e.preventDefault();
			var addressId = $(this).data('id');
			var type = $(this).data('type');

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
				success: function(res) {
					if (res.success) {
						location.reload();
					} else {
						alert(res.data.message || sab_admin_params.i18n.error);
					}
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
			var raw = $opt.data('address');

			if (!raw) {
				return;
			}

			// Map into WooCommerce admin order fields (supporting both classic meta fields and HPOS)
			var fieldMap = {
				'first_name': raw.first_name || '',
				'last_name':  raw.last_name || '',
				'company':    raw.company || '',
				'address_1':  raw.address_1 || '',
				'address_2':  raw.address_2 || '',
				'city':       raw.city || '',
				'state':      raw.state || '',
				'postcode':   raw.postal_code || '',
				'country':    raw.country || '',
				'phone':      raw.phone || '',
				'email':      raw.email || ''
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

			// If edit address modal / section is open, update visible preview text
			var formatted = (raw.first_name + ' ' + raw.last_name).trim() + '<br/>' +
				(raw.company ? raw.company + '<br/>' : '') +
				raw.address_1 + '<br/>' +
				(raw.address_2 ? raw.address_2 + '<br/>' : '') +
				raw.city + ', ' + raw.state + ' ' + (raw.postal_code || '') + '<br/>' +
				raw.country;

			$('.order_data_column .' + prefix + '_address p').html(formatted);
		});

	});

})(jQuery);

