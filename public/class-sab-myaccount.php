<?php
/**
 * Customer My Account Dashboard integration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAB_MyAccount {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_account_sab-address-book_endpoint', array( $this, 'render_address_book' ) );
		add_action( 'template_redirect', array( $this, 'handle_form_submit' ) );
	}

	/**
	 * Main render handler for My Account > Address Book endpoint.
	 */
	public function render_address_book() {
		if ( ! is_user_logged_in() ) {
			echo '<p>' . esc_html__( 'Please log in to view your address book.', 'smart-address-book' ) . '</p>';
			return;
		}

		$user_id = get_current_user_id();
		$action  = sanitize_text_field( $_GET['action'] ?? '' );

		if ( 'add' === $action || 'edit' === $action ) {
			$this->render_form( $user_id, $action );
		} else {
			$this->render_list( $user_id );
		}
	}

	/**
	 * Render the address list view.
	 *
	 * @param int $user_id User ID.
	 */
	private function render_list( $user_id ) {
		$enable_billing  = 'yes' === get_option( 'sab_enable_billing', 'yes' );
		$enable_shipping = 'yes' === get_option( 'sab_enable_shipping', 'yes' );

		$billing_addresses  = $enable_billing ? SAB_Database::get_addresses_sorted( $user_id, 'billing' ) : array();
		$shipping_addresses = $enable_shipping ? SAB_Database::get_addresses_sorted( $user_id, 'shipping' ) : array();

		$template_path = $this->locate_template( 'address-book.php' );
		include $template_path;
	}

	/**
	 * Render the Add/Edit address form view.
	 *
	 * @param int    $user_id User ID.
	 * @param string $action 'add' or 'edit'.
	 */
	private function render_form( $user_id, $action ) {
		$address = null;
		if ( 'edit' === $action ) {
			$address_id = absint( $_GET['address_id'] ?? 0 );
			$address    = SAB_Database::get_address( $address_id );

			if ( ! $address || (int) $address->user_id !== $user_id ) {
				wc_add_notice( __( 'Address not found or permission denied.', 'smart-address-book' ), 'error' );
				$this->render_list( $user_id );
				return;
			}
		}

		$template_path = $this->locate_template( 'address-form.php' );
		include $template_path;
	}

	/**
	 * Handle standard POST form submission (fallback for non-AJAX submissions).
	 */
	public function handle_form_submit() {
		if ( ! isset( $_POST['sab_save_address'] ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['sab_nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'sab_address_form' ) ) {
			wc_add_notice( __( 'Security verification failed. Please try again.', 'smart-address-book' ), 'error' );
			return;
		}

		$user_id    = get_current_user_id();
		$address_id = absint( $_POST['address_id'] ?? 0 );

		// Check limit if adding
		if ( ! $address_id ) {
			$type = sanitize_text_field( $_POST['address_type'] ?? 'shipping' );
			$type = in_array( $type, array( 'billing', 'shipping' ), true ) ? $type : 'shipping';

			$max_allowed = (int) get_option( 'sab_max_addresses', 0 );
			if ( $max_allowed > 0 && ! SAB_Database::can_add_address( $user_id, $type ) ) {
				/* translators: 1: maximum allowed addresses, 2: address type */
				wc_add_notice( sprintf( __( 'You have reached the maximum limit of %1$d saved %2$s addresses.', 'smart-address-book' ), $max_allowed, $type ), 'error' );
				return;
			}
		}

		$sanitized            = SAB_Address::sanitize( $_POST );
		$sanitized['user_id'] = $user_id;

		$validation = SAB_Address::validate( $sanitized );
		if ( is_wp_error( $validation ) ) {
			foreach ( $validation->get_error_messages() as $err ) {
				wc_add_notice( $err, 'error' );
			}
			return;
		}

		if ( $address_id > 0 ) {
			$existing = SAB_Database::get_address( $address_id );
			if ( ! $existing || (int) $existing->user_id !== $user_id ) {
				wc_add_notice( __( 'Permission denied.', 'smart-address-book' ), 'error' );
				return;
			}
			$success = SAB_Database::update_address( $address_id, $sanitized );
			$msg     = __( 'Address updated successfully.', 'smart-address-book' );
		} else {
			$success = SAB_Database::add_address( $sanitized );
			$msg     = __( 'Address saved successfully.', 'smart-address-book' );
		}

		if ( $success ) {
			wc_add_notice( $msg, 'success' );
			wp_safe_redirect( wc_get_account_endpoint_url( 'sab-address-book' ) );
			exit;
		} else {
			wc_add_notice( __( 'Could not save address. Please try again.', 'smart-address-book' ), 'error' );
		}
	}

	/**
	 * Helper: Locate template supporting theme overrides.
	 *
	 * @param string $template_name Template file name.
	 * @return string File path.
	 */
	public function locate_template( $template_name ) {
		// Look in theme: /woocommerce/smart-address-book/$template_name
		$theme_path = locate_template(
			array(
				'woocommerce/smart-address-book/' . $template_name,
				'woocommerce/' . $template_name,
			)
		);

		if ( $theme_path ) {
			return $theme_path;
		}

		// Fallback to plugin views
		return SAB_PLUGIN_DIR . 'public/views/' . $template_name;
	}
}

