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

		// Hook into order completion to save new addresses if requested
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_save_on_checkout' ), 10, 3 );
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

		$type       = 'billing';
		$default    = SAB_Database::get_default_address( $user_id, 'billing' );
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

		$type       = 'shipping';
		$default    = SAB_Database::get_default_address( $user_id, 'shipping' );
		$default_id = $default ? (int) $default->id : 0;

		$template_path = SAB_PLUGIN_DIR . 'public/views/checkout-selector.php';
		include $template_path;
	}

	/**
	 * Render "Save to address book" checkbox for billing.
	 */
	public function render_billing_save_checkbox() {
		if ( ! is_user_logged_in() || 'yes' !== get_option( 'sab_enable_checkout_save', 'yes' ) ) {
			return;
		}
		$this->render_save_fields( 'billing' );
	}

	/**
	 * Render "Save to address book" checkbox for shipping.
	 */
	public function render_shipping_save_checkbox() {
		if ( ! is_user_logged_in() || 'yes' !== get_option( 'sab_enable_checkout_save', 'yes' ) ) {
			return;
		}
		$this->render_save_fields( 'shipping' );
	}

	/**
	 * Output the save address checkbox and nickname field.
	 *
	 * @param string $type 'billing' or 'shipping'.
	 */
	private function render_save_fields( $type ) {
		$allow_nickname = 'yes' === get_option( 'sab_enable_nickname', 'yes' );
		?>
		<div class="sab-checkout-save-wrapper sab-checkout-save-<?php echo esc_attr( $type ); ?>">
			<p class="form-row form-row-wide">
				<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
					<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox sab-save-checkout-checkbox" name="sab_save_<?php echo esc_attr( $type ); ?>" id="sab_save_<?php echo esc_attr( $type ); ?>" value="1" />
					<span><?php esc_html_e( 'Save this address to my address book', 'smart-address-book' ); ?></span>
				</label>
			</p>

			<?php if ( $allow_nickname ) : ?>
			<p class="form-row form-row-wide sab-checkout-nickname-row" style="display:none;" id="sab_nickname_row_<?php echo esc_attr( $type ); ?>">
				<label for="sab_nickname_<?php echo esc_attr( $type ); ?>">
					<?php esc_html_e( 'Address Nickname / Label', 'smart-address-book' ); ?> <span class="optional">(<?php esc_html_e( 'optional', 'smart-address-book' ); ?>)</span>
				</label>
				<input type="text" class="input-text" name="sab_nickname_<?php echo esc_attr( $type ); ?>" id="sab_nickname_<?php echo esc_attr( $type ); ?>" placeholder="<?php esc_attr_e( 'e.g. Home, Office, Vacation House', 'smart-address-book' ); ?>" />
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

			if ( ! is_wp_error( SAB_Address::validate( $billing_data ) ) ) {
				SAB_Database::add_address( $billing_data );
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

			if ( ! is_wp_error( SAB_Address::validate( $shipping_data ) ) ) {
				SAB_Database::add_address( $shipping_data );
			}
		}
	}
}

