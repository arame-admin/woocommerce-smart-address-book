<?php
/**
 * AJAX handlers for Smart Address Book.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAB_Ajax {

	/**
	 * Constructor: Register all AJAX hooks.
	 */
	public function __construct() {
		// Public & Member AJAX endpoints
		add_action( 'wp_ajax_sab_add_address', array( $this, 'ajax_add_address' ) );
		add_action( 'wp_ajax_sab_update_address', array( $this, 'ajax_update_address' ) );
		add_action( 'wp_ajax_sab_delete_address', array( $this, 'ajax_delete_address' ) );
		add_action( 'wp_ajax_sab_set_default', array( $this, 'ajax_set_default' ) );
		add_action( 'wp_ajax_sab_get_address', array( $this, 'ajax_get_address' ) );
		add_action( 'wp_ajax_sab_search_addresses', array( $this, 'ajax_search_addresses' ) );
		add_action( 'wp_ajax_sab_get_checkout_address', array( $this, 'ajax_get_checkout_address' ) );
		add_action( 'wp_ajax_sab_save_checkout_address', array( $this, 'ajax_save_checkout_address' ) );
	}

	/**
	 * Check common permissions and verify nonce.
	 *
	 * @param string $action Nonce action name.
	 * @return int User ID.
	 */
	private function verify_request( $action = 'sab_nonce' ) {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to perform this action.', 'smart-address-book' ) ), 401 );
		}

		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ?? ( $_REQUEST['_ajax_nonce'] ?? '' ) ) );
		if ( ! wp_verify_nonce( $nonce, $action ) && ! wp_verify_nonce( $nonce, 'sab_admin_nonce' ) && ! wp_verify_nonce( $nonce, 'sab_checkout_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please refresh the page.', 'smart-address-book' ) ), 403 );
		}

		return get_current_user_id();
	}

	/**
	 * Add a new address via AJAX.
	 */
	public function ajax_add_address() {
		$current_user_id = $this->verify_request();

		// Check if admin is adding for another user
		$target_user_id = $current_user_id;
		if ( isset( $_POST['user_id'] ) && current_user_can( 'manage_woocommerce' ) ) {
			$target_user_id = absint( $_POST['user_id'] );
		}

		// Check address limits for this specific type
		$type = sanitize_text_field( $_POST['address_type'] ?? 'shipping' );
		$type = in_array( $type, array( 'billing', 'shipping' ), true ) ? $type : 'shipping';

		$max_allowed = (int) get_option( 'sab_max_addresses', 0 );
		if ( $max_allowed > 0 && ! SAB_Database::can_add_address( $target_user_id, $type ) ) {
			/* translators: 1: maximum allowed addresses, 2: address type */
			wp_send_json_error( array( 'message' => sprintf( __( 'You have reached the maximum limit of %1$d saved %2$s addresses.', 'smart-address-book' ), $max_allowed, $type ) ) );
			return;
		}

		$raw_data = $_POST;
		$raw_data['user_id'] = $target_user_id;

		$sanitized = SAB_Address::sanitize( $raw_data );
		$validation = SAB_Address::validate( $sanitized );

		if ( is_wp_error( $validation ) ) {
			$errors_map = array();
			foreach ( $validation->get_error_codes() as $code ) {
				$field = preg_replace( '/^(empty_|invalid_)/', '', $code );
				if ( 'postcode' === $field ) {
					$field = 'postal_code';
				}
				$errors_map[ $field ] = $validation->get_error_message( $code );
			}
			wp_send_json_error(
				array(
					'message' => $validation->get_error_message(),
					'errors'  => $errors_map,
				)
			);
			return;
		}

		$new_id = SAB_Database::add_address( $sanitized );
		if ( ! $new_id ) {
			wp_send_json_error( array( 'message' => __( 'Could not save address. Please try again.', 'smart-address-book' ) ) );
		}

		$created = SAB_Database::get_address( $new_id );
		wp_send_json_success(
			array(
				'message'    => __( 'Address saved successfully!', 'smart-address-book' ),
				'address_id' => $new_id,
				'address'    => $created,
				'formatted'  => SAB_Address::format_address( $created ),
			)
		);
	}

	/**
	 * Update an existing address via AJAX.
	 */
	public function ajax_update_address() {
		$current_user_id = $this->verify_request();

		$address_id = absint( $_POST['address_id'] ?? ( $_POST['id'] ?? 0 ) );
		if ( ! $address_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid address ID.', 'smart-address-book' ) ) );
		}

		$existing = SAB_Database::get_address( $address_id );
		if ( ! $existing ) {
			wp_send_json_error( array( 'message' => __( 'Address not found.', 'smart-address-book' ) ) );
		}

		// Check permission
		if ( (int) $existing->user_id !== $current_user_id && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to edit this address.', 'smart-address-book' ) ), 403 );
		}

		$sanitized = SAB_Address::sanitize( $_POST );
		$sanitized['user_id'] = (int) $existing->user_id; // Preserve owner

		$validation = SAB_Address::validate( $sanitized );
		if ( is_wp_error( $validation ) ) {
			$errors_map = array();
			foreach ( $validation->get_error_codes() as $code ) {
				$field = preg_replace( '/^(empty_|invalid_)/', '', $code );
				if ( 'postcode' === $field ) {
					$field = 'postal_code';
				}
				$errors_map[ $field ] = $validation->get_error_message( $code );
			}
			wp_send_json_error(
				array(
					'message' => $validation->get_error_message(),
					'errors'  => $errors_map,
				)
			);
			return;
		}

		$updated = SAB_Database::update_address( $address_id, $sanitized );
		if ( ! $updated ) {
			wp_send_json_error( array( 'message' => __( 'Could not update address.', 'smart-address-book' ) ) );
		}

		$refreshed = SAB_Database::get_address( $address_id );
		wp_send_json_success(
			array(
				'message'   => __( 'Address updated successfully!', 'smart-address-book' ),
				'address'   => $refreshed,
				'formatted' => SAB_Address::format_address( $refreshed ),
			)
		);
	}

	/**
	 * Delete an address via AJAX.
	 */
	public function ajax_delete_address() {
		$current_user_id = $this->verify_request();

		$address_id = absint( $_POST['address_id'] ?? ( $_POST['id'] ?? 0 ) );
		if ( ! $address_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid address ID.', 'smart-address-book' ) ) );
		}

		$existing = SAB_Database::get_address( $address_id );
		if ( ! $existing ) {
			wp_send_json_error( array( 'message' => __( 'Address not found.', 'smart-address-book' ) ) );
		}

		if ( (int) $existing->user_id !== $current_user_id && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to delete this address.', 'smart-address-book' ) ), 403 );
		}

		$was_default  = ( (int) $existing->is_default === 1 );
		$address_type = $existing->address_type;

		$deleted = SAB_Database::delete_address( $address_id );
		if ( ! $deleted ) {
			wp_send_json_error( array( 'message' => __( 'Could not delete address.', 'smart-address-book' ) ) );
		}

		$new_default_id = (int) SAB_Database::$last_new_default_id;

		wp_send_json_success(
			array(
				'message'        => __( 'Address deleted successfully.', 'smart-address-book' ),
				'was_default'    => $was_default,
				'new_default_id' => $new_default_id,
				'address_type'   => $address_type,
			)
		);
	}

	/**
	 * Set address as default via AJAX.
	 */
	public function ajax_set_default() {
		$current_user_id = $this->verify_request();

		$address_id = absint( $_POST['address_id'] ?? ( $_POST['id'] ?? 0 ) );
		$type       = sanitize_text_field( $_POST['address_type'] ?? ( $_POST['type'] ?? 'shipping' ) );

		if ( ! $address_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid address ID.', 'smart-address-book' ) ) );
		}

		$existing = SAB_Database::get_address( $address_id );
		if ( ! $existing ) {
			wp_send_json_error( array( 'message' => __( 'Address not found.', 'smart-address-book' ) ) );
		}

		$target_user_id = (int) $existing->user_id;
		if ( $target_user_id !== $current_user_id && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'smart-address-book' ) ), 403 );
		}

		$type = in_array( $type, array( 'billing', 'shipping' ), true ) ? $type : $existing->address_type;
		$ok   = SAB_Database::set_default( $address_id, $target_user_id, $type );

		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => __( 'Could not set default address.', 'smart-address-book' ) ) );
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Default address updated.', 'smart-address-book' ),
				'address_id' => $address_id,
				'type'       => $type,
			)
		);
	}

	/**
	 * Get address data for editing or populating.
	 */
	public function ajax_get_address() {
		$current_user_id = $this->verify_request();

		$address_id = absint( $_GET['address_id'] ?? ( $_GET['id'] ?? 0 ) );
		if ( ! $address_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid address ID.', 'smart-address-book' ) ) );
		}

		$address = SAB_Database::get_address( $address_id );
		if ( ! $address ) {
			wp_send_json_error( array( 'message' => __( 'Address not found.', 'smart-address-book' ) ) );
		}

		if ( (int) $address->user_id !== $current_user_id && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'smart-address-book' ) ), 403 );
		}

		wp_send_json_success( array( 'address' => $address ) );
	}

	/**
	 * FR-09: Search addresses via AJAX.
	 */
	public function ajax_search_addresses() {
		$current_user_id = $this->verify_request();

		$term = sanitize_text_field( $_GET['term'] ?? '' );
		$type = sanitize_text_field( $_GET['type'] ?? '' );

		$results = SAB_Database::search_addresses( $current_user_id, $term, $type );

		$formatted = array();
		foreach ( $results as $addr ) {
			$formatted[] = array(
				'id'         => $addr->id,
				'nickname'   => $addr->nickname,
				'type'       => $addr->address_type,
				'is_default' => (int) $addr->is_default,
				'formatted'  => SAB_Address::format_address( $addr ),
				'raw'        => $addr,
			);
		}

		wp_send_json_success( array( 'addresses' => $formatted ) );
	}

	/**
	 * FR-07: Get address formatted for checkout auto-population.
	 */
	public function ajax_get_checkout_address() {
		$current_user_id = $this->verify_request( 'sab_checkout_nonce' );

		$address_id = absint( $_GET['address_id'] ?? 0 );
		$type       = sanitize_text_field( $_GET['type'] ?? 'billing' );

		if ( ! $address_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid address ID.', 'smart-address-book' ) ) );
		}

		$address = SAB_Database::get_address( $address_id );
		if ( ! $address || (int) $address->user_id !== $current_user_id ) {
			wp_send_json_error( array( 'message' => __( 'Address not found.', 'smart-address-book' ) ) );
		}

		$prefix = ( 'shipping' === $type ) ? 'shipping_' : 'billing_';

		$fields = array(
			$prefix . 'first_name' => $address->first_name,
			$prefix . 'last_name'  => $address->last_name,
			$prefix . 'company'    => $address->company,
			$prefix . 'address_1'  => $address->address_1,
			$prefix . 'address_2'  => $address->address_2,
			$prefix . 'city'       => $address->city,
			$prefix . 'state'      => $address->state,
			$prefix . 'postcode'   => $address->postal_code,
			$prefix . 'country'    => $address->country,
			$prefix . 'phone'      => $address->phone,
			$prefix . 'email'      => $address->email,
		);

		wp_send_json_success( array( 'fields' => $fields ) );
	}

	/**
	 * FR-08: Save a new address directly from checkout without leaving.
	 */
	public function ajax_save_checkout_address() {
		$current_user_id = $this->verify_request( 'sab_checkout_nonce' );

		// Check if saving at checkout is enabled
		if ( 'yes' !== get_option( 'sab_enable_checkout_save', 'yes' ) ) {
			wp_send_json_error( array( 'message' => __( 'Saving addresses at checkout is disabled.', 'smart-address-book' ) ) );
			return;
		}

		$type = sanitize_text_field( $_POST['address_type'] ?? 'shipping' );
		$type = in_array( $type, array( 'billing', 'shipping' ), true ) ? $type : 'shipping';

		// Check address limits for this specific type
		$max_allowed = (int) get_option( 'sab_max_addresses', 0 );
		if ( $max_allowed > 0 && ! SAB_Database::can_add_address( $current_user_id, $type ) ) {
			/* translators: 1: maximum allowed addresses, 2: address type */
			wp_send_json_error( array( 'message' => sprintf( __( 'You have reached the maximum limit of %1$d saved %2$s addresses.', 'smart-address-book' ), $max_allowed, $type ) ) );
			return;
		}

		$prefix = ( 'billing' === $type ) ? 'billing_' : 'shipping_';

		// Map checkout fields to SAB address fields
		$address_data = array(
			'user_id'      => $current_user_id,
			'address_type' => $type,
			'nickname'     => sanitize_text_field( $_POST['nickname'] ?? ( $_POST[ $prefix . 'nickname' ] ?? '' ) ),
			'first_name'   => sanitize_text_field( $_POST[ $prefix . 'first_name' ] ?? ( $_POST['first_name'] ?? '' ) ),
			'last_name'    => sanitize_text_field( $_POST[ $prefix . 'last_name' ] ?? ( $_POST['last_name'] ?? '' ) ),
			'company'      => sanitize_text_field( $_POST[ $prefix . 'company' ] ?? ( $_POST['company'] ?? '' ) ),
			'phone'        => sanitize_text_field( $_POST[ $prefix . 'phone' ] ?? ( $_POST['phone'] ?? '' ) ),
			'email'        => sanitize_email( $_POST[ $prefix . 'email' ] ?? ( $_POST['email'] ?? ( $_POST['billing_email'] ?? ( wp_get_current_user()->user_email ?? '' ) ) ) ),
			'address_1'    => sanitize_text_field( $_POST[ $prefix . 'address_1' ] ?? ( $_POST['address_1'] ?? '' ) ),
			'address_2'    => sanitize_text_field( $_POST[ $prefix . 'address_2' ] ?? ( $_POST['address_2'] ?? '' ) ),
			'city'         => sanitize_text_field( $_POST[ $prefix . 'city' ] ?? ( $_POST['city'] ?? '' ) ),
			'state'        => sanitize_text_field( $_POST[ $prefix . 'state' ] ?? ( $_POST['state'] ?? '' ) ),
			'country'      => sanitize_text_field( $_POST[ $prefix . 'country' ] ?? ( $_POST['country'] ?? '' ) ),
			'postal_code'  => sanitize_text_field( $_POST[ $prefix . 'postcode' ] ?? ( $_POST[ $prefix . 'postal_code' ] ?? ( $_POST['postal_code'] ?? ( $_POST['postcode'] ?? '' ) ) ) ),
		);

		// If no nickname provided, generate default
		if ( empty( $address_data['nickname'] ) ) {
			$count                    = SAB_Database::get_address_count_by_type( $current_user_id, $type ) + 1;
			$address_data['nickname'] = sprintf( '%s #%d', ucfirst( $type ), $count );
		}

		if ( isset( $_POST['is_default'] ) ) {
			$address_data['is_default'] = ! empty( $_POST['is_default'] ) ? 1 : 0;
		}

		$validation = SAB_Address::validate( $address_data );
		if ( is_wp_error( $validation ) ) {
			$errors_map = array();
			foreach ( $validation->get_error_codes() as $code ) {
				$field = preg_replace( '/^(empty_|invalid_)/', '', $code );
				if ( 'postcode' === $field ) {
					$field = 'postal_code';
				}
				$errors_map[ $field ] = $validation->get_error_message( $code );
			}
			wp_send_json_error(
				array(
					'message' => $validation->get_error_message(),
					'errors'  => $errors_map,
				)
			);
			return;
		}

		// Avoid duplicate address records
		$existing_addresses = SAB_Database::get_addresses( $current_user_id, $type, 'id' );
		foreach ( $existing_addresses as $existing ) {
			if (
				strtolower( trim( (string) $existing->address_1 ) ) === strtolower( trim( (string) $address_data['address_1'] ) ) &&
				strtolower( trim( (string) $existing->postal_code ) ) === strtolower( trim( (string) $address_data['postal_code'] ) ) &&
				strtolower( trim( (string) $existing->city ) ) === strtolower( trim( (string) $address_data['city'] ) ) &&
				strtolower( trim( (string) $existing->first_name ) ) === strtolower( trim( (string) $address_data['first_name'] ) ) &&
				strtolower( trim( (string) $existing->last_name ) ) === strtolower( trim( (string) $address_data['last_name'] ) )
			) {
				$label_parts = array();
				if ( ! empty( $existing->nickname ) ) {
					$label_parts[] = '[' . $existing->nickname . ']';
				}
				$label_parts[] = trim( $existing->first_name . ' ' . $existing->last_name );
				$label_parts[] = $existing->address_1;
				$label_parts[] = $existing->city . ( $existing->postal_code ? ' ' . $existing->postal_code : '' );

				$display_label = implode( ' — ', array_filter( $label_parts ) );
				if ( (int) $existing->is_default === 1 ) {
					$display_label .= ' (' . __( 'Default', 'smart-address-book' ) . ')';
				}

				wp_send_json_success(
					array(
						'message'    => __( 'Address already exists in your address book and has been selected.', 'smart-address-book' ),
						'address_id' => (int) $existing->id,
						'address'    => $existing,
						'nickname'   => $existing->nickname,
						'label'      => $display_label,
						'is_default' => (int) $existing->is_default,
					)
				);
				return;
			}
		}

		$new_id = SAB_Database::add_address( $address_data );
		if ( ! $new_id ) {
			wp_send_json_error( array( 'message' => __( 'Could not save address.', 'smart-address-book' ) ) );
			return;
		}

		$created = SAB_Database::get_address( $new_id );

		$label_parts = array();
		if ( ! empty( $created->nickname ) ) {
			$label_parts[] = '[' . $created->nickname . ']';
		}
		$label_parts[] = trim( $created->first_name . ' ' . $created->last_name );
		$label_parts[] = $created->address_1;
		$label_parts[] = $created->city . ( $created->postal_code ? ' ' . $created->postal_code : '' );

		$display_label = implode( ' — ', array_filter( $label_parts ) );
		if ( (int) $created->is_default === 1 ) {
			$display_label .= ' (' . __( 'Default', 'smart-address-book' ) . ')';
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Address saved successfully!', 'smart-address-book' ),
				'address_id' => $new_id,
				'address'    => $created,
				'nickname'   => $created->nickname,
				'label'      => $display_label,
				'is_default' => (int) $created->is_default,
			)
		);
		return;
	}
}

