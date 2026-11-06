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
 * Check if WooCommerce is active.
 *
 * @return bool
 */
function sab_is_woocommerce_active() {
	return (bool) apply_filters( 'sab_is_woocommerce_active', class_exists( 'WooCommerce' ) );
}

/**
 * Check if WooCommerce is installed.
 *
 * @return bool
 */
function sab_is_woocommerce_installed() {
	$installed = file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' );

	if ( ! $installed ) {
		if ( ! function_exists( 'get_plugins' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( function_exists( 'get_plugins' ) ) {
			$plugins = get_plugins();
			if ( isset( $plugins['woocommerce/woocommerce.php'] ) ) {
				$installed = true;
			} else {
				foreach ( $plugins as $plugin_file => $plugin_data ) {
					if ( isset( $plugin_data['TextDomain'] ) && 'woocommerce' === $plugin_data['TextDomain'] ) {
						$installed = true;
						break;
					}
				}
			}
		}
	}

	return (bool) apply_filters( 'sab_is_woocommerce_installed', $installed );
}

/**
 * Display admin error notice when WooCommerce is not installed.
 */
function sab_woocommerce_not_installed_notice() {
	$install_url = current_user_can( 'install_plugins' )
		? wp_nonce_url(
			self_admin_url( 'update.php?action=install-plugin&plugin=woocommerce' ),
			'install-plugin_woocommerce'
		)
		: '';
	?>
	<div class="notice notice-error">
		<p>
			<?php esc_html_e( 'Smart Address Book requires WooCommerce to be installed. Please install WooCommerce.', 'smart-address-book' ); ?>
			<?php if ( $install_url ) : ?>
				<a href="<?php echo esc_url( $install_url ); ?>" class="button button-primary" style="margin-left: 8px;"><?php esc_html_e( 'Install WooCommerce', 'smart-address-book' ); ?></a>
			<?php endif; ?>
		</p>
	</div>
	<?php
}

/**
 * Display admin warning notice when WooCommerce is installed but inactive.
 */
function sab_woocommerce_inactive_notice() {
	$activate_url = current_user_can( 'activate_plugins' )
		? wp_nonce_url(
			admin_url( 'plugins.php?action=activate&plugin=woocommerce/woocommerce.php' ),
			'activate-plugin_woocommerce/woocommerce.php'
		)
		: '';
	?>
	<div class="notice notice-warning">
		<p>
			<?php esc_html_e( 'Smart Address Book requires WooCommerce to be active. Please activate WooCommerce.', 'smart-address-book' ); ?>
			<?php if ( $activate_url ) : ?>
				<a href="<?php echo esc_url( $activate_url ); ?>" class="button button-primary" style="margin-left: 8px;"><?php esc_html_e( 'Activate WooCommerce', 'smart-address-book' ); ?></a>
			<?php endif; ?>
		</p>
	</div>
	<?php
}

/**
 * Check if WooCommerce is active before initializing plugin
 */
function sab_init_plugin() {
	if ( ! sab_is_woocommerce_active() ) {
		if ( ! sab_is_woocommerce_installed() ) {
			add_action( 'admin_notices', 'sab_woocommerce_not_installed_notice' );
			add_action( 'network_admin_notices', 'sab_woocommerce_not_installed_notice' );
		} else {
			add_action( 'admin_notices', 'sab_woocommerce_inactive_notice' );
			add_action( 'network_admin_notices', 'sab_woocommerce_inactive_notice' );
		}
		return;
	}

	SAB_Plugin::instance();
}
add_action( 'plugins_loaded', 'sab_init_plugin' );

