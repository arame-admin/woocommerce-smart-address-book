<?php
/**
 * Admin Order Address Picker Integration (FR-14).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAB_Admin_Order {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Classic & HPOS Order screen actions
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'render_billing_address_picker' ) );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'render_shipping_address_picker' ) );
	}

	/**
	 * Render saved billing address picker inside order metabox.
	 *
	 * @param WC_Order $order Current order.
	 */
	public function render_billing_address_picker( $order ) {
		$customer_id = $order->get_customer_id();
		if ( ! $customer_id ) {
			return;
		}

		$addresses = SAB_Database::get_addresses_sorted( $customer_id, 'billing' );
		if ( empty( $addresses ) ) {
			return;
		}

		$address_type = 'billing';
		$type         = $address_type;
		include SAB_PLUGIN_DIR . 'admin/views/order-address-picker.php';
	}

	/**
	 * Render saved shipping address picker inside order metabox.
	 *
	 * @param WC_Order $order Current order.
	 */
	public function render_shipping_address_picker( $order ) {
		$customer_id = $order->get_customer_id();
		if ( ! $customer_id ) {
			return;
		}

		$addresses = SAB_Database::get_addresses_sorted( $customer_id, 'shipping' );
		if ( empty( $addresses ) ) {
			return;
		}

		$address_type = 'shipping';
		$type         = $address_type;
		include SAB_PLUGIN_DIR . 'admin/views/order-address-picker.php';
	}
}

