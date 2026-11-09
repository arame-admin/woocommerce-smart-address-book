/**
 * Smart Address Book for WooCommerce - Checkout Blocks Integration JS
 */

(function($) {
	'use strict';

	// Retrieve data from either localized object or wcSettings
	var sabData = null;

	function getSettings() {
		if (sabData) {
			return sabData;
		}

		if (typeof sab_blocks_checkout_params !== 'undefined') {
			sabData = sab_blocks_checkout_params;
		} else if (typeof sab_checkout_params !== 'undefined') {
			sabData = sab_checkout_params;
		} else if (window.wc && window.wc.wcSettings && typeof window.wc.wcSettings.getSetting === 'function') {
			var setting = window.wc.wcSettings.getSetting('smart-address-book_data');
			if (setting) {
				sabData = setting;
			}
		}

		if (sabData && typeof window.sab_checkout_params === 'undefined') {
			window.sab_checkout_params = sabData;
		}

		return sabData;
	}

	$(document).ready(function() {
		var settings = getSettings();
		if (!settings || !settings.is_logged_in) {
			return;
		}

		var shippingAddresses = settings.shipping_addresses || [];
		var billingAddresses  = settings.billing_addresses || [];
		var defaultShippingId = parseInt(settings.default_shipping_id, 10) || 0;
		var defaultBillingId  = parseInt(settings.default_billing_id, 10) || 0;

		/**
		 * Helper: Set extension data for Store API checkout request.
		 */
		function updateBlockExtensionData(key, id) {
			if (window.wp && window.wp.data && window.wp.data.dispatch) {
				try {
					var checkoutStore = window.wp.data.dispatch('wc/store/checkout');
					if (checkoutStore && checkoutStore.setExtensionData) {
						var ext = {};
						ext[key] = parseInt(id, 10) || 0;
						checkoutStore.setExtensionData('smart-address-book', ext);
					}
				} catch (e) {
					console.warn('[SAB] Could not update extension data', e);
				}
			}
		}

		/**
		 * Helper: Dispatch address to WooCommerce Blocks Cart Store.
		 */
		function dispatchAddressToStore(type, addressData) {
			if (!window.wp || !window.wp.data || !window.wp.data.dispatch) {
				return;
			}

			try {
				var cartStore = window.wp.data.dispatch('wc/store/cart');
				if (!cartStore) {
					return;
				}

				var formatted = {
					first_name: addressData.first_name || '',
					last_name:  addressData.last_name || '',
					company:    addressData.company || '',
					address_1:  addressData.address_1 || '',
					address_2:  addressData.address_2 || '',
					city:       addressData.city || '',
					state:      addressData.state || '',
					postcode:   addressData.postal_code || (addressData.postcode || ''),
					country:    addressData.country || '',
					phone:      addressData.phone || ''
				};

				if (type === 'shipping' && cartStore.setShippingAddress) {
					cartStore.setShippingAddress(formatted);
				} else if (type === 'billing' && cartStore.setBillingAddress) {
					formatted.email = addressData.email || '';
					cartStore.setBillingAddress(formatted);
				}
			} catch (e) {
				console.warn('[SAB] Error dispatching address to cart store', e);
			}
		}

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
		 * Build the HTML for the Blocks Address Selector.
		 */
		function buildSelectorHtml(type, addresses, defaultId) {
			var label = (type === 'billing')
				? (settings.i18n.select_billing || 'Select a saved billing address:')
				: (settings.i18n.select_shipping || 'Select a saved shipping address:');

			var btnLabel = (type === 'billing')
				? (settings.i18n.add_new_billing || '+ Add New Billing Address')
				: (settings.i18n.add_new_shipping || '+ Add New Shipping Address');

			var editLabel = (settings.i18n && settings.i18n.edit) ? settings.i18n.edit : 'Edit';
			var saveLabel = (settings.i18n && settings.i18n.save_changes) ? settings.i18n.save_changes : 'Save Changes';
			var cancelLabel = (settings.i18n && settings.i18n.cancel) ? settings.i18n.cancel : 'Cancel';

			var enableCheckoutSave = (typeof settings.enable_checkout_save !== 'undefined') ? !!settings.enable_checkout_save : true;
			var canAddForType = (type === 'billing') ? settings.can_add_billing : settings.can_add_shipping;
			if (typeof canAddForType === 'undefined') {
				canAddForType = (typeof settings.can_add_address !== 'undefined') ? !!settings.can_add_address : true;
			}
			var validateRequired = (typeof settings.validate_required_fields !== 'undefined') ? !!settings.validate_required_fields : true;
			var reqMark = validateRequired ? ' <abbr class="required" title="required">*</abbr>' : '';

			var selectId = 'sab_blocks_' + type + '_address_select';

			var selectedAddr = null;
			$.each(addresses, function(i, item) {
				if (item.id === defaultId) {
					selectedAddr = item.wc_address || item.raw;
					return false;
				}
			});
			if (!selectedAddr && addresses.length > 0) {
				selectedAddr = addresses[0].wc_address || addresses[0].raw;
			}

			var countries = settings.countries || {};

			var html = '<div class="sab-blocks-selector-wrapper sab-blocks-' + type + '-selector" data-type="' + type + '">';
			html += '  <div class="sab-selector-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">';
			html += '    <label for="' + selectId + '" style="font-weight:600; margin:0; font-size:14px;">' + label + '</label>';
			if (enableCheckoutSave) {
				html += '    <button type="button" class="button sab-add-new-address-btn" data-type="' + type + '" style="font-size:12px; padding:4px 10px; cursor:pointer;">' + btnLabel + '</button>';
			}
			html += '  </div>';
			html += '  <div class="sab-selector-control" style="margin-bottom:12px;">';
			html += '    <select id="' + selectId + '" class="sab-blocks-address-dropdown" data-type="' + type + '" style="width:100%; padding:8px 10px; border:1px solid #757575; border-radius:4px; font-size:14px; background:#fff;">';

			// Addresses in order: Default first, Recently Used next, Remaining after
			$.each(addresses, function(i, item) {
				var isSelected = (item.id === defaultId);
				html += '<option value="' + item.id + '"' + (isSelected ? ' selected="selected"' : '') + ' data-address=\'' + JSON.stringify(item.wc_address || item.raw) + '\'>' + item.label + '</option>';
			});

			html += '    </select>';
			html += '  </div>';

			// 1. Address Summary Card (Displays selected address with exactly ONE Edit button beside it)
			var typeTitle = (type === 'billing') ? 'Billing Address' : 'Shipping Address';
			var summaryStyle = selectedAddr ? '' : 'display:none;';
			html += '  <div class="sab-address-summary-box sab-blocks-summary-box" id="sab_blocks_' + type + '_summary_box" style="' + summaryStyle + '">';
			html += '    <div class="sab-summary-card">';
			html += '      <div class="sab-summary-content">';
			html += '        <div class="sab-summary-header-row">';
			html += '          <span class="sab-summary-title"><strong>' + typeTitle + '</strong></span>';
			html += '        </div>';
			html += '        <div class="sab-summary-text" id="sab_blocks_' + type + '_summary_text">';
			html +=            formatAddressSummary(selectedAddr, type);
			html += '        </div>';
			html += '      </div>';
			html += '      <div class="sab-summary-action">';
			html += '        <button type="button" class="button sab-summary-edit-btn sab-blocks-summary-edit-btn" data-type="' + type + '">';
			html +=            editLabel;
			html += '        </button>';
			html += '      </div>';
			html += '    </div>';
			html += '  </div>';

			// 2. Complete Address Form below summary (Initially hidden, opens on Edit click)
			html += '  <div class="sab-blocks-inline-edit-form" id="sab_blocks_' + type + '_edit_form" style="display:none;">';
			html += '    <div class="sab-blocks-form-grid">';
			html += '      <div class="sab-blocks-form-col">';
			html += '        <label>First Name' + reqMark + '</label>';
			html += '        <input type="text" name="first_name" class="sab-input" value="' + (selectedAddr ? (selectedAddr.first_name || '') : '') + '" />';
			html += '      </div>';
			html += '      <div class="sab-blocks-form-col">';
			html += '        <label>Last Name' + reqMark + '</label>';
			html += '        <input type="text" name="last_name" class="sab-input" value="' + (selectedAddr ? (selectedAddr.last_name || '') : '') + '" />';
			html += '      </div>';
			html += '      <div class="sab-blocks-form-col sab-blocks-form-full">';
			html += '        <label>Company Name <span class="optional">(optional)</span></label>';
			html += '        <input type="text" name="company" class="sab-input" value="' + (selectedAddr ? (selectedAddr.company || '') : '') + '" />';
			html += '      </div>';
			html += '      <div class="sab-blocks-form-col sab-blocks-form-full">';
			html += '        <label>Country / Region' + reqMark + '</label>';
			html += '        <select name="country" class="sab-select sab-blocks-country-select" data-type="' + type + '">';
			var selCountry = selectedAddr ? (selectedAddr.country || 'US') : 'US';
			$.each(countries, function(code, cname) {
				html += '<option value="' + code + '"' + (code === selCountry ? ' selected="selected"' : '') + '>' + cname + '</option>';
			});
			html += '        </select>';
			html += '      </div>';
			html += '      <div class="sab-blocks-form-col sab-blocks-form-full">';
			html += '        <label>Street Address' + reqMark + '</label>';
			html += '        <input type="text" name="address_1" class="sab-input" placeholder="House number and street name" value="' + (selectedAddr ? (selectedAddr.address_1 || '') : '') + '" />';
			html += '      </div>';
			html += '      <div class="sab-blocks-form-col sab-blocks-form-full">';
			html += '        <label>Apartment, suite, unit, etc. <span class="optional">(optional)</span></label>';
			html += '        <input type="text" name="address_2" class="sab-input" placeholder="Apartment, suite, unit, etc. (optional)" value="' + (selectedAddr ? (selectedAddr.address_2 || '') : '') + '" />';
			html += '      </div>';
			html += '      <div class="sab-blocks-form-col sab-blocks-form-full">';
			html += '        <label>Town / City' + reqMark + '</label>';
			html += '        <input type="text" name="city" class="sab-input" value="' + (selectedAddr ? (selectedAddr.city || '') : '') + '" />';
			html += '      </div>';
			html += '      <div class="sab-blocks-form-col" id="sab_blocks_' + type + '_state_wrapper">';
			html += '        <label>State / County</label>';
			html += '        <input type="text" name="state" class="sab-input" value="' + (selectedAddr ? (selectedAddr.state || '') : '') + '" />';
			html += '      </div>';
			html += '      <div class="sab-blocks-form-col">';
			html += '        <label>Postcode / ZIP' + reqMark + '</label>';
			html += '        <input type="text" name="postal_code" class="sab-input" value="' + (selectedAddr ? (selectedAddr.postal_code || (selectedAddr.postcode || '')) : '') + '" />';
			html += '      </div>';
			html += '      <div class="sab-blocks-form-col">';
			html += '        <label>Phone Number <span class="optional">(optional)</span></label>';
			html += '        <input type="tel" name="phone" class="sab-input" value="' + (selectedAddr ? (selectedAddr.phone || '') : '') + '" />';
			html += '      </div>';
			html += '      <div class="sab-blocks-form-col">';
			html += '        <label>Email Address' + reqMark + '</label>';
			html += '        <input type="email" name="email" class="sab-input" value="' + (selectedAddr ? (selectedAddr.email || '') : '') + '" />';
			html += '      </div>';
			html += '    </div>';

			// 3. Save Changes and Cancel buttons displayed at bottom of opened form, below all fields
			html += '    <div class="sab-inline-form-actions">';
			html += '      <button type="button" class="button button-primary sab-blocks-save-btn" data-type="' + type + '">' + saveLabel + '</button>';
			html += '      <button type="button" class="button sab-blocks-cancel-btn" data-type="' + type + '">' + cancelLabel + '</button>';
			html += '    </div>';
			html += '  </div>';

			html += '</div>';

			return html;
		}

		/**
		 * Helper: Remove duplicate summaries and native forms in Checkout Blocks.
		 */
		function cleanBlocksDuplicatesAndForms() {
			var $blockCheckout = $('.wp-block-woocommerce-checkout');
			if (!$blockCheckout.length) {
				return;
			}

			// Remove "+ Enter a new address" option from all address dropdowns in Block Checkout
			$('.sab-blocks-address-dropdown option, .wp-block-woocommerce-checkout select option').filter(function() {
				var txt = $(this).text().toLowerCase();
				return txt.indexOf('enter a new address') !== -1 || $(this).val() === 'new';
			}).remove();

			// 1. Shipping: Remove duplicate shipping address card / summary
			var $shippingSection = $blockCheckout.find('.wc-block-checkout__shipping-fields, .wp-block-woocommerce-checkout-shipping-address-block');
			if ($shippingSection.length) {
				$shippingSection.find('.wc-block-components-address-form-wrapper, .wc-block-components-address-form, .wc-block-components-address-card-wrapper, .wc-block-components-address-card').hide();
				if ($blockCheckout.find('.sab-blocks-shipping-selector').length > 1) {
					$blockCheckout.find('.sab-blocks-shipping-selector:not(:first)').remove();
				}
				if ($blockCheckout.find('#sab_blocks_shipping_summary_box').length > 1) {
					$blockCheckout.find('#sab_blocks_shipping_summary_box:not(:first)').remove();
				}

				// Keep SAB full shipping form hidden by default until Edit is clicked
				var $shippingEditForm = $('#sab_blocks_shipping_edit_form');
				if ($shippingEditForm.length && !$shippingEditForm.hasClass('sab-form-expanded')) {
					$shippingEditForm.hide();
				}
			}

			// 2. Billing: Remove native billing form visible by default & duplicate summaries
			var $billingSection = $blockCheckout.find('.wc-block-checkout__billing-fields, .wp-block-woocommerce-checkout-billing-address-block');
			if ($billingSection.length) {
				$billingSection.find('.wc-block-components-address-form-wrapper, .wc-block-components-address-form, .wc-block-components-address-card-wrapper, .wc-block-components-address-card').hide();
				if ($blockCheckout.find('.sab-blocks-billing-selector').length > 1) {
					$blockCheckout.find('.sab-blocks-billing-selector:not(:first)').remove();
				}
				if ($blockCheckout.find('#sab_blocks_billing_summary_box').length > 1) {
					$blockCheckout.find('#sab_blocks_billing_summary_box:not(:first)').remove();
				}

				// Keep SAB full billing form hidden by default until Edit is clicked
				var $billingEditForm = $('#sab_blocks_billing_edit_form');
				if ($billingEditForm.length && !$billingEditForm.hasClass('sab-form-expanded')) {
					$billingEditForm.hide();
				}
			}
		}

		/**
		 * Inject selectors into WooCommerce Checkout Block DOM.
		 * Places the saved address selectors below their respective section headings.
		 */
		function mountBlocksSelectors() {
			var $blockCheckout = $('.wp-block-woocommerce-checkout');
			if (!$blockCheckout.length) {
				return;
			}

			// 1. Mount Shipping Selector
			if (settings.enable_shipping && shippingAddresses.length > 0) {
				var $shippingBlock = $blockCheckout.find('.wp-block-woocommerce-checkout-shipping-address-block').first();
				if (!$shippingBlock.length) {
					$shippingBlock = $blockCheckout.find('.wc-block-checkout__shipping-fields').first().closest('.wc-block-components-checkout-step, .wp-block-woocommerce-checkout-shipping-address-block');
				}
				if (!$shippingBlock.length) {
					$shippingBlock = $blockCheckout.find('.wc-block-checkout__shipping-fields').first();
				}

				if ($shippingBlock.length) {
					var $shippingTitle = $shippingBlock.find('.wc-block-components-checkout-step__title, .wc-block-components-title, h2, h3').first();
					var $shippingDesc = $shippingBlock.find('.wc-block-components-checkout-step__description').first();
					var $shippingFields = $shippingBlock.find('.wc-block-checkout__shipping-fields, .wc-block-components-checkout-step__container').first();
					var $existingShippingSelector = $blockCheckout.find('.sab-blocks-shipping-selector');

					if (!$existingShippingSelector.length) {
						var shippingHtml = buildSelectorHtml('shipping', shippingAddresses, defaultShippingId);
						if ($shippingFields.length) {
							$shippingFields.prepend(shippingHtml);
						} else if ($shippingDesc.length) {
							$shippingDesc.after(shippingHtml);
						} else if ($shippingTitle.length) {
							$shippingTitle.after(shippingHtml);
						} else {
							$shippingBlock.prepend(shippingHtml);
						}

						// Auto-select default
						if (defaultShippingId) {
							var defaultShipping = shippingAddresses.find(function(a) { return a.id === defaultShippingId; }) || shippingAddresses[0];
							if (defaultShipping) {
								dispatchAddressToStore('shipping', defaultShipping.wc_address || defaultShipping.raw);
								updateBlockExtensionData('shipping_address_id', defaultShipping.id);
							}
						}
					} else if ($shippingTitle.length && $existingShippingSelector.length) {
						// Ensure "Shipping address" heading is above saved shipping selector
						var titleNode = $shippingTitle.get(0);
						var selectorNode = $existingShippingSelector.get(0);
						if (titleNode && selectorNode) {
							var comp = titleNode.compareDocumentPosition(selectorNode);
							// Node.DOCUMENT_POSITION_PRECEDING = 2 (meaning selector precedes heading)
							if (comp & 2) {
								if ($shippingFields.length) {
									$shippingFields.prepend($existingShippingSelector);
								} else {
									$shippingTitle.after($existingShippingSelector);
								}
							}
						}
					}
				}
			}

			// 2. Mount Billing Selector
			if (settings.enable_billing && billingAddresses.length > 0) {
				var $billingBlock = $blockCheckout.find('.wp-block-woocommerce-checkout-billing-address-block').first();
				if (!$billingBlock.length) {
					$billingBlock = $blockCheckout.find('.wc-block-checkout__billing-fields').first().closest('.wc-block-components-checkout-step, .wp-block-woocommerce-checkout-billing-address-block');
				}
				if (!$billingBlock.length) {
					$billingBlock = $blockCheckout.find('.wc-block-checkout__billing-fields').first();
				}

				if ($billingBlock.length) {
					var $billingTitle = $billingBlock.find('.wc-block-components-checkout-step__title, .wc-block-components-title, h2, h3').first();
					var $billingDesc = $billingBlock.find('.wc-block-components-checkout-step__description').first();
					var $billingFields = $billingBlock.find('.wc-block-checkout__billing-fields, .wc-block-components-checkout-step__container').first();
					var $existingBillingSelector = $blockCheckout.find('.sab-blocks-billing-selector');

					if (!$existingBillingSelector.length) {
						var billingHtml = buildSelectorHtml('billing', billingAddresses, defaultBillingId);
						if ($billingFields.length) {
							$billingFields.prepend(billingHtml);
						} else if ($billingDesc.length) {
							$billingDesc.after(billingHtml);
						} else if ($billingTitle.length) {
							$billingTitle.after(billingHtml);
						} else {
							$billingBlock.prepend(billingHtml);
						}

						// Auto-select default
						if (defaultBillingId) {
							var defaultBilling = billingAddresses.find(function(a) { return a.id === defaultBillingId; }) || billingAddresses[0];
							if (defaultBilling) {
								dispatchAddressToStore('billing', defaultBilling.wc_address || defaultBilling.raw);
								updateBlockExtensionData('billing_address_id', defaultBilling.id);
							}
						}
					} else if ($billingTitle.length && $existingBillingSelector.length) {
						// Ensure "Billing address" heading is above saved billing selector
						var bTitleNode = $billingTitle.get(0);
						var bSelectorNode = $existingBillingSelector.get(0);
						if (bTitleNode && bSelectorNode) {
							var bComp = bTitleNode.compareDocumentPosition(bSelectorNode);
							if (bComp & 2) {
								if ($billingFields.length) {
									$billingFields.prepend($existingBillingSelector);
								} else {
									$billingTitle.after($existingBillingSelector);
								}
							}
						}
					}
				}
			}

			cleanBlocksDuplicatesAndForms();
		}

		// Initial mount attempt
		mountBlocksSelectors();

		// Use MutationObserver to detect when Checkout Block finishes hydrating / updates DOM
		var checkoutRoot = document.querySelector('.wp-block-woocommerce-checkout') || document.body;
		var observer = new MutationObserver(function() {
			mountBlocksSelectors();
		});

		observer.observe(checkoutRoot, { childList: true, subtree: true });

		/**
		 * Handler for Checkout Blocks Address Dropdown Change
		 */
		$(document).on('change', '.sab-blocks-address-dropdown', function() {
			var $dropdown = $(this);
			var type = $dropdown.data('type') || 'shipping';
			var val = $dropdown.val();
			var $summaryBox = $('#sab_blocks_' + type + '_summary_box');
			var $editForm = $('#sab_blocks_' + type + '_edit_form');

			if (val === 'new') {
				$summaryBox.hide();
				$editForm.find('input').val('');
				$editForm.addClass('sab-form-expanded').slideDown(250);
				updateBlockExtensionData(type + '_address_id', 0);
				return;
			}

			var addressId = parseInt(val, 10);
			var $opt = $dropdown.find('option:selected');
			var raw = $opt.data('address');

			if (typeof raw === 'string') {
				try {
					raw = JSON.parse(raw);
				} catch (e) {}
			}

			if (addressId && raw) {
				updateBlockExtensionData(type + '_address_id', addressId);
				dispatchAddressToStore(type, raw);

				// Update summary and show it; hide inline editing form
				$('#sab_blocks_' + type + '_summary_text').html(formatAddressSummary(raw, type));
				$summaryBox.show();
				$editForm.removeClass('sab-form-expanded').slideUp(250);
			}
		});

		/**
		 * Click Edit beside summary in Checkout Blocks:
		 * Opens complete corresponding address form below that address summary.
		 */
		$(document).on('click', '.sab-blocks-summary-edit-btn', function(e) {
			e.preventDefault();
			var type = $(this).data('type') || 'shipping';
			var $editForm = $('#sab_blocks_' + type + '_edit_form');
			var $dropdown = $('#sab_blocks_' + type + '_address_select');

			var addressData = null;
			if ($dropdown.length) {
				var $opt = $dropdown.find('option:selected');
				addressData = $opt.data('address');
				if (typeof addressData === 'string') {
					try {
						addressData = JSON.parse(addressData);
					} catch (err) {}
				}
			}

			if (addressData) {
				$editForm.find('[name="first_name"]').val(addressData.first_name || '');
				$editForm.find('[name="last_name"]').val(addressData.last_name || '');
				$editForm.find('[name="company"]').val(addressData.company || '');
				$editForm.find('[name="country"]').val(addressData.country || 'US');
				$editForm.find('[name="address_1"]').val(addressData.address_1 || '');
				$editForm.find('[name="address_2"]').val(addressData.address_2 || '');
				$editForm.find('[name="city"]').val(addressData.city || '');
				$editForm.find('[name="state"]').val(addressData.state || '');
				$editForm.find('[name="postal_code"]').val(addressData.postal_code || (addressData.postcode || ''));
				$editForm.find('[name="phone"]').val(addressData.phone || '');
				$editForm.find('[name="email"]').val(addressData.email || '');
			}

			// Store backup snapshot
			var backup = {};
			$editForm.find('input, select').each(function() {
				var name = $(this).attr('name');
				if (name) {
					backup[name] = $(this).val();
				}
			});
			$editForm.data('sab-backup', backup);

			// Clear previous errors
			$editForm.find('.sab-field-error-message').remove();
			$editForm.find('.sab-input-error').removeClass('sab-input-error');

			// Open form below summary
			$editForm.addClass('sab-form-expanded').slideDown(250);
			$editForm.find('input:first').trigger('focus');
		});

		/**
		 * Cancel button in Checkout Blocks:
		 * Closes the editing form without saving changes.
		 */
		$(document).on('click', '.sab-blocks-cancel-btn', function(e) {
			e.preventDefault();
			var type = $(this).data('type') || 'shipping';
			var $editForm = $('#sab_blocks_' + type + '_edit_form');

			// Restore backup values
			var backup = $editForm.data('sab-backup');
			if (backup) {
				$.each(backup, function(name, val) {
					var $el = $editForm.find('[name="' + name + '"]');
					if ($el.length) {
						$el.val(val);
					}
				});
			}

			// Clear errors & slide up
			$editForm.find('.sab-field-error-message').remove();
			$editForm.find('.sab-input-error').removeClass('sab-input-error');
			$editForm.removeClass('sab-form-expanded').slideUp(250);
		});

		/**
		 * Save Changes button in Checkout Blocks:
		 * Validates and updates the selected address in the current checkout.
		 */
		$(document).on('click', '.sab-blocks-save-btn', function(e) {
			e.preventDefault();
			var type = $(this).data('type') || 'shipping';
			var $editForm = $('#sab_blocks_' + type + '_edit_form');

			// Validation
			var errors = {};
			var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			var validateRequired = (typeof settings.validate_required_fields !== 'undefined') ? !!settings.validate_required_fields : true;

			if (validateRequired) {
				var firstName = $.trim($editForm.find('[name="first_name"]').val());
				if (!firstName) errors.first_name = 'First Name is a required field.';

				var lastName = $.trim($editForm.find('[name="last_name"]').val());
				if (!lastName) errors.last_name = 'Last Name is a required field.';

				var country = $.trim($editForm.find('[name="country"]').val());
				if (!country) errors.country = 'Country / Region is a required field.';

				var address1 = $.trim($editForm.find('[name="address_1"]').val());
				if (!address1) errors.address_1 = 'Street Address is a required field.';

				var city = $.trim($editForm.find('[name="city"]').val());
				if (!city) errors.city = 'Town / City is a required field.';

				var postcode = $.trim($editForm.find('[name="postal_code"]').val());
				if (!postcode) errors.postal_code = 'Postcode / ZIP is a required field.';
			}

			// Email address validation: required when validateRequired is true, format checked if entered
			var email = $.trim($editForm.find('[name="email"]').val());
			if (!email) {
				if (validateRequired) {
					errors.email = 'Email Address is a required field.';
				}
			} else if (!emailRegex.test(email)) {
				errors.email = 'Please enter a valid email address.';
			}

			// Clear old error displays
			$editForm.find('.sab-field-error-message').remove();
			$editForm.find('.sab-input-error').removeClass('sab-input-error');

			if (Object.keys(errors).length > 0) {
				var $firstErr = null;
				$.each(errors, function(fieldName, msg) {
					var $input = $editForm.find('[name="' + fieldName + '"]');
					if ($input.length) {
						$input.addClass('sab-input-error');
						$input.after($('<span class="sab-field-error-message">' + msg + '</span>'));
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

			// Valid: construct updated address
			var updatedAddr = {
				first_name:  $editForm.find('[name="first_name"]').val() || '',
				last_name:   $editForm.find('[name="last_name"]').val() || '',
				company:     $editForm.find('[name="company"]').val() || '',
				country:     $editForm.find('[name="country"]').val() || '',
				address_1:   $editForm.find('[name="address_1"]').val() || '',
				address_2:   $editForm.find('[name="address_2"]').val() || '',
				city:        $editForm.find('[name="city"]').val() || '',
				state:       $editForm.find('[name="state"]').val() || '',
				postal_code: $editForm.find('[name="postal_code"]').val() || '',
				postcode:    $editForm.find('[name="postal_code"]').val() || '',
				phone:       $editForm.find('[name="phone"]').val() || '',
				email:       $editForm.find('[name="email"]').val() || ''
			};

			// Dispatch to WooCommerce Cart Store
			dispatchAddressToStore(type, updatedAddr);

			// Update dropdown option data & label
			var $dropdown = $('#sab_blocks_' + type + '_address_select');
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

			// Update summary card text & display
			$('#sab_blocks_' + type + '_summary_text').html(formatAddressSummary(updatedAddr, type));
			$('#sab_blocks_' + type + '_summary_box').show();

			// Close editing form
			$editForm.removeClass('sab-form-expanded').slideUp(250);
		});

		/**
		 * Custom event listener: When a new address is added via modal, update blocks selectors
		 */
		$(document).on('sab_address_created', function(e, data) {
			if (!data || !data.address_id || !data.type) {
				return;
			}

			var type = data.type;
			var newId = data.address_id;
			var raw = data.address;
			var label = data.label;

			var newEntry = {
				id: newId,
				label: label,
				raw: raw,
				wc_address: raw
			};

			if (type === 'shipping') {
				shippingAddresses.unshift(newEntry);
			} else if (type === 'billing') {
				billingAddresses.unshift(newEntry);
			}

			var $dropdown = $('#sab_blocks_' + type + '_address_select');
			if ($dropdown.length) {
				var $existing = $dropdown.find('option[value="' + newId + '"]');
				if (!$existing.length) {
					var $newOpt = $('<option></option>')
						.val(newId)
						.text(label)
						.attr('data-address', JSON.stringify(raw));

					var $firstOpt = $dropdown.find('option:first');
					if ($firstOpt.length) {
						$firstOpt.after($newOpt);
					} else {
						$dropdown.prepend($newOpt);
					}
				}
				$dropdown.val(newId).trigger('change');
			} else {
				// If selector was not rendered previously, mount now
				mountBlocksSelectors();
				var $newDropdown = $('#sab_blocks_' + type + '_address_select');
				if ($newDropdown.length) {
					$newDropdown.val(newId).trigger('change');
				}
			}

			// Update limits for type if maximum limit configured
			var currentCount = (type === 'shipping') ? shippingAddresses.length : billingAddresses.length;
			if (settings.max_addresses > 0 && currentCount >= settings.max_addresses) {
				if (type === 'shipping') {
					settings.can_add_shipping = false;
					if (typeof sab_checkout_params !== 'undefined') sab_checkout_params.can_add_shipping = false;
					if (typeof sab_blocks_checkout_params !== 'undefined') sab_blocks_checkout_params.can_add_shipping = false;
				} else {
					settings.can_add_billing = false;
					if (typeof sab_checkout_params !== 'undefined') sab_checkout_params.can_add_billing = false;
					if (typeof sab_blocks_checkout_params !== 'undefined') sab_blocks_checkout_params.can_add_billing = false;
				}
			}
		});

		/**
		 * Custom event listener: When an address is edited during checkout, update blocks store & option
		 */
		$(document).on('sab_address_edited', function(e, data) {
			if (!data || !data.type || !data.address) {
				return;
			}

			var type = data.type;
			var edited = data.address;

			dispatchAddressToStore(type, edited);

			var $dropdown = $('#sab_blocks_' + type + '_address_select');
			if ($dropdown.length) {
				var $opt = $dropdown.find('option:selected');
				if ($opt.length && $opt.val() !== 'new') {
					$opt.data('address', edited);
					$opt.attr('data-address', JSON.stringify(edited));
					var curText = $opt.text();
					if (curText.indexOf('(Edited)') === -1) {
						$opt.text(curText + ' (Edited)');
					}
				}
			}

			$('#sab_blocks_' + type + '_summary_text').html(formatAddressSummary(edited, type));
			$('#sab_blocks_' + type + '_summary_box').show();
			$('#sab_blocks_' + type + '_edit_form').slideUp(250);
		});

	});

})(jQuery);

