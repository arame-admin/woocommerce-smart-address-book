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

			wp_localize_script(
				'sab-checkout',
				'sab_checkout_params',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'sab_checkout_nonce' ),
					'i18n'     => array(
						'loading'      => __( 'Loading address...', 'smart-address-book' ),
						'save_success' => __( 'Address saved!', 'smart-address-book' ),
						'error'        => __( 'Could not load address.', 'smart-address-book' ),
					),
				)
			);
		}
	}
}

