<?php
/**
 * Fired when the plugin is uninstalled.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

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

