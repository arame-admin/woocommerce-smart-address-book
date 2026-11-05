<?php
/**
 * Admin controller for Smart Address Book.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAB_Admin {

	/**
	 * Settings instance.
	 *
	 * @var SAB_Admin_Settings|null
	 */
	public $settings = null;

	/**
	 * User profile integration instance.
	 *
	 * @var SAB_Admin_User_Profile|null
	 */
	public $user_profile = null;

	/**
	 * Admin order integration instance.
	 *
	 * @var SAB_Admin_Order|null
	 */
	public $order = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings     = new SAB_Admin_Settings();
		$this->user_profile = new SAB_Admin_User_Profile();
		$this->order        = new SAB_Admin_Order();

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_filter( 'plugin_action_links_' . SAB_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );
	}

	/**
	 * Add Settings link on Plugins page.
	 *
	 * @param array $links Action links.
	 * @return array
	 */
	public function add_action_links( $links ) {
		$settings_url = admin_url( 'admin.php?page=wc-settings&tab=sab_address_book' );
		$custom_links = array(
			'<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'smart-address-book' ) . '</a>',
		);
		return array_merge( $custom_links, $links );
	}

	/**
	 * Enqueue admin scripts & styles on relevant screens.
	 *
	 * @param string $hook Screen hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		$is_settings_tab = ( isset( $_GET['page'], $_GET['tab'] ) && 'wc-settings' === $_GET['page'] && 'sab_address_book' === $_GET['tab'] );
		$is_user_screen  = in_array( $hook, array( 'user-edit.php', 'profile.php' ), true );
		$is_order_screen = in_array( $hook, array( 'post.php', 'post-new.php', 'woocommerce_page_wc-orders' ), true );

		if ( ! $is_settings_tab && ! $is_user_screen && ! $is_order_screen ) {
			return;
		}

		wp_enqueue_style(
			'sab-admin-css',
			SAB_PLUGIN_URL . 'admin/css/sab-admin.css',
			array(),
			SAB_VERSION
		);

		wp_enqueue_script(
			'sab-admin-js',
			SAB_PLUGIN_URL . 'admin/js/sab-admin.js',
			array( 'jquery' ),
			SAB_VERSION,
			true
		);

		wp_localize_script(
			'sab-admin-js',
			'sab_admin_params',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'sab_admin_nonce' ),
				'i18n'     => array(
					'confirm_delete' => __( 'Are you sure you want to delete this address?', 'smart-address-book' ),
					'deleting'       => __( 'Deleting...', 'smart-address-book' ),
					'saving'         => __( 'Saving...', 'smart-address-book' ),
					'success'        => __( 'Action completed successfully.', 'smart-address-book' ),
					'error'          => __( 'An error occurred.', 'smart-address-book' ),
					'default'        => __( 'Default', 'smart-address-book' ),
				),
			)
		);
	}
}

