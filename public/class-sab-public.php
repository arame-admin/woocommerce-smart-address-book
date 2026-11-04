<?php
/**
 * Public facing controller for Smart Address Book.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAB_Public {

	/**
	 * My Account controller instance.
	 *
	 * @var SAB_MyAccount|null
	 */
	public $myaccount = null;

	/**
	 * Checkout controller instance.
	 *
	 * @var SAB_Checkout|null
	 */
	public $checkout = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->myaccount = new SAB_MyAccount();
		$this->checkout  = new SAB_Checkout();

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue scripts and styles on My Account and Checkout pages.
	 */
	public function enqueue_scripts() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$is_checkout = is_checkout();
		$is_account  = is_account_page();

		if ( ! $is_checkout && ! $is_account ) {
			return;
		}

		// Public Stylesheet
		wp_enqueue_style(
			'sab-public',
			SAB_PLUGIN_URL . 'public/css/sab-public.css',
			array(),
			SAB_VERSION
		);

		// My Account specific script
		if ( $is_account ) {
			wp_enqueue_script(
				'sab-public',
				SAB_PLUGIN_URL . 'public/js/sab-public.js',
				array( 'jquery' ),
				SAB_VERSION,
				true
			);

			wp_localize_script(
				'sab-public',
				'sab_params',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'sab_nonce' ),
					'states'   => ( function_exists( 'WC' ) && WC()->countries ) ? WC()->countries->get_states() : array(),
					'i18n'     => array(
						'confirm_delete'    => __( 'Are you sure you want to delete this address?', 'smart-address-book' ),
						'deleting'          => __( 'Deleting...', 'smart-address-book' ),
						'saving'            => __( 'Saving...', 'smart-address-book' ),
						'error'             => __( 'An error occurred. Please try again.', 'smart-address-book' ),
						'select_state_text' => __( 'Select an option&hellip;', 'woocommerce' ),
					),
				)
			);
		}

		// Checkout specific script
		if ( $is_checkout ) {
			wp_enqueue_script(
				'sab-checkout',
				SAB_PLUGIN_URL . 'public/js/sab-checkout.js',
				array( 'jquery' ),
				SAB_VERSION,
				true
			);

			$user_id          = get_current_user_id();
			$default_billing  = SAB_Database::get_default_address( $user_id, 'billing' );
			$default_shipping = SAB_Database::get_default_address( $user_id, 'shipping' );

			$billing_addresses  = SAB_Database::get_addresses_sorted( $user_id, 'billing' );
			$shipping_addresses = SAB_Database::get_addresses_sorted( $user_id, 'shipping' );

			$format_addr_fn = function( $list, $default_id ) {
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

			$checkout_data = array(
				'is_logged_in'        => true,
				'ajax_url'            => admin_url( 'admin-ajax.php' ),
				'nonce'               => wp_create_nonce( 'sab_checkout_nonce' ),
				'states'              => ( function_exists( 'WC' ) && WC()->countries ) ? WC()->countries->get_states() : array(),
				'countries'           => ( function_exists( 'WC' ) && WC()->countries ) ? WC()->countries->get_allowed_countries() : array(),
				'billing_addresses'   => $format_addr_fn( $billing_addresses, $default_billing ? $default_billing->id : 0 ),
				'shipping_addresses'  => $format_addr_fn( $shipping_addresses, $default_shipping ? $default_shipping->id : 0 ),
				'default_billing_id'  => $default_billing ? (int) $default_billing->id : 0,
				'default_shipping_id' => $default_shipping ? (int) $default_shipping->id : 0,
				'enable_billing'      => 'yes' === get_option( 'sab_enable_billing', 'yes' ),
				'enable_shipping'     => 'yes' === get_option( 'sab_enable_shipping', 'yes' ),
				'i18n'                => array(
					'select_billing'    => __( 'Select a saved billing address:', 'smart-address-book' ),
					'select_shipping'   => __( 'Select a saved shipping address:', 'smart-address-book' ),
					'add_new_address'   => __( '+ Add New Address', 'smart-address-book' ),
					'add_new_billing'   => __( '+ Add New Billing Address', 'smart-address-book' ),
					'add_new_shipping'  => __( '+ Add New Shipping Address', 'smart-address-book' ),
					'enter_new'         => __( '+ Enter a new address', 'smart-address-book' ),
					'loading'           => __( 'Loading address...', 'smart-address-book' ),
					'save_success'      => __( 'Address saved!', 'smart-address-book' ),
					'error'             => __( 'Could not load address.', 'smart-address-book' ),
					'saving'            => __( 'Saving...', 'smart-address-book' ),
					'select_state_text' => __( 'Select an option&hellip;', 'woocommerce' ),
					'edit'              => __( 'Edit', 'smart-address-book' ),
					'save_changes'      => __( 'Save Changes', 'smart-address-book' ),
					'cancel'            => __( 'Cancel', 'smart-address-book' ),
				),
			);

			wp_localize_script( 'sab-checkout', 'sab_checkout_params', $checkout_data );

			// Also register and localize sab-blocks-checkout for Checkout Blocks
			wp_enqueue_script(
				'sab-blocks-checkout',
				SAB_PLUGIN_URL . 'public/js/sab-blocks-checkout.js',
				array( 'jquery' ),
				SAB_VERSION,
				true
			);
			wp_localize_script( 'sab-blocks-checkout', 'sab_blocks_checkout_params', $checkout_data );
		}
	}
}

