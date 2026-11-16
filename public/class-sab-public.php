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
		add_action( 'wp_footer', array( $this, 'render_pro_limit_modal' ) );
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
		$public_css_ver = defined( 'SAB_VERSION' ) ? SAB_VERSION . '.' . filemtime( SAB_PLUGIN_DIR . 'public/css/sab-public.css' ) : time();
		wp_enqueue_style(
			'sab-public',
			SAB_PLUGIN_URL . 'public/css/sab-public.css',
			array(),
			$public_css_ver
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

			$user_id          = get_current_user_id();
			$max_allowed      = function_exists( 'sab_get_address_limit' ) ? sab_get_address_limit() : 3;
			$can_add_billing  = SAB_Database::can_add_address( $user_id, 'billing' );
			$can_add_shipping = SAB_Database::can_add_address( $user_id, 'shipping' );
			$can_add          = $can_add_billing || $can_add_shipping;
			$validate_req     = 'yes' === get_option( 'sab_validate_required_fields', 'yes' );
			$is_pro           = function_exists( 'sab_is_pro_active' ) ? sab_is_pro_active() : false;

			wp_localize_script(
				'sab-public',
				'sab_params',
				array(
					'ajax_url'                 => admin_url( 'admin-ajax.php' ),
					'nonce'                    => wp_create_nonce( 'sab_nonce' ),
					'states'                   => ( function_exists( 'WC' ) && WC()->countries ) ? WC()->countries->get_states() : array(),
					'max_addresses'            => $max_allowed,
					'can_add_address'          => $can_add,
					'can_add_billing'          => $can_add_billing,
					'can_add_shipping'         => $can_add_shipping,
					'is_pro'                   => $is_pro,
					'validate_required_fields' => $validate_req,
					'add_url'                  => add_query_arg( 'action', 'add', wc_get_account_endpoint_url( 'sab-address-book' ) ),
					'limit_title'              => __( 'Address limit reached', 'smart-address-book' ),
					'limit_message'            => __( 'You have reached the maximum limit of saved addresses. Upgrade to Pro to save unlimited addresses.', 'smart-address-book' ),
					'limit_message_billing'    => __( 'You have reached the maximum limit of 3 billing addresses. Upgrade to Pro to save unlimited billing addresses.', 'smart-address-book' ),
					'limit_message_shipping'   => __( 'You have reached the maximum limit of 3 shipping addresses. Upgrade to Pro to save unlimited shipping addresses.', 'smart-address-book' ),
					'pro_button_text'          => __( 'Pro Upgrade – Coming Soon', 'smart-address-book' ),
					'pro_coming_soon_notice'   => __( "Pro Upgrade is coming soon! You'll soon be able to upgrade your plan and enjoy unlimited billing and shipping addresses.", 'smart-address-book' ),
					'i18n'     => array(
						'confirm_delete'    => __( 'Are you sure you want to delete this address?', 'smart-address-book' ),
						'deleting'          => __( 'Deleting...', 'smart-address-book' ),
						'saving'            => __( 'Saving...', 'smart-address-book' ),
						'error'             => __( 'An error occurred. Please try again.', 'smart-address-book' ),
						'select_state_text' => __( 'Select an option&hellip;', 'woocommerce' ),
						'default_billing'   => __( 'Default Billing', 'smart-address-book' ),
						'default_shipping'  => __( 'Default Shipping', 'smart-address-book' ),
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

			$max_allowed       = function_exists( 'sab_get_address_limit' ) ? sab_get_address_limit() : 3;
			$can_add_billing   = SAB_Database::can_add_address( $user_id, 'billing' );
			$can_add_shipping  = SAB_Database::can_add_address( $user_id, 'shipping' );
			$can_add           = $can_add_billing || $can_add_shipping;
			$validate_req      = 'yes' === get_option( 'sab_validate_required_fields', 'yes' );
			$checkout_save     = 'yes' === get_option( 'sab_enable_checkout_save', 'yes' );
			$is_pro            = function_exists( 'sab_is_pro_active' ) ? sab_is_pro_active() : false;

			$allowed_billing_countries  = ( function_exists( 'WC' ) && WC()->countries ) ? WC()->countries->get_allowed_countries() : array();
			$allowed_shipping_countries = ( function_exists( 'WC' ) && WC()->countries ) ? ( WC()->countries->get_shipping_countries() ?: $allowed_billing_countries ) : array();

			$base_country             = ( function_exists( 'WC' ) && WC()->countries ) ? WC()->countries->get_base_country() : 'US';
			$default_billing_country  = ( function_exists( 'WC' ) && WC()->customer && WC()->customer->get_billing_country() ) ? WC()->customer->get_billing_country() : $base_country;
			$default_shipping_country = ( function_exists( 'WC' ) && WC()->customer && WC()->customer->get_shipping_country() ) ? WC()->customer->get_shipping_country() : $base_country;

			$checkout_data = array(
				'is_logged_in'             => true,
				'ajax_url'                 => admin_url( 'admin-ajax.php' ),
				'nonce'                    => wp_create_nonce( 'sab_checkout_nonce' ),
				'states'                   => ( function_exists( 'WC' ) && WC()->countries ) ? WC()->countries->get_states() : array(),
				'countries'                => $allowed_billing_countries,
				'billing_countries'        => $allowed_billing_countries,
				'shipping_countries'       => $allowed_shipping_countries,
				'default_billing_country'  => $default_billing_country,
				'default_shipping_country' => $default_shipping_country,
				'billing_addresses'        => $format_addr_fn( $billing_addresses, $default_billing ? $default_billing->id : 0 ),
				'shipping_addresses'       => $format_addr_fn( $shipping_addresses, $default_shipping ? $default_shipping->id : 0 ),
				'default_billing_id'       => $default_billing ? (int) $default_billing->id : 0,
				'default_shipping_id'      => $default_shipping ? (int) $default_shipping->id : 0,
				'enable_billing'           => 'yes' === get_option( 'sab_enable_billing', 'yes' ),
				'enable_shipping'          => 'yes' === get_option( 'sab_enable_shipping', 'yes' ),
				'max_addresses'            => $max_allowed,
				'can_add_address'          => $can_add,
				'can_add_billing'          => $can_add_billing,
				'can_add_shipping'         => $can_add_shipping,
				'is_pro'                   => $is_pro,
				'validate_required_fields' => $validate_req,
				'enable_checkout_save'     => $checkout_save,
				'limit_title'              => __( 'Address limit reached', 'smart-address-book' ),
				'limit_message'            => __( 'You have reached the maximum limit of saved addresses. Upgrade to Pro to save unlimited addresses.', 'smart-address-book' ),
				'limit_message_billing'    => __( 'You have reached the maximum limit of 3 billing addresses. Upgrade to Pro to save unlimited billing addresses.', 'smart-address-book' ),
				'limit_message_shipping'   => __( 'You have reached the maximum limit of 3 shipping addresses. Upgrade to Pro to save unlimited shipping addresses.', 'smart-address-book' ),
				'pro_button_text'          => __( 'Pro Upgrade – Coming Soon', 'smart-address-book' ),
				'pro_coming_soon_notice'   => __( "Pro Upgrade is coming soon! You'll soon be able to upgrade your plan and enjoy unlimited billing and shipping addresses.", 'smart-address-book' ),
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
					'edit'                => __( 'Edit', 'smart-address-book' ),
					'save_changes'        => __( 'Save Changes', 'smart-address-book' ),
					'cancel'              => __( 'Cancel', 'smart-address-book' ),
					'contact_information' => __( 'Contact Information', 'smart-address-book' ),
				),
			);

			wp_localize_script( 'sab-checkout', 'sab_checkout_params', $checkout_data );

			// Also register and localize sab-blocks-checkout for Checkout Blocks
			$blocks_js_ver = defined( 'SAB_VERSION' ) ? SAB_VERSION . '.' . filemtime( SAB_PLUGIN_DIR . 'public/js/sab-blocks-checkout.js' ) : time();
			wp_enqueue_script(
				'sab-blocks-checkout',
				SAB_PLUGIN_URL . 'public/js/sab-blocks-checkout.js',
				array( 'jquery' ),
				$blocks_js_ver,
				true
			);
			wp_localize_script( 'sab-blocks-checkout', 'sab_blocks_checkout_params', $checkout_data );
		}
	}

	/**
	 * Render the Pro Limit Modal template in footer for My Account and Checkout pages.
	 */
	public function render_pro_limit_modal() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Only render on account, checkout, or cart pages where address actions can occur
		if ( ! is_account_page() && ! is_checkout() && ! is_cart() ) {
			return;
		}
		?>
		<div id="sab-pro-limit-modal" class="sab-modal-backdrop" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="sab-pro-limit-title">
			<div class="sab-modal-dialog" style="max-width: 480px;">
				<div class="sab-modal-header">
					<h3 id="sab-pro-limit-title" style="margin:0; font-size:18px;">
						<?php esc_html_e( 'Address limit reached', 'smart-address-book' ); ?>
					</h3>
					<button type="button" class="sab-modal-close sab-pro-limit-close" aria-label="<?php esc_attr_e( 'Close', 'smart-address-book' ); ?>">&times;</button>
				</div>
				<div class="sab-modal-body" style="padding:24px 20px; text-align:center;">
					<div class="sab-pro-limit-icon" style="margin-bottom:16px;">
						<span class="dashicons dashicons-lock" style="font-size:44px; width:44px; height:44px; color:#f0ad4e;"></span>
					</div>
					<p id="sab-pro-limit-message" class="sab-pro-limit-message" style="font-size:15px; line-height:1.6; margin:0 0 16px 0; color:#333;">
						<?php esc_html_e( 'You have reached the maximum limit of 3 billing addresses. Upgrade to Pro to save unlimited billing addresses.', 'smart-address-book' ); ?>
					</p>
					<div id="sab-pro-coming-soon-notice" class="sab-pro-coming-soon-notice" style="display:none; text-align:left; margin:16px 0 0 0; padding:12px 16px; background:#e8f4fc; border-left:4px solid #0073aa; border-radius:4px; font-size:13.5px; line-height:1.5; color:#1d2327;">
						<strong><?php esc_html_e( "Pro Upgrade is coming soon! You'll soon be able to upgrade your plan and enjoy unlimited billing and shipping addresses.", 'smart-address-book' ); ?></strong>
					</div>
				</div>
				<div class="sab-modal-footer" style="padding:14px 20px; display:flex; justify-content:center; gap:10px; background:#fafafa; border-top:1px solid #e5e5e5;">
					<button type="button" class="button button-primary sab-pro-upgrade-btn">
						<?php esc_html_e( 'Pro Upgrade – Coming Soon', 'smart-address-book' ); ?>
					</button>
					<button type="button" class="button sab-pro-limit-close">
						<?php esc_html_e( 'Close', 'smart-address-book' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}
}

