<?php
/**
 * Checkout Integration for Smart Address Book.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAB_Checkout {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Classic Checkout Hooks
		add_action( 'woocommerce_before_checkout_billing_form', array( $this, 'render_billing_selector' ) );
		add_action( 'woocommerce_before_checkout_shipping_form', array( $this, 'render_shipping_selector' ) );

		add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'render_billing_save_checkbox' ) );
		add_action( 'woocommerce_after_checkout_shipping_form', array( $this, 'render_shipping_save_checkbox' ) );

		// Inline Save Changes and Cancel buttons below fields
		add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'render_billing_inline_actions' ), 20 );
		add_action( 'woocommerce_after_checkout_shipping_form', array( $this, 'render_shipping_inline_actions' ), 20 );

		// Render modal on checkout
		add_action( 'woocommerce_after_checkout_form', array( $this, 'render_address_modal' ) );
		add_action( 'wp_footer', array( $this, 'render_address_modal' ) );

		// Hook into order creation to save selected address IDs and update last_used_at
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_order_address_meta' ), 10, 2 );

		// Hook into order completion to save new addresses if requested
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_save_on_checkout' ), 10, 3 );
	}

	/**
	 * Save selected Smart Address Book IDs into order meta on order creation.
	 *
	 * @param \WC_Order $order Order instance.
	 * @param array     $data Checkout data.
	 */
	public function save_order_address_meta( $order, $data ) {
		$customer_id = (int) $order->get_customer_id();
		if ( ! $customer_id ) {
			$customer_id = get_current_user_id();
		}

		if ( $customer_id <= 0 ) {
			return;
		}

		$billing_id  = absint( $_POST['sab_billing_address_id'] ?? 0 );
		$shipping_id = absint( $_POST['sab_shipping_address_id'] ?? 0 );

		if ( $billing_id > 0 && 'yes' === get_option( 'sab_enable_billing', 'yes' ) ) {
			$billing_address = SAB_Database::get_address( $billing_id );
			if ( $billing_address && (int) $billing_address->user_id === $customer_id && 'billing' === $billing_address->address_type ) {
				$order->update_meta_data( '_sab_billing_address_id', $billing_id );
				SAB_Database::update_last_used( $billing_id );
			}
		}

		if ( $shipping_id > 0 && 'yes' === get_option( 'sab_enable_shipping', 'yes' ) ) {
			$shipping_address = SAB_Database::get_address( $shipping_id );
			if ( $shipping_address && (int) $shipping_address->user_id === $customer_id && 'shipping' === $shipping_address->address_type ) {
				$order->update_meta_data( '_sab_shipping_address_id', $shipping_id );
				SAB_Database::update_last_used( $shipping_id );
			}
		}
	}

	/**
	 * Render Billing Address Selector Dropdown.
	 */
	public function render_billing_selector() {
		if ( ! is_user_logged_in() || 'yes' !== get_option( 'sab_enable_billing', 'yes' ) ) {
			return;
		}

		$user_id   = get_current_user_id();
		$addresses = SAB_Database::get_addresses_sorted( $user_id, 'billing' );

		if ( empty( $addresses ) ) {
			return;
		}

		$address_type = 'billing';
		$type         = $address_type;
		$default      = SAB_Database::get_default_address( $user_id, 'billing' );
		$default_id = $default ? (int) $default->id : 0;

		$template_path = SAB_PLUGIN_DIR . 'public/views/checkout-selector.php';
		include $template_path;
	}

	/**
	 * Render Shipping Address Selector Dropdown.
	 */
	public function render_shipping_selector() {
		if ( ! is_user_logged_in() || 'yes' !== get_option( 'sab_enable_shipping', 'yes' ) ) {
			return;
		}

		$user_id   = get_current_user_id();
		$addresses = SAB_Database::get_addresses_sorted( $user_id, 'shipping' );

		if ( empty( $addresses ) ) {
			return;
		}

		$address_type = 'shipping';
		$type         = $address_type;
		$default      = SAB_Database::get_default_address( $user_id, 'shipping' );
		$default_id = $default ? (int) $default->id : 0;

		$template_path = SAB_PLUGIN_DIR . 'public/views/checkout-selector.php';
		include $template_path;
	}

	/**
	 * Render "Save to address book" checkbox for billing.
	 */
	public function render_billing_save_checkbox() {
		if ( ! is_user_logged_in() || 'yes' !== get_option( 'sab_enable_checkout_save', 'yes' ) || ! SAB_Database::can_add_address( get_current_user_id(), 'billing' ) ) {
			return;
		}
		$this->render_save_fields( 'billing' );
	}

	/**
	 * Render "Save to address book" checkbox for shipping.
	 */
	public function render_shipping_save_checkbox() {
		if ( ! is_user_logged_in() || 'yes' !== get_option( 'sab_enable_checkout_save', 'yes' ) || ! SAB_Database::can_add_address( get_current_user_id(), 'shipping' ) ) {
			return;
		}
		$this->render_save_fields( 'shipping' );
	}

	/**
	 * Render Save Changes and Cancel buttons below billing fields.
	 */
	public function render_billing_inline_actions() {
		if ( ! is_user_logged_in() || 'yes' !== get_option( 'sab_enable_billing', 'yes' ) ) {
			return;
		}
		?>
		<div class="sab-inline-form-actions" id="sab_billing_inline_actions" style="display:none;">
			<button type="button" class="button button-primary sab-inline-save-btn" data-type="billing">
				<?php esc_html_e( 'Save Changes', 'smart-address-book' ); ?>
			</button>
			<button type="button" class="button sab-inline-cancel-btn" data-type="billing">
				<?php esc_html_e( 'Cancel', 'smart-address-book' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Render Save Changes and Cancel buttons below shipping fields.
	 */
	public function render_shipping_inline_actions() {
		if ( ! is_user_logged_in() || 'yes' !== get_option( 'sab_enable_shipping', 'yes' ) ) {
			return;
		}
		?>
		<div class="sab-inline-form-actions" id="sab_shipping_inline_actions" style="display:none;">
			<button type="button" class="button button-primary sab-inline-save-btn" data-type="shipping">
				<?php esc_html_e( 'Save Changes', 'smart-address-book' ); ?>
			</button>
			<button type="button" class="button sab-inline-cancel-btn" data-type="shipping">
				<?php esc_html_e( 'Cancel', 'smart-address-book' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Output the save address checkbox and nickname field.
	 *
	 * @param string $address_type 'billing' or 'shipping'.
	 */
	private function render_save_fields( $address_type ) {
		$allow_nickname = 'yes' === get_option( 'sab_enable_nickname', 'yes' );
		$type           = $address_type;
		?>
		<div class="sab-checkout-save-wrapper sab-checkout-save-<?php echo esc_attr( $address_type ); ?>">
			<p class="form-row form-row-wide">
				<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
					<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox sab-save-checkout-checkbox" name="sab_save_<?php echo esc_attr( $address_type ); ?>" id="sab_save_<?php echo esc_attr( $address_type ); ?>" value="1" />
					<span><?php esc_html_e( 'Save this address to my address book', 'smart-address-book' ); ?></span>
				</label>
			</p>

			<?php if ( $allow_nickname ) : ?>
			<p class="form-row form-row-wide sab-checkout-nickname-row" style="display:none;" id="sab_nickname_row_<?php echo esc_attr( $address_type ); ?>">
				<label for="sab_nickname_<?php echo esc_attr( $address_type ); ?>">
					<?php esc_html_e( 'Address Nickname / Label', 'smart-address-book' ); ?> <span class="optional">(<?php esc_html_e( 'optional', 'smart-address-book' ); ?>)</span>
				</label>
				<input type="text" class="input-text" name="sab_nickname_<?php echo esc_attr( $address_type ); ?>" id="sab_nickname_<?php echo esc_attr( $address_type ); ?>" placeholder="<?php esc_attr_e( 'e.g. Home, Office, Vacation House', 'smart-address-book' ); ?>" />
			</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Save new address when order is processed if checkbox was selected.
	 *
	 * @param int      $order_id Order ID.
	 * @param array    $posted_data Form posted data.
	 * @param WC_Order $order Order object.
	 */
	public function process_save_on_checkout( $order_id, $posted_data, $order ) {
		if ( ! is_user_logged_in() || 'yes' !== get_option( 'sab_enable_checkout_save', 'yes' ) ) {
			return;
		}

		$user_id = $order->get_customer_id();
		if ( ! $user_id ) {
			return;
		}

		// 1. Process Billing
		if ( ! empty( $_POST['sab_save_billing'] ) && 'yes' === get_option( 'sab_enable_billing', 'yes' ) ) {
			$nickname = sanitize_text_field( wp_unslash( $_POST['sab_nickname_billing'] ?? '' ) );
			if ( empty( $nickname ) ) {
				$count    = SAB_Database::get_address_count_by_type( $user_id, 'billing' ) + 1;
				$nickname = sprintf( 'Billing #%d', $count );
			}

			$billing_data = array(
				'user_id'      => $user_id,
				'address_type' => 'billing',
				'nickname'     => $nickname,
				'first_name'   => $order->get_billing_first_name(),
				'last_name'    => $order->get_billing_last_name(),
				'company'      => $order->get_billing_company(),
				'phone'        => $order->get_billing_phone(),
				'email'        => $order->get_billing_email(),
				'address_1'    => $order->get_billing_address_1(),
				'address_2'    => $order->get_billing_address_2(),
				'city'         => $order->get_billing_city(),
				'state'        => $order->get_billing_state(),
				'country'      => $order->get_billing_country(),
				'postal_code'  => $order->get_billing_postcode(),
			);

			if ( SAB_Database::can_add_address( $user_id, 'billing' ) && ! is_wp_error( SAB_Address::validate( $billing_data ) ) ) {
				$new_billing_id = SAB_Database::add_address( $billing_data );
				if ( $new_billing_id ) {
					$order->update_meta_data( '_sab_billing_address_id', $new_billing_id );
					SAB_Database::update_last_used( $new_billing_id );
					$order->save();
				}
			}
		}

		// 2. Process Shipping
		if ( ! empty( $_POST['sab_save_shipping'] ) && 'yes' === get_option( 'sab_enable_shipping', 'yes' ) ) {
			$nickname = sanitize_text_field( wp_unslash( $_POST['sab_nickname_shipping'] ?? '' ) );
			if ( empty( $nickname ) ) {
				$count    = SAB_Database::get_address_count_by_type( $user_id, 'shipping' ) + 1;
				$nickname = sprintf( 'Shipping #%d', $count );
			}

			$shipping_data = array(
				'user_id'      => $user_id,
				'address_type' => 'shipping',
				'nickname'     => $nickname,
				'first_name'   => $order->get_shipping_first_name() ?: $order->get_billing_first_name(),
				'last_name'    => $order->get_shipping_last_name() ?: $order->get_billing_last_name(),
				'company'      => $order->get_shipping_company() ?: $order->get_billing_company(),
				'phone'        => $order->get_billing_phone(),
				'email'        => $order->get_billing_email(),
				'address_1'    => $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
				'address_2'    => $order->get_shipping_address_2() ?: $order->get_billing_address_2(),
				'city'         => $order->get_shipping_city() ?: $order->get_billing_city(),
				'state'        => $order->get_shipping_state() ?: $order->get_billing_state(),
				'country'      => $order->get_shipping_country() ?: $order->get_billing_country(),
				'postal_code'  => $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
			);

			if ( SAB_Database::can_add_address( $user_id, 'shipping' ) && ! is_wp_error( SAB_Address::validate( $shipping_data ) ) ) {
				$new_shipping_id = SAB_Database::add_address( $shipping_data );
				if ( $new_shipping_id ) {
					$order->update_meta_data( '_sab_shipping_address_id', $new_shipping_id );
					SAB_Database::update_last_used( $new_shipping_id );
					$order->save();
				}
			}
		}
	}

	/**
	 * Render Add New Address Modal dialog for checkout.
	 */
	public function render_address_modal() {
		static $rendered = false;
		if ( $rendered || ! is_user_logged_in() ) {
			return;
		}

		if ( ! is_checkout() && ! is_account_page() ) {
			return;
		}

		$rendered = true;

		$countries       = ( function_exists( 'WC' ) && WC()->countries ) ? WC()->countries->get_allowed_countries() : array();
		$default_country = ( function_exists( 'WC' ) && WC()->customer ) ? ( WC()->customer->get_shipping_country() ?: 'US' ) : 'US';
		$states          = ( function_exists( 'WC' ) && WC()->countries ) ? WC()->countries->get_states( $default_country ) : array();

		$validate_required = 'yes' === get_option( 'sab_validate_required_fields', 'yes' );
		$req_mark          = $validate_required ? ' <abbr class="required" title="required">*</abbr>' : '';
		$req_attr          = $validate_required ? ' required' : '';
		?>
		<div id="sab-checkout-address-modal" class="sab-modal-backdrop" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="sab-modal-title">
			<div class="sab-modal-dialog">
				<div class="sab-modal-header">
					<h3 id="sab-modal-title"><?php esc_html_e( 'Add New Address', 'smart-address-book' ); ?></h3>
					<button type="button" class="sab-modal-close" aria-label="<?php esc_attr_e( 'Close', 'smart-address-book' ); ?>">&times;</button>
				</div>
				<div class="sab-modal-body">
					<div class="sab-modal-notice sab-notice-error" style="display:none;"></div>
					<form id="sab-checkout-modal-form" class="woocommerce-address-fields" novalidate="novalidate">
						<input type="hidden" name="action" value="sab_save_checkout_address" />
						<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'sab_checkout_nonce' ) ); ?>" />
						<input type="hidden" name="modal_mode" id="sab_modal_mode" value="add" />
						<input type="hidden" name="editing_type" id="sab_modal_editing_type" value="" />
						<input type="hidden" name="editing_address_id" id="sab_modal_editing_address_id" value="" />

						<div class="sab-modal-form-grid">
							<!-- Address Type -->
							<p class="form-row form-row-wide" id="sab_modal_type_row">
								<label><strong><?php esc_html_e( 'Address Type:', 'smart-address-book' ); ?></strong></label>
								<span class="sab-radio-group">
									<label class="sab-radio-label" style="display:inline-block; margin-right:15px; font-weight:normal; cursor:pointer;">
										<input type="radio" name="address_type" value="shipping" checked="checked" />
										<?php esc_html_e( 'Shipping Address', 'smart-address-book' ); ?>
									</label>
									<label class="sab-radio-label" style="display:inline-block; font-weight:normal; cursor:pointer;">
										<input type="radio" name="address_type" value="billing" />
										<?php esc_html_e( 'Billing Address', 'smart-address-book' ); ?>
									</label>
								</span>
							</p>

							<!-- Nickname -->
							<?php if ( 'yes' === get_option( 'sab_enable_nickname', 'yes' ) ) : ?>
							<p class="form-row form-row-wide" id="sab_modal_nickname_row">
								<label for="sab_modal_nickname">
									<?php esc_html_e( 'Address Nickname / Label', 'smart-address-book' ); ?> <span class="optional">(<?php esc_html_e( 'optional', 'smart-address-book' ); ?>)</span>
								</label>
								<input type="text" class="input-text" name="nickname" id="sab_modal_nickname" placeholder="<?php esc_attr_e( 'e.g. Home, Office, Parents', 'smart-address-book' ); ?>" />
							</p>
							<?php endif; ?>

							<!-- First & Last Name -->
							<p class="form-row form-row-first <?php echo $validate_required ? 'validate-required' : ''; ?>">
								<label for="sab_modal_first_name">
									<?php esc_html_e( 'First Name', 'smart-address-book' ); ?><?php echo $req_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</label>
								<input type="text" class="input-text" name="first_name" id="sab_modal_first_name"<?php echo $req_attr; ?> />
							</p>

							<p class="form-row form-row-last <?php echo $validate_required ? 'validate-required' : ''; ?>">
								<label for="sab_modal_last_name">
									<?php esc_html_e( 'Last Name', 'smart-address-book' ); ?><?php echo $req_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</label>
								<input type="text" class="input-text" name="last_name" id="sab_modal_last_name"<?php echo $req_attr; ?> />
							</p>

							<!-- Company -->
							<p class="form-row form-row-wide">
								<label for="sab_modal_company">
									<?php esc_html_e( 'Company Name', 'smart-address-book' ); ?> <span class="optional">(<?php esc_html_e( 'optional', 'smart-address-book' ); ?>)</span>
								</label>
								<input type="text" class="input-text" name="company" id="sab_modal_company" />
							</p>

							<!-- Country -->
							<p class="form-row form-row-wide <?php echo $validate_required ? 'validate-required' : ''; ?>">
								<label for="sab_modal_country">
									<?php esc_html_e( 'Country / Region', 'smart-address-book' ); ?><?php echo $req_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</label>
								<select name="country" id="sab_modal_country" class="country_to_state country_select"<?php echo $req_attr; ?>>
									<option value=""><?php esc_html_e( 'Select a country / region&hellip;', 'smart-address-book' ); ?></option>
									<?php foreach ( $countries as $code => $name ) : ?>
										<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $default_country, $code ); ?>><?php echo esc_html( $name ); ?></option>
									<?php endforeach; ?>
								</select>
							</p>

							<!-- Street Address -->
							<p class="form-row form-row-wide <?php echo $validate_required ? 'validate-required' : ''; ?>">
								<label for="sab_modal_address_1">
									<?php esc_html_e( 'Street Address', 'smart-address-book' ); ?><?php echo $req_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</label>
								<input type="text" class="input-text" name="address_1" id="sab_modal_address_1" placeholder="<?php esc_attr_e( 'House number and street name', 'smart-address-book' ); ?>"<?php echo $req_attr; ?> />
							</p>

							<p class="form-row form-row-wide">
								<label for="sab_modal_address_2" class="screen-reader-text">
									<?php esc_html_e( 'Apartment, suite, unit, etc.', 'smart-address-book' ); ?>
								</label>
								<input type="text" class="input-text" name="address_2" id="sab_modal_address_2" placeholder="<?php esc_attr_e( 'Apartment, suite, unit, etc. (optional)', 'smart-address-book' ); ?>" />
							</p>

							<!-- City -->
							<p class="form-row form-row-wide <?php echo $validate_required ? 'validate-required' : ''; ?>">
								<label for="sab_modal_city">
									<?php esc_html_e( 'Town / City', 'smart-address-book' ); ?><?php echo $req_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</label>
								<input type="text" class="input-text" name="city" id="sab_modal_city"<?php echo $req_attr; ?> />
							</p>

							<!-- State -->
							<p class="form-row form-row-first" id="sab_modal_state_field">
								<label for="sab_modal_state">
									<?php esc_html_e( 'State / County', 'smart-address-book' ); ?>
								</label>
								<?php if ( ! empty( $states ) && is_array( $states ) ) : ?>
									<select name="state" id="sab_modal_state" class="state_select">
										<option value=""><?php esc_html_e( 'Select an option&hellip;', 'smart-address-book' ); ?></option>
										<?php foreach ( $states as $s_code => $s_name ) : ?>
											<option value="<?php echo esc_attr( $s_code ); ?>"><?php echo esc_html( $s_name ); ?></option>
										<?php endforeach; ?>
									</select>
								<?php else : ?>
									<input type="text" class="input-text" name="state" id="sab_modal_state" />
								<?php endif; ?>
							</p>

							<!-- Postcode -->
							<p class="form-row form-row-last <?php echo $validate_required ? 'validate-required' : ''; ?>">
								<label for="sab_modal_postcode">
									<?php esc_html_e( 'Postcode / ZIP', 'smart-address-book' ); ?><?php echo $req_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</label>
								<input type="text" class="input-text" name="postal_code" id="sab_modal_postcode"<?php echo $req_attr; ?> />
							</p>

							<!-- Phone -->
							<p class="form-row form-row-first">
								<label for="sab_modal_phone">
									<?php esc_html_e( 'Phone Number', 'smart-address-book' ); ?> <span class="optional">(<?php esc_html_e( 'optional', 'smart-address-book' ); ?>)</span>
								</label>
								<input type="tel" class="input-text" name="phone" id="sab_modal_phone" />
							</p>

							<!-- Email -->
							<p class="form-row form-row-last <?php echo $validate_required ? 'validate-required' : ''; ?> validate-email" id="sab_modal_email_field">
								<label for="sab_modal_email">
									<?php esc_html_e( 'Email Address', 'smart-address-book' ); ?><?php echo $req_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</label>
								<input type="email" class="input-text" name="email" id="sab_modal_email"<?php echo $req_attr; ?> />
							</p>

							<!-- Default Checkbox -->
							<p class="form-row form-row-wide" id="sab_modal_default_row">
								<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
									<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="is_default" id="sab_modal_is_default" value="1" />
									<span><?php esc_html_e( 'Set as default address for this type', 'smart-address-book' ); ?></span>
								</label>
							</p>
						</div>

						<div class="sab-modal-footer">
							<button type="submit" class="button button-primary sab-modal-save-btn" id="sab-modal-submit-btn">
								<?php esc_html_e( 'Save and Use Address', 'smart-address-book' ); ?>
							</button>
							<button type="button" class="button sab-modal-cancel-btn" id="sab-modal-cancel-btn">
								<?php esc_html_e( 'Cancel', 'smart-address-book' ); ?>
							</button>
						</div>
					</form>
				</div>
			</div>
		</div>
		<?php
	}
}

