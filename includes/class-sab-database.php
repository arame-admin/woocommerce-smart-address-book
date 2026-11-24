<?php
/**
 * Database abstraction layer for Smart Address Book.
 * Acts as a static facade delegating to SAB_Address_Repository_Interface.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'interface-sab-address-repository.php';
require_once plugin_dir_path( __FILE__ ) . 'class-sab-address-repository.php';

class SAB_Database {

	/**
	 * Address types constants.
	 */
	const TYPE_BILLING  = SAB_Address_Repository::TYPE_BILLING;
	const TYPE_SHIPPING = SAB_Address_Repository::TYPE_SHIPPING;

	/**
	 * Stores the ID of the new default address assigned during the last delete operation, if any.
	 *
	 * @var int
	 */
	public static $last_new_default_id = 0;

	/**
	 * Active repository instance.
	 *
	 * @var SAB_Address_Repository_Interface|null
	 */
	private static $repository = null;

	/**
	 * Get the repository instance.
	 *
	 * @return SAB_Address_Repository_Interface
	 */
	public static function get_repository() {
		if ( null === self::$repository ) {
			self::$repository = new SAB_Address_Repository();
		}
		return self::$repository;
	}

	/**
	 * Set a custom repository instance (e.g. for testing / mocking).
	 *
	 * @param SAB_Address_Repository_Interface $repository Repository instance.
	 */
	public static function set_repository( SAB_Address_Repository_Interface $repository ) {
		self::$repository = $repository;
	}

	/**
	 * Get the table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		return self::get_repository()->get_table_name();
	}

	/**
	 * Check and upgrade database schema using db version option (Points 3, 18).
	 * Eliminates runtime SHOW COLUMNS query overhead.
	 */
	public static function check_and_upgrade_schema() {
		$target_db_version  = defined( 'SAB_DB_VERSION' ) ? SAB_DB_VERSION : '1.1.0';
		$current_db_version = get_option( 'sab_db_version', '1.0.0' );

		if ( version_compare( $current_db_version, $target_db_version, '>=' ) ) {
			return;
		}

		global $wpdb;
		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			address_type varchar(8) NOT NULL DEFAULT 'shipping',
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
			country char(2) DEFAULT NULL,
			postal_code varchar(20) DEFAULT NULL,
			is_default tinyint(1) NOT NULL DEFAULT 0,
			last_used_at datetime DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY is_default (user_id, address_type, is_default),
			KEY last_used (user_id, address_type, last_used_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Verify table exists before attempting alterations (Issue 3)
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
		if ( ! $table_exists ) {
			return;
		}

		// Pre-validate and sanitize existing data to prevent MySQL strict mode errors (STRICT_TRANS_TABLES) (Issue 3)
		// 1. Sanitize country codes: truncate/trim to 2 characters uppercase
		$wpdb->query( "UPDATE {$table_name} SET country = UPPER(SUBSTRING(TRIM(country), 1, 2)) WHERE country IS NOT NULL AND CHAR_LENGTH(TRIM(country)) > 2" );

		// 2. Normalize address_type: ensure valid values before restricting column length
		$wpdb->query( "UPDATE {$table_name} SET address_type = 'shipping' WHERE address_type NOT IN ('billing', 'shipping') OR address_type IS NULL" );

		// Drop redundant user_type index if it exists from previous installations (Point 22)
		$indices = $wpdb->get_results( "SHOW INDEX FROM {$table_name} WHERE Key_name = 'user_type'" );
		if ( ! empty( $indices ) ) {
			$wpdb->query( "ALTER TABLE {$table_name} DROP INDEX user_type" );
		}

		// Ensure country is char(2) and address_type is varchar(8) (Points 1, 2)
		$wpdb->query( "ALTER TABLE {$table_name} MODIFY COLUMN country char(2) DEFAULT NULL" );
		$wpdb->query( "ALTER TABLE {$table_name} MODIFY COLUMN address_type varchar(8) NOT NULL DEFAULT 'shipping'" );

		// Only mark migration complete if no critical MySQL errors occurred
		if ( empty( $wpdb->last_error ) ) {
			update_option( 'sab_db_version', $target_db_version );
		}
	}

	/**
	 * Backward compatibility alias for check_and_upgrade_schema.
	 */
	public static function maybe_update_schema() {
		self::check_and_upgrade_schema();
	}

	/**
	 * Update last_used_at timestamp for an address.
	 *
	 * @param int $id Address ID.
	 * @return bool
	 */
	public static function update_last_used( $id ) {
		return self::get_repository()->update_last_used( $id );
	}

	/**
	 * Retrieve addresses for a user with optional filtering and sorting.
	 *
	 * @param int    $user_id      User ID.
	 * @param string $address_type Optional address type ('billing', 'shipping', or empty for both).
	 * @param string $orderby      Ordering method ('default', 'recent', 'id').
	 * @return array
	 */
	public static function get_addresses( $user_id, $address_type = '', $orderby = 'default' ) {
		return self::get_repository()->get_addresses( $user_id, $address_type, $orderby );
	}

	/**
	 * Retrieve addresses sorted by default, recent, remaining.
	 *
	 * @param int    $user_id      User ID.
	 * @param string $address_type Address type.
	 * @return array
	 */
	public static function get_addresses_sorted( $user_id, $address_type = '' ) {
		return self::get_repository()->get_addresses_sorted( $user_id, $address_type );
	}

	/**
	 * Get recently used address IDs from previous WooCommerce orders.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	public static function get_recently_used_ids( $user_id ) {
		$repo = self::get_repository();
		if ( method_exists( $repo, 'get_recently_used_ids' ) ) {
			return $repo->get_recently_used_ids( $user_id );
		}
		return array();
	}

	/**
	 * Get a single address by ID.
	 *
	 * @param int $id Address ID.
	 * @return object|null
	 */
	public static function get_address( $id ) {
		return self::get_repository()->get_address( $id );
	}

	/**
	 * Find an existing duplicate address for a user.
	 *
	 * @param int    $user_id      User ID.
	 * @param string $address_type 'billing' or 'shipping'.
	 * @param array  $data         Address data.
	 * @return int Address ID if found, 0 otherwise.
	 */
	public static function find_duplicate_address( $user_id, $address_type, $data ) {
		return self::get_repository()->find_duplicate_address( $user_id, $address_type, $data );
	}

	/**
	 * Insert a new address into the database.
	 *
	 * @param array $data Address data.
	 * @return int|false
	 */
	public static function add_address( $data ) {
		return self::get_repository()->add_address( $data );
	}

	/**
	 * Update an existing address.
	 *
	 * @param int   $id   Address ID.
	 * @param array $data Address data.
	 * @return bool
	 */
	public static function update_address( $id, $data ) {
		return self::get_repository()->update_address( $id, $data );
	}

	/**
	 * Delete an address.
	 *
	 * @param int $id Address ID.
	 * @return bool
	 */
	public static function delete_address( $id ) {
		$repo   = self::get_repository();
		$result = $repo->delete_address( $id );
		if ( isset( $repo->last_new_default_id ) ) {
			self::$last_new_default_id = $repo->last_new_default_id;
		}
		return $result;
	}

	/**
	 * Set an address as the default for its type.
	 *
	 * @param int    $address_id   Address ID.
	 * @param int    $user_id      User ID.
	 * @param string $address_type Address type.
	 * @return bool
	 */
	public static function set_default( $address_id, $user_id, $address_type ) {
		return self::get_repository()->set_default( $address_id, $user_id, $address_type );
	}

	/**
	 * Clear default flag for all addresses of a user and type.
	 *
	 * @param int    $user_id      User ID.
	 * @param string $address_type Address type.
	 * @param int    $exclude_id   Optional ID to exclude from unsetting.
	 * @return bool
	 */
	public static function clear_defaults( $user_id, $address_type, $exclude_id = 0 ) {
		return self::get_repository()->clear_defaults( $user_id, $address_type, $exclude_id );
	}

	/**
	 * Get the default address for a user.
	 *
	 * @param int    $user_id      User ID.
	 * @param string $address_type Address type.
	 * @return object|null
	 */
	public static function get_default_address( $user_id, $address_type = 'shipping' ) {
		return self::get_repository()->get_default_address( $user_id, $address_type );
	}

	/**
	 * Search saved addresses.
	 *
	 * @param int    $user_id      User ID.
	 * @param string $search_term  Query.
	 * @param string $address_type Optional address type.
	 * @return array
	 */
	public static function search_addresses( $user_id, $search_term, $address_type = '' ) {
		return self::get_repository()->search_addresses( $user_id, $search_term, $address_type );
	}

	/**
	 * Total address count for a user.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public static function get_address_count( $user_id ) {
		return self::get_repository()->get_address_count( $user_id );
	}

	/**
	 * Address count for a user by address type.
	 *
	 * @param int    $user_id      User ID.
	 * @param string $address_type Address type.
	 * @return int
	 */
	public static function get_address_count_by_type( $user_id, $address_type ) {
		return self::get_repository()->get_address_count_by_type( $user_id, $address_type );
	}

	/**
	 * Check if a user can add a new address based on maximum limit setting.
	 *
	 * @param int    $user_id      User ID.
	 * @param string $address_type Optional address type ('billing' or 'shipping').
	 * @return bool True if user can add, false if maximum limit reached.
	 */
	public static function can_add_address( $user_id, $address_type = '' ) {
		return self::get_repository()->can_add_address( $user_id, $address_type );
	}

	/**
	 * Purge all addresses for a deleted user (Point 4).
	 *
	 * @param int $user_id Deleted user ID.
	 * @return int Number of deleted rows.
	 */
	public static function on_delete_user( $user_id ) {
		return self::get_repository()->delete_addresses_by_user( $user_id );
	}
}
