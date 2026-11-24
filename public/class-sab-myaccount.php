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

		SAB_Template_Loader::load(
			'address-book.php',
			compact( 'billing_addresses', 'shipping_addresses', 'enable_billing', 'enable_shipping', 'user_id' ),
			SAB_PLUGIN_DIR . 'public/views/'
		);
	}

	/**
	 * Render the Add/Edit address form view.
	 *
	 * @param int    $user_id User ID.
	 * @param string $action 'add' or 'edit'.
	 */
	private function render_form( $user_id, $action ) {
		$address = null;
		if ( 'add' === $action ) {
			$requested_type = sanitize_text_field( $_GET['type'] ?? '' );
			if ( ! empty( $requested_type ) && in_array( $requested_type, array( 'billing', 'shipping' ), true ) ) {
				if ( ! SAB_Database::can_add_address( $user_id, $requested_type ) ) {
					$this->render_limit_reached( $user_id, $requested_type );
					return;
				}
			} elseif ( ! SAB_Database::can_add_address( $user_id, 'billing' ) && ! SAB_Database::can_add_address( $user_id, 'shipping' ) ) {
				$this->render_limit_reached( $user_id, 'billing' );
				return;
			}
		}

		if ( 'edit' === $action ) {
			$address_id = absint( $_GET['address_id'] ?? 0 );
			$address    = SAB_Database::get_address( $address_id );

			if ( ! $address || (int) $address->user_id !== $user_id ) {
				wc_add_notice( __( 'Address not found or permission denied.', 'smart-address-book' ), 'error' );
				$this->render_list( $user_id );
				return;
			}

			if ( 'yes' !== get_option( 'sab_enable_' . $address->address_type, 'yes' ) ) {
				/* translators: %s: address type */
				wc_add_notice( sprintf( __( '%s address book is currently disabled.', 'smart-address-book' ), ucfirst( $address->address_type ) ), 'error' );
				$this->render_list( $user_id );
				return;
			}
		}

		SAB_Template_Loader::load(
			'address-form.php',
			compact( 'address', 'action', 'user_id' ),
			SAB_PLUGIN_DIR . 'public/views/'
		);
	}

	/**
	 * Render limit reached notification view in My Account.
	 *
	 * @param int    $user_id      User ID.
	 * @param string $address_type 'billing' or 'shipping'.
	 */
	private function render_limit_reached( $user_id, $address_type = 'billing' ) {
		$address_type = in_array( $address_type, array( 'billing', 'shipping' ), true ) ? $address_type : 'billing';
		$back_url     = wc_get_account_endpoint_url( 'sab-address-book' );
		$limit        = function_exists( 'sab_get_address_limit' ) ? sab_get_address_limit( $address_type ) : 3;
		$message      = ( 'billing' === $address_type )
			? sprintf( __( 'You have reached the maximum limit of %d billing addresses. Upgrade to Pro to save unlimited billing addresses.', 'smart-address-book' ), $limit )
			: sprintf( __( 'You have reached the maximum limit of %d shipping addresses. Upgrade to Pro to save unlimited shipping addresses.', 'smart-address-book' ), $limit );
		?>
		<div class="sab-address-form-wrapper sab-limit-reached-wrapper">
			<div class="sab-form-header">
				<h3><?php esc_html_e( 'Address limit reached', 'smart-address-book' ); ?></h3>
				<a href="<?php echo esc_url( $back_url ); ?>" class="button sab-back-link">
					&larr; <?php esc_html_e( 'Back to Address Book', 'smart-address-book' ); ?>
				</a>
			</div>
			<div class="sab-limit-notice">
				<p style="font-size: 15px; margin-bottom: 16px;">
					<?php echo esc_html( $message ); ?>
				</p>
				<button type="button" class="button button-primary sab-pro-upgrade-btn">
					<?php esc_html_e( 'Pro Upgrade – Coming Soon', 'smart-address-book' ); ?>
				</button>
				<div class="sab-pro-coming-soon-notice" style="display:none; margin-top: 16px; padding: 12px 16px; background:#e8f4fc; border-left:4px solid #0073aa; border-radius:4px; font-size:14px; color:#1d2327;">
					<strong><?php esc_html_e( "Pro Upgrade is coming soon! You'll soon be able to upgrade your plan and enjoy unlimited billing and shipping addresses.", 'smart-address-book' ); ?></strong>
				</div>
			</div>
		</div>
		<?php
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

		$user_id      = get_current_user_id();
		$address_id   = absint( $_POST['address_id'] ?? 0 );
		$address_type = sanitize_text_field( $_POST['address_type'] ?? 'shipping' );
		$address_type = in_array( $address_type, array( 'billing', 'shipping' ), true ) ? $address_type : 'shipping';

		if ( 'yes' !== get_option( 'sab_enable_' . $address_type, 'yes' ) ) {
			/* translators: %s: address type */
			wc_add_notice( sprintf( __( '%s address book is currently disabled.', 'smart-address-book' ), ucfirst( $address_type ) ), 'error' );
			return;
		}

		// Check limit if adding
		if ( ! $address_id ) {
			$max_allowed = function_exists( 'sab_get_address_limit' ) ? sab_get_address_limit( $address_type ) : 3;
			if ( $max_allowed > 0 && ! SAB_Database::can_add_address( $user_id, $address_type ) ) {
				$msg = ( 'billing' === $address_type )
					? sprintf( __( 'You have reached the maximum limit of %d billing addresses. Upgrade to Pro to save unlimited billing addresses.', 'smart-address-book' ), $max_allowed )
					: sprintf( __( 'You have reached the maximum limit of %d shipping addresses. Upgrade to Pro to save unlimited shipping addresses.', 'smart-address-book' ), $max_allowed );
				wc_add_notice( $msg, 'error' );
				return;
			}
		}

		$sanitized            = SAB_Address::sanitize( $_POST );
		$sanitized['user_id'] = $user_id;

		$validation = SAB_Address::validate_address( $sanitized );
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
	 * Helper: Locate template supporting theme overrides (delegates to SAB_Template_Loader).
	 *
	 * @param string $template_name Template file name.
	 * @return string File path.
	 */
	public function locate_template( $template_name ) {
		return SAB_Template_Loader::locate( $template_name, SAB_PLUGIN_DIR . 'public/views/' );
	}
}

