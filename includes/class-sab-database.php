<?php
/**
 * Database abstraction layer for Smart Address Book.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAB_Database {

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
	 * Retrieve addresses for a user with optional filtering and sorting.
	 *
	 * @param int    $user_id User ID.
	 * @param string $type Optional address type ('billing', 'shipping', or empty for both).
	 * @param string $orderby Ordering method ('default', 'recent', 'id').
	 * @return array
	 */
	public static function get_addresses( $user_id, $type = '', $orderby = 'default' ) {
		global $wpdb;

		$table_name = self::table_name();
		$user_id    = absint( $user_id );

		if ( 'default' === $orderby ) {
			return self::get_addresses_sorted( $user_id, $type );
		}

		if ( ! empty( $type ) ) {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE user_id = %d AND address_type = %s ORDER BY is_default DESC, id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$type
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE user_id = %d ORDER BY is_default DESC, id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			);
		}

		$results = $wpdb->get_results( $sql );
		return apply_filters( 'sab_get_addresses', $results, $user_id, $type, $orderby );
	}

	/**
	 * FR-10 Address Sorting:
	 * 1. Default Address
	 * 2. Recently Used
	 * 3. Remaining Addresses
	 *
	 * @param int    $user_id User ID.
	 * @param string $type Address type.
	 * @return array
	 */
	public static function get_addresses_sorted( $user_id, $type = '' ) {
		global $wpdb;

		$table_name = self::table_name();
		$user_id    = absint( $user_id );

		if ( ! empty( $type ) ) {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE user_id = %d AND address_type = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$type
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

		$recently_used_ids = self::get_recently_used_ids( $user_id );

		$defaults       = array();
		$recently_used  = array();
		$remaining      = array();

		foreach ( $all as $address ) {
			if ( (int) $address->is_default === 1 ) {
				$defaults[] = $address;
			} elseif ( in_array( (int) $address->id, $recently_used_ids, true ) ) {
				$recently_used[] = $address;
			} else {
				$remaining[] = $address;
			}
		}

		// Sort recently_used array matching order of recently_used_ids
		if ( ! empty( $recently_used ) && ! empty( $recently_used_ids ) ) {
			usort(
				$recently_used,
				function ( $a, $b ) use ( $recently_used_ids ) {
					$pos_a = array_search( (int) $a->id, $recently_used_ids, true );
					$pos_b = array_search( (int) $b->id, $recently_used_ids, true );
					return $pos_a - $pos_b;
				}
			);
		}

		// Sort remaining by updated_at / id DESC
		usort(
			$remaining,
			function ( $a, $b ) {
				return (int) $b->id - (int) $a->id;
			}
		);

		$sorted = array_merge( $defaults, $recently_used, $remaining );
		return apply_filters( 'sab_get_addresses_sorted', $sorted, $user_id, $type );
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

			foreach ( $addresses as $addr ) {
				$target = ( 'billing' === $addr->address_type ) ? $order_billing : $order_shipping;
				if ( self::addresses_match( $addr, $target ) ) {
					if ( ! in_array( (int) $addr->id, $matched_ids, true ) ) {
						$matched_ids[] = (int) $addr->id;
					}
				}
			}
		}

		return $matched_ids;
	}

	/**
	 * Helper: Check if a saved address matches order address.
	 *
	 * @param object $addr Saved address.
	 * @param array  $order_addr Order address array.
	 * @return bool
	 */
	private static function addresses_match( $addr, $order_addr ) {
		if ( empty( $order_addr ) || empty( $order_addr['address_1'] ) ) {
			return false;
		}

		$addr_1_match = strtolower( trim( $addr->address_1 ?? '' ) ) === strtolower( trim( $order_addr['address_1'] ?? '' ) );
		$post_match   = strtolower( trim( $addr->postal_code ?? '' ) ) === strtolower( trim( $order_addr['postcode'] ?? '' ) );
		$city_match   = strtolower( trim( $addr->city ?? '' ) ) === strtolower( trim( $order_addr['city'] ?? '' ) );

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
	 * Insert a new address into the database.
	 *
	 * @param array $data Address data.
	 * @return int|false
	 */
	public static function add_address( $data ) {
		global $wpdb;

		$table_name = self::table_name();

		$user_id      = absint( $data['user_id'] ?? get_current_user_id() );
		$address_type = in_array( $data['address_type'] ?? '', array( 'billing', 'shipping' ), true ) ? $data['address_type'] : 'shipping';
		$is_default   = ! empty( $data['is_default'] ) ? 1 : 0;

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

		$result = $wpdb->delete( $table_name, array( 'id' => $id ), array( '%d' ) );

		if ( $result ) {
			// If deleted address was default, set next available address as default
			if ( (int) $address->is_default === 1 ) {
				$next = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT id FROM {$table_name} WHERE user_id = %d AND address_type = %s ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$address->user_id,
						$address->address_type
					)
				);
				if ( $next ) {
					self::set_default( $next->id, $address->user_id, $address->address_type );
				}
			}

			do_action( 'sab_address_deleted', $id, $address );
			return true;
		}

		return false;
	}

	/**
	 * Set an address as the default for its type.
	 *
	 * @param int    $id Address ID.
	 * @param int    $user_id User ID.
	 * @param string $type Address type.
	 * @return bool
	 */
	public static function set_default( $id, $user_id, $type ) {
		global $wpdb;

		$table_name = self::table_name();
		$id         = absint( $id );
		$user_id    = absint( $user_id );

		self::clear_defaults( $user_id, $type, $id );

		$result = $wpdb->update(
			$table_name,
			array(
				'is_default' => 1,
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'id'      => $id,
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
	 * @param string $type Address type.
	 * @param int    $exclude_id Optional ID to exclude from unsetting.
	 */
	public static function clear_defaults( $user_id, $type, $exclude_id = 0 ) {
		global $wpdb;

		$table_name = self::table_name();
		$user_id    = absint( $user_id );

		if ( $exclude_id > 0 ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table_name} SET is_default = 0 WHERE user_id = %d AND address_type = %s AND id != %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user_id,
					$type,
					$exclude_id
				)
			);
		} else {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table_name} SET is_default = 0 WHERE user_id = %d AND address_type = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user_id,
					$type
				)
			);
		}
	}

	/**
	 * Get the default address for a user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $type Address type.
	 * @return object|null
	 */
	public static function get_default_address( $user_id, $type = 'shipping' ) {
		global $wpdb;

		$table_name = self::table_name();
		$user_id    = absint( $user_id );

		$default = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE user_id = %d AND address_type = %s AND is_default = 1 LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$type
			)
		);

		if ( ! $default ) {
			// Fallback to most recent
			$default = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table_name} WHERE user_id = %d AND address_type = %s ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user_id,
					$type
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
	 * @param string $type Optional address type.
	 * @return array
	 */
	public static function search_addresses( $user_id, $search_term, $type = '' ) {
		global $wpdb;

		$table_name = self::table_name();
		$user_id    = absint( $user_id );
		$like       = '%' . $wpdb->esc_like( sanitize_text_field( $search_term ) ) . '%';

		if ( ! empty( $type ) ) {
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
				$type,
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
	 * @param string $type Address type.
	 * @return int
	 */
	public static function get_address_count_by_type( $user_id, $type ) {
		global $wpdb;

		$table_name = self::table_name();
		$user_id    = absint( $user_id );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d AND address_type = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				$type
			)
		);
	}
}

