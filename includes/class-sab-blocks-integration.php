<?php
/**
 * WooCommerce Blocks Integration for Smart Address Book.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! interface_exists( '\Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface' ) ) {
	return;
}

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

class SAB_Blocks_Integration implements IntegrationInterface {

	/**
	 * Name of integration.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'smart-address-book';
	}

	/**
	 * Initialize integration.
	 */
	public function initialize() {
		$this->register_assets();
		$this->register_store_api();
	}

	/**
	 * Register frontend assets.
	 */
	private function register_assets() {
		wp_register_style(
			'sab-blocks-checkout',
			SAB_PLUGIN_URL . 'public/css/sab-public.css',
			array(),
			SAB_VERSION
		);

		$script_version = defined( 'SAB_VERSION' ) ? SAB_VERSION . '.' . filemtime( SAB_PLUGIN_DIR . 'public/js/sab-blocks-checkout.js' ) : time();

		wp_register_script(
			'sab-blocks-checkout',
			SAB_PLUGIN_URL . 'public/js/sab-blocks-checkout.js',
			array( 'jquery', 'wp-data', 'wp-element', 'wp-hooks' ),
			$script_version,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'sab-blocks-checkout', 'smart-address-book' );
		}
	}

	/**
	 * Register Store API extensions and hooks.
	 */
	private function register_store_api() {
		if ( function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			try {
				woocommerce_store_api_register_endpoint_data(
					array(
						'endpoint'        => 'checkout',
						'namespace'       => 'smart-address-book',
						'data_callback'   => array( $this, 'get_store_api_data' ),
						'schema_callback' => array( $this, 'get_store_api_schema' ),
						'schema_type'     => ARRAY_A,
					)
				);
			} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Silently catch if already registered
			}
		}

		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'update_order_from_request' ), 10, 2 );
	}

	/**
	 * Returns an array of script handles to enqueue in the frontend context.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return array( 'sab-blocks-checkout' );
	}

	/**
	 * Returns an array of script handles to enqueue in the editor context.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return array();
	}

	/**
	 * An array of key, value pairs of data made available to the block on the client side.
	 *
	 * @return array
	 */
	public function get_script_data() {
		if ( ! is_user_logged_in() ) {
			return array(
				'is_logged_in' => false,
			);
		}

		$user_id = get_current_user_id();

		$billing_addresses  = SAB_Database::get_addresses_sorted( $user_id, 'billing' );
		$shipping_addresses = SAB_Database::get_addresses_sorted( $user_id, 'shipping' );

		$default_billing  = SAB_Database::get_default_address( $user_id, 'billing' );
		$default_shipping = SAB_Database::get_default_address( $user_id, 'shipping' );

		$format_address_list = function( $list, $default_id ) {
			$formatted = array();
			foreach ( $list as $address ) {
				$label_parts = array();
				if ( ! empty( $address->nickname ) ) {
					$label_parts[] = '[' . $address->nickname . ']';
				}
				$label_parts[] = trim( $address->first_name . ' ' . $address->last_name );
				$label_parts[] = $address->address_1;
				$label_parts[] = $address->city . ( $address->postal_code ? ' ' . $address->postal_code : '' );

				$display_label = implode( ' — ', array_filter( $label_parts ) );
				if ( (int) $address->id === (int) $default_id || (int) $address->is_default === 1 ) {
					$display_label .= ' (' . __( 'Default', 'smart-address-book' ) . ')';
				} elseif ( ! empty( $address->is_recently_used ) ) {
					$display_label .= ' (' . __( 'Recently Used', 'smart-address-book' ) . ')';
				}

				$formatted[] = array(
					'id'               => (int) $address->id,
					'label'            => $display_label,
					'is_default'       => ( (int) $address->id === (int) $default_id || (int) $address->is_default === 1 ),
					'is_recently_used' => ! empty( $address->is_recently_used ),
					'raw'              => $address,
					'wc_address'       => array(
						'first_name' => $address->first_name ?: '',
						'last_name'  => $address->last_name ?: '',
						'company'    => $address->company ?: '',
						'address_1'  => $address->address_1 ?: '',
						'address_2'  => $address->address_2 ?: '',
						'city'       => $address->city ?: '',
						'state'      => $address->state ?: '',
						'postcode'   => $address->postal_code ?: '',
						'country'    => $address->country ?: '',
						'phone'      => $address->phone ?: '',
						'email'      => $address->email ?: '',
					),
				);
			}
			return $formatted;
		};

		$max_allowed            = (int) get_option( 'sab_max_addresses', 0 );
		$can_add_billing        = SAB_Database::can_add_address( $user_id, 'billing' );
		$can_add_shipping       = SAB_Database::can_add_address( $user_id, 'shipping' );
		$can_add                = $can_add_billing || $can_add_shipping;
		$validate_req           = 'yes' === get_option( 'sab_validate_required_fields', 'yes' );
		$checkout_save          = 'yes' === get_option( 'sab_enable_checkout_save', 'yes' );
		/* translators: %d: maximum allowed billing addresses */
		$limit_message_billing  = sprintf( __( 'You have reached the maximum limit of %d saved billing addresses.', 'smart-address-book' ), $max_allowed );
		/* translators: %d: maximum allowed shipping addresses */
		$limit_message_shipping = sprintf( __( 'You have reached the maximum limit of %d saved shipping addresses.', 'smart-address-book' ), $max_allowed );

			$allowed_billing_countries  = ( function_exists( 'WC' ) && WC()->countries ) ? WC()->countries->get_allowed_countries() : array();
			$allowed_shipping_countries = ( function_exists( 'WC' ) && WC()->countries ) ? ( WC()->countries->get_shipping_countries() ?: $allowed_billing_countries ) : array();

			$base_country             = ( function_exists( 'WC' ) && WC()->countries ) ? WC()->countries->get_base_country() : 'US';
			$default_billing_country  = ( function_exists( 'WC' ) && WC()->customer && WC()->customer->get_billing_country() ) ? WC()->customer->get_billing_country() : $base_country;
			$default_shipping_country = ( function_exists( 'WC' ) && WC()->customer && WC()->customer->get_shipping_country() ) ? WC()->customer->get_shipping_country() : $base_country;

			return array(
				'is_logged_in'             => true,
				'billing_addresses'        => $format_address_list( $billing_addresses, $default_billing ? $default_billing->id : 0 ),
				'shipping_addresses'       => $format_address_list( $shipping_addresses, $default_shipping ? $default_shipping->id : 0 ),
				'default_billing_id'       => $default_billing ? (int) $default_billing->id : 0,
				'default_shipping_id'      => $default_shipping ? (int) $default_shipping->id : 0,
				'enable_billing'           => 'yes' === get_option( 'sab_enable_billing', 'yes' ),
				'enable_shipping'          => 'yes' === get_option( 'sab_enable_shipping', 'yes' ),
				'max_addresses'            => $max_allowed,
				'can_add_address'          => $can_add,
				'can_add_billing'          => $can_add_billing,
				'can_add_shipping'         => $can_add_shipping,
				'validate_required_fields' => $validate_req,
				'enable_checkout_save'     => $checkout_save,
				/* translators: %d: maximum allowed addresses */
				'limit_message'            => sprintf( __( 'You have reached the maximum limit of %d saved addresses.', 'smart-address-book' ), $max_allowed ),
				'limit_message_billing'    => $limit_message_billing,
				'limit_message_shipping'   => $limit_message_shipping,
				'ajax_url'                 => admin_url( 'admin-ajax.php' ),
				'nonce'                    => wp_create_nonce( 'sab_checkout_nonce' ),
				'countries'                => $allowed_billing_countries,
				'billing_countries'        => $allowed_billing_countries,
				'shipping_countries'       => $allowed_shipping_countries,
				'default_billing_country'  => $default_billing_country,
				'default_shipping_country' => $default_shipping_country,
				'states'                   => ( function_exists( 'WC' ) && WC()->countries ) ? WC()->countries->get_states() : array(),
			'i18n'                => array(
				'select_billing'   => __( 'Select a saved billing address:', 'smart-address-book' ),
				'select_shipping'  => __( 'Select a saved shipping address:', 'smart-address-book' ),
				'add_new_address'  => __( '+ Add New Address', 'smart-address-book' ),
				'add_new_billing'  => __( '+ Add New Billing Address', 'smart-address-book' ),
				'add_new_shipping' => __( '+ Add New Shipping Address', 'smart-address-book' ),
				'enter_new'        => __( '+ Enter a new address', 'smart-address-book' ),
				'default_badge'   => __( 'Default', 'smart-address-book' ),
				'recent_badge'    => __( 'Recently Used', 'smart-address-book' ),
				'saving'          => __( 'Saving...', 'smart-address-book' ),
				'save_success'    => __( 'Address saved and selected!', 'smart-address-book' ),
				'error'           => __( 'An error occurred. Please try again.', 'smart-address-book' ),
				'edit'            => __( 'Edit', 'smart-address-book' ),
				'save_changes'    => __( 'Save Changes', 'smart-address-book' ),
				'cancel'          => __( 'Cancel', 'smart-address-book' ),
			),
		);
	}

	/**
	 * Callback for Store API checkout schema.
	 */
	public function get_store_api_schema() {
		return array(
			'billing_address_id'  => array(
				'description' => __( 'Selected Smart Address Book billing address ID', 'smart-address-book' ),
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
			),
			'shipping_address_id' => array(
				'description' => __( 'Selected Smart Address Book shipping address ID', 'smart-address-book' ),
				'type'        => 'integer',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => false,
			),
		);
	}

	/**
	 * Callback for Store API checkout data.
	 */
	public function get_store_api_data() {
		$user_id          = get_current_user_id();
		$enable_billing   = 'yes' === get_option( 'sab_enable_billing', 'yes' );
		$enable_shipping  = 'yes' === get_option( 'sab_enable_shipping', 'yes' );
		$default_billing  = ( $user_id && $enable_billing ) ? SAB_Database::get_default_address( $user_id, 'billing' ) : null;
		$default_shipping = ( $user_id && $enable_shipping ) ? SAB_Database::get_default_address( $user_id, 'shipping' ) : null;

		return array(
			'billing_address_id'  => $default_billing ? (int) $default_billing->id : 0,
			'shipping_address_id' => $default_shipping ? (int) $default_shipping->id : 0,
		);
	}

	/**
	 * Update order from Store API checkout request.
	 *
	 * @param \WC_Order        $order Order instance.
	 * @param \WP_REST_Request $request Request instance.
	 */
	public function update_order_from_request( $order, $request ) {
		$extensions = $request->get_param( 'extensions' );
		$sab_data   = $extensions['smart-address-book'] ?? array();

		$customer_id = (int) $order->get_customer_id();
		if ( ! $customer_id ) {
			$customer_id = get_current_user_id();
		}

		if ( $customer_id <= 0 ) {
			return;
		}

		if ( ! empty( $sab_data['billing_address_id'] ) && 'yes' === get_option( 'sab_enable_billing', 'yes' ) ) {
			$billing_id      = absint( $sab_data['billing_address_id'] );
			$billing_address = SAB_Database::get_address( $billing_id );
			if ( $billing_address && (int) $billing_address->user_id === $customer_id && 'billing' === $billing_address->address_type ) {
				$order->update_meta_data( '_sab_billing_address_id', $billing_id );
				SAB_Database::update_last_used( $billing_id );
			}
		}

		if ( ! empty( $sab_data['shipping_address_id'] ) && 'yes' === get_option( 'sab_enable_shipping', 'yes' ) ) {
			$shipping_id      = absint( $sab_data['shipping_address_id'] );
			$shipping_address = SAB_Database::get_address( $shipping_id );
			if ( $shipping_address && (int) $shipping_address->user_id === $customer_id && 'shipping' === $shipping_address->address_type ) {
				$order->update_meta_data( '_sab_shipping_address_id', $shipping_id );
				SAB_Database::update_last_used( $shipping_id );
			}
		}
	}
}
