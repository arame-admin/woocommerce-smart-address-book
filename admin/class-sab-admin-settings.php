<?php
/**
 * WooCommerce Settings Tab for Smart Address Book.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAB_Admin_Settings {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_tabs_sab_address_book', array( $this, 'render_settings' ) );
		add_action( 'woocommerce_update_options_sab_address_book', array( $this, 'save_settings' ) );
		add_action( 'woocommerce_sections_sab_address_book', array( $this, 'output_sections' ) );
		add_filter( 'pre_update_option_sab_max_addresses', array( $this, 'sanitize_max_addresses' ), 10, 2 );
	}

	/**
	 * Add custom tab to WooCommerce Settings.
	 *
	 * @param array $tabs Settings tabs.
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['sab_address_book'] = __( 'Address Book', 'smart-address-book' );
		return $tabs;
	}

	/**
	 * Get sub-sections within the Address Book tab.
	 *
	 * @return array
	 */
	public function get_sections() {
		return apply_filters(
			'sab_get_settings_sections',
			array(
				''           => __( 'General', 'smart-address-book' ),
				'checkout'   => __( 'Checkout', 'smart-address-book' ),
				'validation' => __( 'Validation', 'smart-address-book' ),
			)
		);
	}

	/**
	 * Output section links.
	 */
	public function output_sections() {
		global $current_section;

		$sections = $this->get_sections();
		if ( empty( $sections ) || 1 === count( $sections ) ) {
			return;
		}

		echo '<ul class="subsubsub">';
		$array_keys = array_keys( $sections );
		foreach ( $sections as $id => $label ) {
			$url       = admin_url( 'admin.php?page=wc-settings&tab=sab_address_book&section=' . sanitize_title( $id ) );
			$class     = ( $current_section === $id ? 'current' : '' );
			$separator = ( end( $array_keys ) === $id ? '' : '|' );
			echo '<li><a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</a> ' . esc_html( $separator ) . ' </li>';
		}
		echo '</ul><br class="clear" />';
	}

	/**
	 * Get settings array based on current section.
	 *
	 * @param string $current_section Current section name.
	 * @return array
	 */
	public function get_settings( $current_section = '' ) {
		$settings = array();

		if ( 'checkout' === $current_section ) {
			// Section: Checkout Settings
			$settings = array(
				array(
					'title' => __( 'Checkout Address Settings', 'smart-address-book' ),
					'type'  => 'title',
					'desc'  => __( 'Configure how customer saved addresses behave during the checkout process.', 'smart-address-book' ),
					'id'    => 'sab_checkout_settings_title',
				),
				array(
					'title'   => __( 'Save Address at Checkout', 'smart-address-book' ),
					'desc'    => __( 'Allow customers to save newly entered addresses during checkout', 'smart-address-book' ),
					'id'      => 'sab_enable_checkout_save',
					'default' => 'yes',
					'type'    => 'checkbox',
				),
				array(
					'title'   => __( 'Auto-set Default Address', 'smart-address-book' ),
					'desc'    => __( 'Automatically set the first saved address as the customer\'s default address', 'smart-address-book' ),
					'id'      => 'sab_auto_set_default',
					'default' => 'yes',
					'type'    => 'checkbox',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'sab_checkout_settings_title',
				),
			);
		} elseif ( 'validation' === $current_section ) {
			// Section: Validation Settings (FR-11)
			$settings = array(
				array(
					'title' => __( 'Address Validation Settings', 'smart-address-book' ),
					'type'  => 'title',
					'desc'  => __( 'Enable automated validation checks on address submission.', 'smart-address-book' ),
					'id'    => 'sab_validation_settings_title',
				),
				array(
					'title'   => __( 'Enforce Required Fields', 'smart-address-book' ),
					'desc'    => __( 'Require First Name, Last Name, Street Address, City, Country, Postcode, and Email', 'smart-address-book' ),
					'id'      => 'sab_validate_required_fields',
					'default' => 'yes',
					'type'    => 'checkbox',
				),
				array(
					'title'   => __( 'Validate Postal Code', 'smart-address-book' ),
					'desc'    => __( 'Validate postal codes format against chosen country', 'smart-address-book' ),
					'id'      => 'sab_validate_postal_code',
					'default' => 'yes',
					'type'    => 'checkbox',
				),
				array(
					'title'   => __( 'Validate Phone Number', 'smart-address-book' ),
					'desc'    => __( 'Verify that entered phone numbers follow standard phone number format', 'smart-address-book' ),
					'id'      => 'sab_validate_phone',
					'default' => 'yes',
					'type'    => 'checkbox',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'sab_validation_settings_title',
				),
			);
		} else {
			// Section: General Settings
			$settings = array(
				array(
					'title' => __( 'General Address Book Settings', 'smart-address-book' ),
					'type'  => 'title',
					'desc'  => __( 'Configure global rules and features for customer address books.', 'smart-address-book' ),
					'id'    => 'sab_general_settings_title',
				),
				array(
					'title'   => __( 'Enable Billing Address Book', 'smart-address-book' ),
					'desc'    => __( 'Allow customers to manage multiple billing addresses', 'smart-address-book' ),
					'id'      => 'sab_enable_billing',
					'default' => 'yes',
					'type'    => 'checkbox',
				),
				array(
					'title'   => __( 'Enable Shipping Address Book', 'smart-address-book' ),
					'desc'    => __( 'Allow customers to manage multiple shipping addresses', 'smart-address-book' ),
					'id'      => 'sab_enable_shipping',
					'default' => 'yes',
					'type'    => 'checkbox',
				),
				call_user_func(
					function() {
						$is_pro = function_exists( 'sab_is_pro_active' ) ? sab_is_pro_active() : false;
						$setting = array(
							'title' => __( 'Maximum Saved Addresses', 'smart-address-book' ),
							'id'    => 'sab_max_addresses',
							'type'  => 'number',
						);

						if ( $is_pro ) {
							$setting['desc']              = __( 'Maximum number of addresses a customer can save (set 0 for unlimited)', 'smart-address-book' );
							$setting['default']           = '0';
							$setting['custom_attributes'] = array(
								'min'  => '0',
								'step' => '1',
							);
						} else {
							$setting['desc']              = __( 'The Free version supports a maximum of 3 billing addresses and 3 shipping addresses. Upgrade to Pro to configure higher limits or unlimited addresses.', 'smart-address-book' );
							$setting['default']           = '3';
							$setting['value']             = '3';
							$setting['custom_attributes'] = array(
								'min'      => '3',
								'max'      => '3',
								'readonly' => 'readonly',
								'disabled' => 'disabled',
							);
						}

						return $setting;
					}
				),
				array(
					'title'   => __( 'Address Nicknames', 'smart-address-book' ),
					'desc'    => __( 'Allow customers to assign nicknames/labels (e.g. Home, Office, Parents)', 'smart-address-book' ),
					'id'      => 'sab_enable_nickname',
					'default' => 'yes',
					'type'    => 'checkbox',
				),
				array(
					'title'   => __( 'Address Search', 'smart-address-book' ),
					'desc'    => __( 'Enable real-time address searching in customer account dashboard', 'smart-address-book' ),
					'id'      => 'sab_enable_address_search',
					'default' => 'yes',
					'type'    => 'checkbox',
				),
				array(
					'title'   => __( 'Delete Data on Uninstall', 'smart-address-book' ),
					'desc'    => __( 'Remove all address book tables and options when this plugin is deleted', 'smart-address-book' ),
					'id'      => 'sab_delete_data_on_uninstall',
					'default' => 'no',
					'type'    => 'checkbox',
				),
				array(
					'type' => 'sectionend',
					'id'   => 'sab_general_settings_title',
				),
			);
		}

		return apply_filters( 'sab_get_settings', $settings, $current_section );
	}

	/**
	 * Render settings page.
	 */
	public function render_settings() {
		global $current_section;
		woocommerce_admin_fields( $this->get_settings( $current_section ) );
	}

	/**
	 * Save settings.
	 */
	public function save_settings() {
		global $current_section;
		$is_pro = function_exists( 'sab_is_pro_active' ) ? sab_is_pro_active() : false;
		if ( ! $is_pro && '' === $current_section ) {
			$_POST['sab_max_addresses'] = '3';
		}
		woocommerce_update_options( $this->get_settings( $current_section ) );
	}

	/**
	 * Sanitize maximum saved addresses setting.
	 *
	 * Free version strictly enforces 3.
	 * Pro version allows 0 (unlimited) and positive numbers.
	 *
	 * @param mixed $new_value New option value.
	 * @param mixed $old_value Old option value.
	 * @return int
	 */
	public function sanitize_max_addresses( $new_value, $old_value ) {
		$is_pro = function_exists( 'sab_is_pro_active' ) ? sab_is_pro_active() : false;
		if ( ! $is_pro ) {
			return 3;
		}

		return max( 0, absint( $new_value ) );
	}
}

