<?php
/**
 * Fired during plugin activation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAB_Activator {

	/**
	 * Run activation logic.
	 */
	public static function activate() {
		self::create_tables();
		self::set_default_options();

		// Set DB version
		update_option( 'sab_db_version', SAB_VERSION );

		// Flush rewrite rules for My Account endpoint
		add_rewrite_endpoint( 'sab-address-book', EP_ROOT | EP_PAGES );
		flush_rewrite_rules();
	}

	/**
	 * Create or update custom database tables.
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'sab_addresses';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			address_type varchar(10) NOT NULL DEFAULT 'shipping',
			nickname varchar(100) DEFAULT NULL,
			first_name varchar(100) DEFAULT NULL,
			last_name varchar(100) DEFAULT NULL,
			company varchar(200) DEFAULT NULL,
			phone varchar(50) DEFAULT NULL,
			email varchar(200) DEFAULT NULL,
			address_1 varchar(255) DEFAULT NULL,
			address_2 varchar(255) DEFAULT NULL,
			city varchar(100) DEFAULT NULL,
			state varchar(100) DEFAULT NULL,
			country varchar(10) DEFAULT NULL,
			postal_code varchar(20) DEFAULT NULL,
			is_default tinyint(1) NOT NULL DEFAULT 0,
			last_used_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY user_type (user_id, address_type),
			KEY is_default (user_id, address_type, is_default),
			KEY last_used (user_id, address_type, last_used_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Set sensible default options.
	 */
	private static function set_default_options() {
		$defaults = array(
			'sab_enable_billing'           => 'yes',
			'sab_enable_shipping'          => 'yes',
			'sab_max_addresses'            => '0',
			'sab_enable_nickname'          => 'yes',
			'sab_enable_address_search'    => 'yes',
			'sab_delete_data_on_uninstall' => 'no',
			'sab_enable_checkout_save'     => 'yes',
			'sab_auto_set_default'         => 'yes',
			'sab_validate_postal_code'     => 'yes',
			'sab_validate_phone'           => 'yes',
			'sab_validate_required_fields' => 'yes',
		);

		foreach ( $defaults as $option => $value ) {
			if ( false === get_option( $option ) ) {
				update_option( $option, $value );
			}
		}
	}
}

