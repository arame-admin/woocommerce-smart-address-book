<?php
/**
 * Fired when the plugin is uninstalled.
 * Supports single site and WordPress multisite networks (Point 25).
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Clean up plugin data for a single site/blog.
 */
function sab_uninstall_single_site() {
	$delete_data = get_option( 'sab_delete_data_on_uninstall', 'no' );

	if ( 'yes' === $delete_data ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'sab_addresses';
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$options_to_delete = array(
			'sab_enable_billing',
			'sab_enable_shipping',
			'sab_max_addresses',
			'sab_enable_nickname',
			'sab_enable_address_search',
			'sab_delete_data_on_uninstall',
			'sab_enable_checkout_save',
			'sab_auto_set_default',
			'sab_validate_postal_code',
			'sab_validate_phone',
			'sab_validate_required_fields',
			'sab_db_version',
		);

		foreach ( $options_to_delete as $option ) {
			delete_option( $option );
		}
	}
}

// Handle multisite network uninstall or single site uninstall (Point 25)
if ( is_multisite() ) {
	if ( function_exists( 'get_sites' ) ) {
		$site_ids = get_sites( array( 'fields' => 'ids' ) );
		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			sab_uninstall_single_site();
			restore_current_blog();
		}
	} elseif ( function_exists( 'wp_get_sites' ) ) {
		// Fallback for older WordPress versions
		$sites = wp_get_sites();
		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );
			sab_uninstall_single_site();
			restore_current_blog();
		}
	} else {
		sab_uninstall_single_site();
	}
} else {
	sab_uninstall_single_site();
}
