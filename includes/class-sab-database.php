<?php
/**
 * Database abstraction layer for Smart Address Book.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAB_Database {

	/**
	 * Stores the ID of the new default address assigned during the last delete operation, if any.
	 *
	 * @var int
	 */
	public static $last_new_default_id = 0;

	/**
	 * Get the table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'sab_addresses';
	}

	/**
	 * Ensure table schema includes last_used_at.
	 */
	public static function maybe_update_schema() {
		global $wpdb;
		$table_name = self::table_name();

		static $checked = false;
		if ( $checked ) {
			return;
		}
		$checked = true;

		$row = $wpdb->get_results( "SHOW COLUMNS FROM {$table_name} LIKE 'last_used_at'" );
		if ( empty( $row ) ) {
			$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN last_used_at datetime DEFAULT NULL AFTER is_default" );
			$wpdb->query( "ALTER TABLE {$table_name} ADD INDEX last_used (user_id, address_type, last_used_at)" );
		}
	}

	/**
	 * Update last_used_at timestamp for an address.
	 *
	 * @param int $id Address ID.
	 * @return bool
	 */
	public static function update_last_used( $id ) {
		global $wpdb;
		$table_name = self::table_name();
		$id         = absint( $id );
		if ( ! $id ) {
			return false;
		}

		self::maybe_update_schema();

		$result = $wpdb->update(
			$table_name,
			array( 'last_used_at' => current_time( 'mysql' ) ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Retrieve addresses for a user with optional filtering and sorting.
	 *
	 * @param int    $user_id User ID.
	 * @param string $address_type Optional address type ('billing', 'shipping', or empty for both).
	 * @param string $orderby Ordering method ('default', 'recent', 'id').
	 * @return array
	 */
	public static function get_addresses( $user_id, $address_type = '', $orderby = 'default' ) {
		global $wpdb;

		$table_name = self::table_name();
		$user_id    = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}

		if ( 'default' === $orderby ) {
			return self::get_addresses_sorted( $user_id, $address_type );
		}

		if ( ! empty( $address_type ) ) {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE user_id = %d AND address_type = %s ORDER BY is_default DESC, id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$address_type
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY is_default DESC, id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			);
		}

		$results = $wpdb->get_results( $sql );

		/**
		 * Filter the retrieved addresses for a user.
		 *
		 * @param array  $results      Array of address objects.
		 * @param int    $user_id      User ID.
		 * @param string $address_type Address type ('billing', 'shipping', or empty).
		 * @param string $orderby      Ordering method applied.
		 */
		return apply_filters( 'sab_get_addresses', $results, $user_id, $address_type, $orderby );
	}

	/**
	 * FR-10 Address Sorting:
	 * 1. Default Address
	 * 2. Recently Used
	 * 3. Remaining Addresses
	 *
	 * @param int    $user_id User ID.
	 * @param string $address_type Address type.
	 * @return array
	 */
	public static function get_addresses_sorted( $user_id, $address_type = '' ) {
		global $wpdb;

		self::maybe_update_schema();

		$table_name = self::table_name();
		$user_id    = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}

		if ( ! empty( $address_type ) ) {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE user_id = %d AND address_type = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$address_type
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			);
		}

		$all = $wpdb->get_results( $sql );
		if ( empty( $all ) ) {
			return array();
		}

		$defaults      = array();
		$recently_used = array();
		$remaining     = array();

		// Initialize flags
		foreach ( $all as $address ) {
			$address->is_recently_used = false;
		}

		// 1. Separate default addresses
		foreach ( $all as $address ) {
			if ( (int) $address->is_default === 1 ) {
				$defaults[] = $address;
			}
		}

		$non_defaults = array_values(
			array_filter(
				$all,
				function ( $a ) {
					return (int) $a->is_default !== 1;
				}
			)
		);

		// 2. Check for addresses with last_used_at
		$has_last_used = false;
		foreach ( $non_defaults as $address ) {
			if ( ! empty( $address->last_used_at ) ) {
				$has_last_used = true;
				break;
			}
		}

		if ( $has_last_used ) {
			foreach ( $non_defaults as $address ) {
				if ( ! empty( $address->last_used_at ) ) {
					$recently_used[] = $address;
				} else {
					$remaining[] = $address;
				}
			}

			// Sort recently used by last_used_at DESC
			usort(
				$recently_used,
				function ( $a, $b ) {
					return strtotime( $b->last_used_at ) - strtotime( $a->last_used_at );
				}
			);
		} else {
			// Fallback to order history if last_used_at is not populated yet
			$recently_used_ids = self::get_recently_used_ids( $user_id );
			foreach ( $non_defaults as $address ) {
				if ( in_array( (int) $address->id, $recently_used_ids, true ) ) {
					$recently_used[] = $address;
				} else {
					$remaining[] = $address;
				}
			}

			if ( ! empty( $recently_used ) && ! empty( $recently_used_ids ) ) {
				usort(
					$recently_used,
					function ( $a, $b ) use ( $recently_used_ids ) {
						$position_a = array_search( (int) $a->id, $recently_used_ids, true );
						$position_b = array_search( (int) $b->id, $recently_used_ids, true );
						return $position_a - $position_b;
					}
				);
			}
		}

		// Mark the most recently used address
		if ( ! empty( $recently_used ) ) {
			$recently_used[0]->is_recently_used = true;
		}

		// 3. Sort remaining by updated_at / id DESC
		usort(
			$remaining,
			function ( $a, $b ) {
				return (int) $b->id - (int) $a->id;
			}
		);

		$sorted = array_merge( $defaults, $recently_used, $remaining );

		/**
		 * Filter the sorted addresses for a user.
		 *
		 * @param array  $sorted       Sorted array of address objects.
		 * @param int    $user_id      User ID.
		 * @param string $address_type Address type ('billing', 'shipping', or empty).
		 */
		return apply_filters( 'sab_get_addresses_sorted', $sorted, $user_id, $address_type );
	}

	/**
	 * Get recently used address IDs from previous WooCommerce orders.
	 *
	 * @param int $user_id User ID.
	 * @return array Array of address IDs.
	 */
	public static function get_recently_used_ids( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id || ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => 10,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'return'      => 'ids',
			)
		);

		if ( empty( $orders ) ) {
			return array();
		}

		$matched_ids = array();
		$addresses   = self::get_raw_addresses( $user_id );

		foreach ( $orders as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			$order_billing  = $order->get_address( 'billing' );
			$order_shipping = $order->get_address( 'shipping' );

			foreach ( $addresses as $address ) {
				$target = ( 'billing' === $address->address_type ) ? $order_billing : $order_shipping;
				if ( self::addresses_match( $address, $target ) ) {
					if ( ! in_array( (int) $address->id, $matched_ids, true ) ) {
						$matched_ids[] = (int) $address->id;
					}
				}
			}
		}

		return $matched_ids;
	}

	/**
	 * Helper: Check if a saved address matches order address.
	 *
	 * @param object $address Saved address.
	 * @param array  $order_address Order address array.
	 * @return bool
	 */
	private static function addresses_match( $address, $order_address ) {
		if ( empty( $order_address ) || empty( $order_address['address_1'] ) ) {
			return false;
		}

		$addr_1_match = strtolower( trim( $address->address_1 ?? '' ) ) === strtolower( trim( $order_address['address_1'] ?? '' ) );
		$post_match   = strtolower( trim( $address->postal_code ?? '' ) ) === strtolower( trim( $order_address['postcode'] ?? '' ) );
		$city_match   = strtolower( trim( $address->city ?? '' ) ) === strtolower( trim( $order_address['city'] ?? '' ) );

		return ( $addr_1_match && $post_match && $city_match );
	}

	/**
	 * Raw address fetch without sorting overhead.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	private static function get_raw_addresses( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}
		$table_name = self::table_name();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			)
		);
	}

	/**
	 * Get a single address by ID.
	 *
	 * @param int $id Address ID.
	 * @return object|null
	 */
	public static function get_address( $id ) {
		global $wpdb;

		$table_name = self::table_name();
		$id         = absint( $id );

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			)
		);
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
		global $wpdb;

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return 0;
		}

		$table_name   = self::table_name();
		$address_type = in_array( $address_type, array( 'billing', 'shipping' ), true ) ? $address_type : 'shipping';

		$first_name  = sanitize_text_field( $data['first_name'] ?? '' );
		$last_name   = sanitize_text_field( $data['last_name'] ?? '' );
		$address_1   = sanitize_text_field( $data['address_1'] ?? '' );
		$city        = sanitize_text_field( $data['city'] ?? '' );
		$postal_code = sanitize_text_field( $data['postal_code'] ?? ( $data['postcode'] ?? '' ) );
		$country     = sanitize_text_field( $data['country'] ?? '' );

		if ( empty( $first_name ) || empty( $last_name ) || empty( $address_1 ) ) {
			return 0;
		}

		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table_name}
				 WHERE user_id = %d
				   AND address_type = %s
				   AND LOWER(TRIM(first_name)) = LOWER(TRIM(%s))
				   AND LOWER(TRIM(last_name)) = LOWER(TRIM(%s))
				   AND LOWER(TRIM(address_1)) = LOWER(TRIM(%s))
				   AND LOWER(TRIM(city)) = LOWER(TRIM(%s))
				   AND LOWER(TRIM(postal_code)) = LOWER(TRIM(%s))
				   AND LOWER(TRIM(country)) = LOWER(TRIM(%s))
				 LIMIT 1",
				$user_id,
				$address_type,
				$first_name,
				$last_name,
				$address_1,
				$city,
				$postal_code,
				$country
			)
		);

		return $existing_id ? (int) $existing_id : 0;
	}

	/**
	 * Insert a new address into the database.
	 *
	 * @param array $data Address data.
	 * @return int|false
	 */
	public static function add_address( $data ) {
		global $wpdb;

		self::maybe_update_schema();

		$table_name = self::table_name();

		$user_id      = absint( $data['user_id'] ?? get_current_user_id() );
		$address_type = in_array( $data['address_type'] ?? '', array( 'billing', 'shipping' ), true ) ? $data['address_type'] : 'shipping';
		$is_default   = ! empty( $data['is_default'] ) ? 1 : 0;

		// Check for existing duplicate address for this user to prevent duplicates
		$duplicate_id = self::find_duplicate_address( $user_id, $address_type, $data );
		if ( $duplicate_id ) {
			self::update_last_used( $duplicate_id );
			return $duplicate_id;
		}

		// Enforce maximum address limit
		if ( ! self::can_add_address( $user_id, $address_type ) ) {
			return false;
		}

		// If user has no existing address of this type, make this the default automatically
		$existing_count = self::get_address_count_by_type( $user_id, $address_type );
		if ( 0 === $existing_count && 'yes' === get_option( 'sab_auto_set_default', 'yes' ) ) {
			$is_default = 1;
		}

		if ( 1 === $is_default ) {
			self::clear_defaults( $user_id, $address_type );
		}

		// Normalize postal_code / postcode
		$postal_code = $data['postal_code'] ?? ( $data['postcode'] ?? '' );

		$insert_data = array(
			'user_id'      => $user_id,
			'address_type' => $address_type,
			'nickname'     => sanitize_text_field( $data['nickname'] ?? '' ),
			'first_name'   => sanitize_text_field( $data['first_name'] ?? '' ),
			'last_name'    => sanitize_text_field( $data['last_name'] ?? '' ),
			'company'      => sanitize_text_field( $data['company'] ?? '' ),
			'phone'        => sanitize_text_field( $data['phone'] ?? '' ),
			'email'        => sanitize_email( $data['email'] ?? '' ),
			'address_1'    => sanitize_text_field( $data['address_1'] ?? '' ),
			'address_2'    => sanitize_text_field( $data['address_2'] ?? '' ),
			'city'         => sanitize_text_field( $data['city'] ?? '' ),
			'state'        => sanitize_text_field( $data['state'] ?? '' ),
			'country'      => sanitize_text_field( $data['country'] ?? '' ),
			'postal_code'  => sanitize_text_field( $postal_code ),
			'is_default'   => $is_default,
			'created_at'   => current_time( 'mysql' ),
			'updated_at'   => current_time( 'mysql' ),
		);

		$formats = array(
			'%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s'
		);

		$inserted = $wpdb->insert( $table_name, $insert_data, $formats );
		if ( false === $inserted ) {
			return false;
		}

		$new_id = $wpdb->insert_id;

		/**
		 * Fires after an address is successfully added to the database.
		 *
		 * @param int   $new_id      The newly created address ID.
		 * @param array $insert_data The sanitized data inserted into the database.
		 */
		do_action( 'sab_address_added', $new_id, $insert_data );

		return $new_id;
	}

	/**
	 * Update an existing address.
	 *
	 * @param int   $id Address ID.
	 * @param array $data Address data.
	 * @return bool
	 */
	public static function update_address( $id, $data ) {
		global $wpdb;

		$table_name = self::table_name();
		$id         = absint( $id );

		$existing = self::get_address( $id );
		if ( ! $existing ) {
			return false;
		}

		$user_id      = isset( $data['user_id'] ) ? absint( $data['user_id'] ) : (int) $existing->user_id;
		$address_type = isset( $data['address_type'] ) && in_array( $data['address_type'], array( 'billing', 'shipping' ), true )
			? $data['address_type']
			: $existing->address_type;

		$is_default = isset( $data['is_default'] ) ? ( ! empty( $data['is_default'] ) ? 1 : 0 ) : (int) $existing->is_default;

		if ( 1 === $is_default ) {
			self::clear_defaults( $user_id, $address_type, $id );
		}

		$postal_code = $data['postal_code'] ?? ( $data['postcode'] ?? $existing->postal_code );

		$update_data = array(
			'user_id'      => $user_id,
			'address_type' => $address_type,
			'nickname'     => isset( $data['nickname'] ) ? sanitize_text_field( $data['nickname'] ) : $existing->nickname,
			'first_name'   => isset( $data['first_name'] ) ? sanitize_text_field( $data['first_name'] ) : $existing->first_name,
			'last_name'    => isset( $data['last_name'] ) ? sanitize_text_field( $data['last_name'] ) : $existing->last_name,
			'company'      => isset( $data['company'] ) ? sanitize_text_field( $data['company'] ) : $existing->company,
			'phone'        => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : $existing->phone,
			'email'        => isset( $data['email'] ) ? sanitize_email( $data['email'] ) : $existing->email,
			'address_1'    => isset( $data['address_1'] ) ? sanitize_text_field( $data['address_1'] ) : $existing->address_1,
			'address_2'    => isset( $data['address_2'] ) ? sanitize_text_field( $data['address_2'] ) : $existing->address_2,
			'city'         => isset( $data['city'] ) ? sanitize_text_field( $data['city'] ) : $existing->city,
			'state'        => isset( $data['state'] ) ? sanitize_text_field( $data['state'] ) : $existing->state,
			'country'      => isset( $data['country'] ) ? sanitize_text_field( $data['country'] ) : $existing->country,
			'postal_code'  => sanitize_text_field( $postal_code ),
			'is_default'   => $is_default,
			'updated_at'   => current_time( 'mysql' ),
		);

		$formats = array(
			'%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s'
		);

		$result = $wpdb->update( $table_name, $update_data, array( 'id' => $id ), $formats, array( '%d' ) );
		if ( false !== $result ) {
			/**
			 * Fires after an address is successfully updated in the database.
			 *
			 * @param int   $id          The updated address ID.
			 * @param array $update_data The sanitized update data.
			 */
			do_action( 'sab_address_updated', $id, $update_data );
			return true;
		}

		return false;
	}

	/**
	 * Delete an address.
	 *
	 * @param int $id Address ID.
	 * @return bool
	 */
	public static function delete_address( $id ) {
		global $wpdb;

		$table_name = self::table_name();
		$id         = absint( $id );

		$address = self::get_address( $id );
		if ( ! $address ) {
			return false;
		}

		$user_id      = (int) $address->user_id;
		$address_type = $address->address_type;
		$was_default  = ( (int) $address->is_default === 1 );

		// Check if it was considered default via fallback if not explicitly flagged
		if ( ! $was_default ) {
			$current_default = self::get_default_address( $user_id, $address_type );
			if ( $current_default && (int) $current_default->id === $id ) {
				$was_default = true;
			}
		}

		$result = $wpdb->delete( $table_name, array( 'id' => $id ), array( '%d' ) );

		if ( $result ) {
			$new_default_id = 0;

			// If deleted address was default, automatically assign the next eligible address as new default
			// using the existing address sort order (recently used order)
			if ( $was_default ) {
				$remaining_sorted = self::get_addresses_sorted( $user_id, $address_type );
				if ( ! empty( $remaining_sorted ) ) {
					// Select the first available address according to that existing sort order
					$next_default = $remaining_sorted[0];
					self::set_default( $next_default->id, $user_id, $address_type );
					$new_default_id = (int) $next_default->id;
				}
			}

			self::$last_new_default_id = $new_default_id;

			/**
			 * Fires after an address is successfully deleted from the database.
			 *
			 * @param int         $id             The deleted address ID.
			 * @param object      $address        The address object before deletion.
			 * @param int|false   $new_default_id The new default address ID if re-assigned, or false.
			 */
			do_action( 'sab_address_deleted', $id, $address, $new_default_id );
			return true;
		}

		return false;
	}

	/**
	 * Set an address as the default for its type.
	 *
	 * @param int    $address_id Address ID.
	 * @param int    $user_id User ID.
	 * @param string $address_type Address type.
	 * @return bool
	 */
	public static function set_default( $address_id, $user_id, $address_type ) {
		global $wpdb;

		$table_name = self::table_name();
		$address_id = absint( $address_id );
		$user_id    = absint( $user_id );
		if ( ! $user_id || ! $address_id ) {
			return false;
		}

		self::clear_defaults( $user_id, $address_type, $address_id );

		$result = $wpdb->update(
			$table_name,
			array(
				'is_default' => 1,
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'id'      => $address_id,
				'user_id' => $user_id,
			),
			array( '%d', '%s' ),
			array( '%d', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Clear default flag for all addresses of a user and type.
	 *
	 * @param int    $user_id User ID.
	 * @param string $address_type Address type.
	 * @param int    $exclude_id Optional ID to exclude from unsetting.
	 */
	public static function clear_defaults( $user_id, $address_type, $exclude_id = 0 ) {
		global $wpdb;

		$table_name = self::table_name();
		$user_id    = absint( $user_id );
		if ( ! $user_id ) {
			return;
		}

		if ( $exclude_id > 0 ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table_name} SET is_default = 0 WHERE user_id = %d AND address_type = %s AND id != %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user_id,
					$address_type,
					$exclude_id
				)
			);
		} else {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table_name} SET is_default = 0 WHERE user_id = %d AND address_type = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user_id,
					$address_type
				)
			);
		}
	}

	/**
	 * Get the default address for a user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $address_type Address type.
	 * @return object|null
	 */
	public static function get_default_address( $user_id, $address_type = 'shipping' ) {
		global $wpdb;

		$table_name = self::table_name();
		$user_id    = absint( $user_id );
		if ( ! $user_id ) {
			return null;
		}

		$default = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE user_id = %d AND address_type = %s AND is_default = 1 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$address_type
			)
		);

		if ( ! $default ) {
			// Fallback to most recent
			$default = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table_name} WHERE user_id = %d AND address_type = %s ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user_id,
					$address_type
				)
			);
		}

		return $default;
	}

	/**
	 * FR-09: Search saved addresses.
	 *
	 * @param int    $user_id User ID.
	 * @param string $search_term Query.
	 * @param string $address_type Optional address type.
	 * @return array
	 */
	public static function search_addresses( $user_id, $search_term, $address_type = '' ) {
		global $wpdb;

		$table_name = self::table_name();
		$user_id    = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}
		$like       = '%' . $wpdb->esc_like( sanitize_text_field( $search_term ) ) . '%';

		if ( ! empty( $address_type ) ) {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table_name} 
				WHERE user_id = %d 
				AND address_type = %s
				AND (
					nickname LIKE %s OR 
					first_name LIKE %s OR 
					last_name LIKE %s OR 
					company LIKE %s OR 
					city LIKE %s OR 
					state LIKE %s OR 
					postal_code LIKE %s OR 
					address_1 LIKE %s OR 
					phone LIKE %s
				)
				ORDER BY is_default DESC, id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$address_type,
				$like,
				$like,
				$like,
				$like,
				$like,
				$like,
				$like,
				$like,
				$like
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table_name} 
				WHERE user_id = %d 
				AND (
					nickname LIKE %s OR 
					first_name LIKE %s OR 
					last_name LIKE %s OR 
					company LIKE %s OR 
					city LIKE %s OR 
					state LIKE %s OR 
					postal_code LIKE %s OR 
					address_1 LIKE %s OR 
					phone LIKE %s
				)
				ORDER BY is_default DESC, id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$like,
				$like,
				$like,
				$like,
				$like,
				$like,
				$like,
				$like,
				$like
			);
		}

		return $wpdb->get_results( $sql );
	}

	/**
	 * Total address count for a user.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public static function get_address_count( $user_id ) {
		global $wpdb;

		$table_name = self::table_name();
		$user_id    = absint( $user_id );
		if ( ! $user_id ) {
			return 0;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			)
		);
	}

	/**
	 * Address count for a user by address type.
	 *
	 * @param int    $user_id User ID.
	 * @param string $address_type Address type.
	 * @return int
	 */
	public static function get_address_count_by_type( $user_id, $address_type ) {
		global $wpdb;

		$table_name = self::table_name();
		$user_id    = absint( $user_id );
		if ( ! $user_id ) {
			return 0;
		}

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d AND address_type = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$address_type
			)
		);
	}

	/**
	 * Check if a user can add a new address based on maximum limit setting.
	 * When type is specified, checks limit for that specific address type.
	 *
	 * @param int    $user_id User ID.
	 * @param string $address_type Optional address type ('billing' or 'shipping').
	 * @return bool True if user can add, false if maximum limit reached.
	 */
	public static function can_add_address( $user_id, $address_type = '' ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}

		$max_allowed = (int) get_option( 'sab_max_addresses', 0 );
		if ( $max_allowed <= 0 ) {
			return true;
		}

		if ( ! empty( $address_type ) ) {
			$address_type  = in_array( $address_type, array( 'billing', 'shipping' ), true ) ? $address_type : 'billing';
			$current_count = self::get_address_count_by_type( $user_id, $address_type );
			return $current_count < $max_allowed;
		}

		// If no type specified, check if either type can still receive addresses
		$billing_count  = self::get_address_count_by_type( $user_id, 'billing' );
		$shipping_count = self::get_address_count_by_type( $user_id, 'shipping' );

		return ( $billing_count < $max_allowed ) || ( $shipping_count < $max_allowed );
	}
}

