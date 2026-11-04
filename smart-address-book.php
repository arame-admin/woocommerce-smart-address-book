<?php
/**
 * Plugin Name: Smart Address Book for WooCommerce
 * Plugin URI:  https://example.com/smart-address-book
 * Description: Address management experience where logged-in WooCommerce customers can save, manage, and select multiple billing and shipping addresses during checkout.
 * Version:     1.0.0
 * Author:      Developer
 * Text Domain: smart-address-book
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.7
 * Requires PHP: 8.1
 * WC requires at least: 7.0
 * WC tested up to: 9.4
 * License:     GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin Constants
define( 'SAB_VERSION', '1.0.0' );
define( 'SAB_PLUGIN_FILE', __FILE__ );
define( 'SAB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SAB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SAB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Declare High-Performance Order Storage (HPOS) Compatibility
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Include Core Dependency Classes
 */
require_once SAB_PLUGIN_DIR . 'includes/class-sab-activator.php';
require_once SAB_PLUGIN_DIR . 'includes/class-sab-deactivator.php';
require_once SAB_PLUGIN_DIR . 'includes/class-sab-database.php';
require_once SAB_PLUGIN_DIR . 'includes/class-sab-address.php';
require_once SAB_PLUGIN_DIR . 'includes/class-sab-ajax.php';
require_once SAB_PLUGIN_DIR . 'includes/class-sab-import-export.php';
require_once SAB_PLUGIN_DIR . 'includes/class-sab-privacy.php';
require_once SAB_PLUGIN_DIR . 'includes/class-sab-plugin.php';

// Public & Admin Classes
require_once SAB_PLUGIN_DIR . 'public/class-sab-public.php';
require_once SAB_PLUGIN_DIR . 'public/class-sab-myaccount.php';
require_once SAB_PLUGIN_DIR . 'public/class-sab-checkout.php';

if ( is_admin() ) {
	require_once SAB_PLUGIN_DIR . 'admin/class-sab-admin.php';
	require_once SAB_PLUGIN_DIR . 'admin/class-sab-admin-settings.php';
	require_once SAB_PLUGIN_DIR . 'admin/class-sab-admin-user-profile.php';
	require_once SAB_PLUGIN_DIR . 'admin/class-sab-admin-order.php';
}

/**
 * Activation & Deactivation Hooks
 */
register_activation_hook( __FILE__, array( 'SAB_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SAB_Deactivator', 'deactivate' ) );

/**
 * Check if WooCommerce is active before initializing plugin
 */
function sab_init_plugin() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			function () {
				?>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'Smart Address Book requires WooCommerce to be installed and active.', 'smart-address-book' ); ?></p>
				</div>
				<?php
			}
		);
		return;
	}

	SAB_Plugin::instance();
}
add_action( 'plugins_loaded', 'sab_init_plugin' );

