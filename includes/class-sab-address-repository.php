<?php
/**
 * Address Repository implementation for Smart Address Book.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'interface-sab-address-repository.php';

class SAB_Address_Repository implements SAB_Address_Repository_Interface {

	/**
	 * Address types constants.
	 */
	const TYPE_BILLING  = 'billing';
	const TYPE_SHIPPING = 'shipping';

	/**
	 * Stores the ID of the new default address assigned during the last delete operation, if any.
	 *
	 * @var int
	 */
	public $last_new_default_id = 0;

	/**
	 * Get the table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'sab_addresses';
	}

	/**
	 * Retrieve a single address by ID.
	 *
	 * @param int $id Address ID.
	 * @return object|null
	 */
	public function get_address( $id ) {
		global $wpdb;

		$id = absint( $id );
		if ( ! $id ) {
			return null;
		}

		$table_name = $this->get_table_name();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			)
		);
	}

	/**
	 * Retrieve addresses for a user with optional filtering and sorting.
	 *
	 * @param int    $user_id      User ID.
	 * @param string $address_type Optional address type ('billing', 'shipping', or empty for both).
	 * @param string $orderby      Ordering method ('default', 'recent', 'id').
	 * @return array
	 */
	public function get_addresses( $user_id, $address_type = '', $orderby = 'default' ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}

		if ( 'default' === $orderby ) {
			return $this->get_addresses_sorted( $user_id, $address_type );
		}

		$table_name = $this->get_table_name();

		if ( ! empty( $address_type ) ) {
			$address_type = in_array( $address_type, array( self::TYPE_BILLING, self::TYPE_SHIPPING ), true ) ? $address_type : self::TYPE_SHIPPING;
			$sql          = $wpdb->prepare(
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
	 * Address Sorting:
	 * 1. Default Address
	 * 2. Recently Used
	 * 3. Remaining Addresses
	 *
	 * @param int    $user_id      User ID.
	 * @param string $address_type Address type.
	 * @return array
	 */
	public function get_addresses_sorted( $user_id, $address_type = '' ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}

		$table_name = $this->get_table_name();

		if ( ! empty( $address_type ) ) {
			$address_type = in_array( $address_type, array( self::TYPE_BILLING, self::TYPE_SHIPPING ), true ) ? $address_type : self::TYPE_SHIPPING;
			$sql          = $wpdb->prepare(
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

		foreach ( $all as $address ) {
			$address->is_recently_used = false;
		}

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

			usort(
				$recently_used,
				function ( $a, $b ) {
					return strtotime( $b->last_used_at ) - strtotime( $a->last_used_at );
				}
			);
		} else {
			$recently_used_ids = $this->get_recently_used_ids( $user_id );
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

		if ( ! empty( $recently_used ) ) {
			$recently_used[0]->is_recently_used = true;
		}

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
	public function get_recently_used_ids( $user_id ) {
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
		$addresses   = $this->get_raw_addresses( $user_id );

		foreach ( $orders as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			$order_billing  = $order->get_address( 'billing' );
			$order_shipping = $order->get_address( 'shipping' );

			foreach ( $addresses as $address ) {
				$target = ( self::TYPE_BILLING === $address->address_type ) ? $order_billing : $order_shipping;
				if ( $this->addresses_match( $address, $target ) ) {
					if ( ! in_array( (int) $address->id, $matched_ids, true ) ) {
						$matched_ids[] = (int) $address->id;
					}
				}
			}
		}

		return $matched_ids;
	}

	/**
	 * Check if a saved address matches order address.
	 *
	 * @param object $address       Saved address.
	 * @param array  $order_address Order address array.
	 * @return bool
	 */
	protected function addresses_match( $address, $order_address ) {
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
	protected function get_raw_addresses( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}
		$table_name = $this->get_table_name();
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id
			)
		);
	}

	/**
	 * Find an existing duplicate address for a user.
	 * Optimized to be sargable without wrapping column names in LOWER(TRIM(...)).
	 *
	 * @param int    $user_id      User ID.
	 * @param string $address_type 'billing' or 'shipping'.
	 * @param array  $data         Address data.
	 * @return int Address ID if found, 0 otherwise.
	 */
	public function find_duplicate_address( $user_id, $address_type, $data ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return 0;
		}

		$table_name   = $this->get_table_name();
		$address_type = in_array( $address_type, array( self::TYPE_BILLING, self::TYPE_SHIPPING ), true ) ? $address_type : self::TYPE_SHIPPING;

		$first_name  = trim( sanitize_text_field( $data['first_name'] ?? '' ) );
		$last_name   = trim( sanitize_text_field( $data['last_name'] ?? '' ) );
		$address_1   = trim( sanitize_text_field( $data['address_1'] ?? '' ) );
		$city        = trim( sanitize_text_field( $data['city'] ?? '' ) );
		$postal_code = trim( sanitize_text_field( $data['postal_code'] ?? ( $data['postcode'] ?? '' ) ) );
		$country     = strtoupper( substr( trim( sanitize_text_field( $data['country'] ?? '' ) ), 0, 2 ) );

		if ( empty( $first_name ) || empty( $last_name ) || empty( $address_1 ) ) {
			return 0;
		}

		// Sargable query: Avoid functions on columns to allow index usage.
		// Standard MySQL database collations are case-insensitive.
		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table_name}
				 WHERE user_id = %d
				   AND address_type = %s
				   AND first_name = %s
				   AND last_name = %s
				   AND address_1 = %s
				   AND city = %s
				   AND postal_code = %s
				   AND country = %s
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
	public function add_address( $data ) {
		global $wpdb;

		$table_name = $this->get_table_name();

		$user_id      = absint( $data['user_id'] ?? get_current_user_id() );
		$address_type = in_array( $data['address_type'] ?? '', array( self::TYPE_BILLING, self::TYPE_SHIPPING ), true ) ? $data['address_type'] : self::TYPE_SHIPPING;
		$is_default   = ! empty( $data['is_default'] ) ? 1 : 0;

		if ( ! $user_id ) {
			return false;
		}

		// Check for existing duplicate address for this user to prevent duplicates
		$duplicate_id = $this->find_duplicate_address( $user_id, $address_type, $data );
		if ( $duplicate_id ) {
			$this->update_last_used( $duplicate_id );
			return $duplicate_id;
		}

		// Enforce maximum address limit
		if ( ! $this->can_add_address( $user_id, $address_type ) ) {
			return false;
		}

		// If user has no existing address of this type, make this the default automatically
		$existing_count = $this->get_address_count_by_type( $user_id, $address_type );
		if ( 0 === $existing_count && 'yes' === get_option( 'sab_auto_set_default', 'yes' ) ) {
			$is_default = 1;
		}

		if ( 1 === $is_default ) {
			$this->clear_defaults( $user_id, $address_type );
		}

		$postal_code = trim( sanitize_text_field( $data['postal_code'] ?? ( $data['postcode'] ?? '' ) ) );
		$country     = strtoupper( substr( trim( sanitize_text_field( $data['country'] ?? '' ) ), 0, 2 ) );

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
			'country'      => $country,
			'postal_code'  => $postal_code,
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
	 * @param int   $id   Address ID.
	 * @param array $data Address data.
	 * @return bool
	 */
	public function update_address( $id, $data ) {
		global $wpdb;

		$table_name = $this->get_table_name();
		$id         = absint( $id );

		$existing = $this->get_address( $id );
		if ( ! $existing ) {
			return false;
		}

		$user_id      = isset( $data['user_id'] ) ? absint( $data['user_id'] ) : (int) $existing->user_id;
		$address_type = isset( $data['address_type'] ) && in_array( $data['address_type'], array( self::TYPE_BILLING, self::TYPE_SHIPPING ), true )
			? $data['address_type']
			: $existing->address_type;

		$is_default = isset( $data['is_default'] ) ? ( ! empty( $data['is_default'] ) ? 1 : 0 ) : (int) $existing->is_default;

		if ( 1 === $is_default ) {
			$this->clear_defaults( $user_id, $address_type, $id );
		}

		$postal_code = isset( $data['postal_code'] ) ? $data['postal_code'] : ( $data['postcode'] ?? $existing->postal_code );
		$country     = isset( $data['country'] ) ? strtoupper( substr( trim( sanitize_text_field( $data['country'] ) ), 0, 2 ) ) : $existing->country;

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
			'country'      => $country,
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
	public function delete_address( $id ) {
		global $wpdb;

		$table_name = $this->get_table_name();
		$id         = absint( $id );

		$address = $this->get_address( $id );
		if ( ! $address ) {
			return false;
		}

		$user_id      = absint( $address->user_id );
		$address_type = $address->address_type;
		$was_default  = ( (int) $address->is_default === 1 );

		if ( ! $was_default ) {
			$current_default = $this->get_default_address( $user_id, $address_type );
			if ( $current_default && (int) $current_default->id === $id ) {
				$was_default = true;
			}
		}

		$result = $wpdb->delete( $table_name, array( 'id' => $id ), array( '%d' ) );

		if ( $result ) {
			$new_default_id = 0;

			if ( $was_default ) {
				$remaining_sorted = $this->get_addresses_sorted( $user_id, $address_type );
				if ( ! empty( $remaining_sorted ) ) {
					$next_default = $remaining_sorted[0];
					$this->set_default( $next_default->id, $user_id, $address_type );
					$new_default_id = (int) $next_default->id;
				}
			}

			$this->last_new_default_id = $new_default_id;

			/**
			 * Fires after an address is successfully deleted from the database.
			 *
			 * @param int       $id             The deleted address ID.
			 * @param object    $address        The address object before deletion.
			 * @param int|false $new_default_id The new default address ID if re-assigned, or false.
			 */
			do_action( 'sab_address_deleted', $id, $address, $new_default_id );
			return true;
		}

		return false;
	}

	/**
	 * Set an address as default.
	 *
	 * @param int    $id           Address ID.
	 * @param int    $user_id      User ID.
	 * @param string $address_type Address type.
	 * @return bool
	 */
	public function set_default( $id, $user_id, $address_type = 'shipping' ) {
		global $wpdb;

		$table_name   = $this->get_table_name();
		$id           = absint( $id );
		$user_id      = absint( $user_id );
		$address_type = in_array( $address_type, array( self::TYPE_BILLING, self::TYPE_SHIPPING ), true ) ? $address_type : self::TYPE_SHIPPING;

		if ( ! $user_id || ! $id ) {
			return false;
		}

		$this->clear_defaults( $user_id, $address_type, $id );

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
	 * Clear default status for user addresses.
	 *
	 * @param int    $user_id      User ID.
	 * @param string $address_type Address type.
	 * @param int    $exclude_id   Optional ID to exclude.
	 * @return bool
	 */
	public function clear_defaults( $user_id, $address_type, $exclude_id = 0 ) {
		global $wpdb;

		$table_name   = $this->get_table_name();
		$user_id      = absint( $user_id );
		$address_type = in_array( $address_type, array( self::TYPE_BILLING, self::TYPE_SHIPPING ), true ) ? $address_type : self::TYPE_SHIPPING;
		$exclude_id   = absint( $exclude_id );

		if ( ! $user_id ) {
			return false;
		}

		if ( $exclude_id > 0 ) {
			$result = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table_name} SET is_default = 0 WHERE user_id = %d AND address_type = %s AND id != %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user_id,
					$address_type,
					$exclude_id
				)
			);
		} else {
			$result = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table_name} SET is_default = 0 WHERE user_id = %d AND address_type = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user_id,
					$address_type
				)
			);
		}

		return false !== $result;
	}

	/**
	 * Get default address for a user.
	 *
	 * @param int    $user_id      User ID.
	 * @param string $address_type Address type.
	 * @return object|null
	 */
	public function get_default_address( $user_id, $address_type = 'shipping' ) {
		global $wpdb;

		$table_name   = $this->get_table_name();
		$user_id      = absint( $user_id );
		$address_type = in_array( $address_type, array( self::TYPE_BILLING, self::TYPE_SHIPPING ), true ) ? $address_type : self::TYPE_SHIPPING;

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
	 * Get total address count for user.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function get_address_count( $user_id ) {
		global $wpdb;

		$table_name = $this->get_table_name();
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
	 * Get address count by type for user.
	 *
	 * @param int    $user_id      User ID.
	 * @param string $address_type Address type.
	 * @return int
	 */
	public function get_address_count_by_type( $user_id, $address_type ) {
		global $wpdb;

		$table_name   = $this->get_table_name();
		$user_id      = absint( $user_id );
		$address_type = in_array( $address_type, array( self::TYPE_BILLING, self::TYPE_SHIPPING ), true ) ? $address_type : self::TYPE_SHIPPING;

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
	 *
	 * @param int    $user_id      User ID.
	 * @param string $address_type Optional address type ('billing' or 'shipping').
	 * @return bool
	 */
	public function can_add_address( $user_id, $address_type = '' ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}

		$max_allowed = function_exists( 'sab_get_address_limit' ) ? sab_get_address_limit( $address_type ) : (int) get_option( 'sab_max_addresses', 3 );
		if ( $max_allowed <= 0 ) {
			return true;
		}

		if ( ! empty( $address_type ) ) {
			$address_type  = in_array( $address_type, array( self::TYPE_BILLING, self::TYPE_SHIPPING ), true ) ? $address_type : self::TYPE_BILLING;
			$current_count = $this->get_address_count_by_type( $user_id, $address_type );
			return $current_count < $max_allowed;
		}

		$billing_count  = $this->get_address_count_by_type( $user_id, self::TYPE_BILLING );
		$shipping_count = $this->get_address_count_by_type( $user_id, self::TYPE_SHIPPING );

		$billing_limit  = function_exists( 'sab_get_address_limit' ) ? sab_get_address_limit( self::TYPE_BILLING ) : 3;
		$shipping_limit = function_exists( 'sab_get_address_limit' ) ? sab_get_address_limit( self::TYPE_SHIPPING ) : 3;

		$can_billing  = ( $billing_limit <= 0 ) || ( $billing_count < $billing_limit );
		$can_shipping = ( $shipping_limit <= 0 ) || ( $shipping_count < $shipping_limit );

		return ( $can_billing || $can_shipping );
	}

	/**
	 * Search addresses for a user.
	 *
	 * @param int    $user_id      User ID.
	 * @param string $term         Search term.
	 * @param string $address_type Optional address type.
	 * @return array
	 */
	public function search_addresses( $user_id, $term, $address_type = '' ) {
		global $wpdb;

		$table_name = $this->get_table_name();
		$user_id    = absint( $user_id );
		if ( ! $user_id ) {
			return array();
		}
		$like = '%' . $wpdb->esc_like( sanitize_text_field( $term ) ) . '%';

		if ( ! empty( $address_type ) ) {
			$address_type = in_array( $address_type, array( self::TYPE_BILLING, self::TYPE_SHIPPING ), true ) ? $address_type : self::TYPE_SHIPPING;
			$sql          = $wpdb->prepare(
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
	 * Update last_used_at timestamp.
	 *
	 * @param int $id Address ID.
	 * @return bool
	 */
	public function update_last_used( $id ) {
		global $wpdb;

		$id = absint( $id );
		if ( ! $id ) {
			return false;
		}

		$table_name = $this->get_table_name();

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
	 * Delete all addresses for a user upon user deletion (Point 4).
	 *
	 * @param int $user_id User ID.
	 * @return int Number of deleted rows.
	 */
	public function delete_addresses_by_user( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return 0;
		}

		$table_name = $this->get_table_name();
		$deleted    = $wpdb->delete( $table_name, array( 'user_id' => $user_id ), array( '%d' ) );

		if ( false !== $deleted && $deleted > 0 ) {
			do_action( 'sab_user_addresses_deleted', $user_id, $deleted );
		}

		return ( false !== $deleted ) ? (int) $deleted : 0;
	}
}
