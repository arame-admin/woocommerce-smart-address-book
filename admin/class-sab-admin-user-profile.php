<?php
/**
 * Admin User Profile Screen Integration (FR-14).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAB_Admin_User_Profile {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'show_user_profile', array( $this, 'render_user_addresses' ), 20 );
		add_action( 'edit_user_profile', array( $this, 'render_user_addresses' ), 20 );
	}

	/**
	 * Render customer address management table on user profile screen.
	 *
	 * @param WP_User $user Current user object.
	 */
	public function render_user_addresses( $user ) {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}

		$user_id   = $user->ID;
		$addresses = SAB_Database::get_addresses_sorted( $user_id );

		$template_path = SAB_PLUGIN_DIR . 'admin/views/user-profile-addresses.php';
		include $template_path;
	}
}

