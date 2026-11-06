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
			foreach ( $list as $addr ) {
				$label_parts = array();
				if ( ! empty( $addr->nickname ) ) {
					$label_parts[] = '[' . $addr->nickname . ']';
				}
				$label_parts[] = trim( $addr->first_name . ' ' . $addr->last_name );
				$label_parts[] = $addr->address_1;
				$label_parts[] = $addr->city . ( $addr->postal_code ? ' ' . $addr->postal_code : '' );

				$display_label = implode( ' — ', array_filter( $label_parts ) );
				if ( (int) $addr->id === (int) $default_id || (int) $addr->is_default === 1 ) {
					$display_label .= ' (' . __( 'Default', 'smart-address-book' ) . ')';
				} elseif ( ! empty( $addr->is_recently_used ) ) {
					$display_label .= ' (' . __( 'Recently Used', 'smart-address-book' ) . ')';
				}

				$formatted[] = array(
					'id'               => (int) $addr->id,
					'label'            => $display_label,
					'is_default'       => ( (int) $addr->id === (int) $default_id || (int) $addr->is_default === 1 ),
					'is_recently_used' => ! empty( $addr->is_recently_used ),
					'raw'              => $addr,
					'wc_address'       => array(
						'first_name' => $addr->first_name ?: '',
						'last_name'  => $addr->last_name ?: '',
						'company'    => $addr->company ?: '',
						'address_1'  => $addr->address_1 ?: '',
						'address_2'  => $addr->address_2 ?: '',
						'city'       => $addr->city ?: '',
						'state'      => $addr->state ?: '',
						'postcode'   => $addr->postal_code ?: '',
						'country'    => $addr->country ?: '',
						'phone'      => $addr->phone ?: '',
						'email'      => $addr->email ?: '',
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
		$limit_message_billing  = sprintf( __( 'You have reached the maximum limit of %d saved billing addresses.', 'smart-address-book' ), $max_allowed );
		$limit_message_shipping = sprintf( __( 'You have reached the maximum limit of %d saved shipping addresses.', 'smart-address-book' ), $max_allowed );

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
			'limit_message'            => sprintf( __( 'You have reached the maximum limit of %d saved addresses.', 'smart-address-book' ), $max_allowed ),
			'limit_message_billing'    => $limit_message_billing,
			'limit_message_shipping'   => $limit_message_shipping,
			'ajax_url'                 => admin_url( 'admin-ajax.php' ),
			'nonce'                    => wp_create_nonce( 'sab_checkout_nonce' ),
			'countries'           => ( function_exists( 'WC' ) && WC()->countries ) ? WC()->countries->get_allowed_countries() : array(),
			'states'              => ( function_exists( 'WC' ) && WC()->countries ) ? WC()->countries->get_states() : array(),
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
		$default_billing  = SAB_Database::get_default_address( $user_id, 'billing' );
		$default_shipping = SAB_Database::get_default_address( $user_id, 'shipping' );

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

		if ( ! empty( $sab_data['billing_address_id'] ) ) {
			$billing_id = absint( $sab_data['billing_address_id'] );
			$order->update_meta_data( '_sab_billing_address_id', $billing_id );
			SAB_Database::update_last_used( $billing_id );
		}

		if ( ! empty( $sab_data['shipping_address_id'] ) ) {
			$shipping_id = absint( $sab_data['shipping_address_id'] );
			$order->update_meta_data( '_sab_shipping_address_id', $shipping_id );
			SAB_Database::update_last_used( $shipping_id );
		}
	}
}
