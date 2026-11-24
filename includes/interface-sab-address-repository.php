<?php
/**
 * Address Repository Interface for Smart Address Book.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface SAB_Address_Repository_Interface {

	/**
	 * Get the table name.
	 *
	 * @return string
	 */
	public function get_table_name();

	/**
	 * Retrieve a single address by ID.
	 *
	 * @param int $id Address ID.
	 * @return object|null
	 */
	public function get_address( $id );

	/**
	 * Retrieve addresses for a user with optional filtering and sorting.
	 *
	 * @param int    $user_id      User ID.
	 * @param string $address_type Address type.
	 * @param string $orderby      Ordering method.
	 * @return array
	 */
	public function get_addresses( $user_id, $address_type = '', $orderby = 'default' );

	/**
	 * Retrieve addresses sorted by default, recent, remaining.
	 *
	 * @param int    $user_id      User ID.
	 * @param string $address_type Address type.
	 * @return array
	 */
	public function get_addresses_sorted( $user_id, $address_type = '' );

	/**
	 * Get default address for a user.
	 *
	 * @param int    $user_id      User ID.
	 * @param string $address_type Address type.
	 * @return object|null
	 */
	public function get_default_address( $user_id, $address_type = 'shipping' );

	/**
	 * Add a new address.
	 *
	 * @param array $data Address data.
	 * @return int|false
	 */
	public function add_address( $data );

	/**
	 * Update an existing address.
	 *
	 * @param int   $id   Address ID.
	 * @param array $data Address data.
	 * @return bool
	 */
	public function update_address( $id, $data );

	/**
	 * Delete an address.
	 *
	 * @param int $id Address ID.
	 * @return bool
	 */
	public function delete_address( $id );

	/**
	 * Set an address as default.
	 *
	 * @param int    $id           Address ID.
	 * @param int    $user_id      User ID.
	 * @param string $address_type Address type.
	 * @return bool
	 */
	public function set_default( $id, $user_id, $address_type = 'shipping' );

	/**
	 * Clear default status for user addresses.
	 *
	 * @param int    $user_id      User ID.
	 * @param string $address_type Address type.
	 * @return bool
	 */
	public function clear_defaults( $user_id, $address_type );

	/**
	 * Get total address count for user.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function get_address_count( $user_id );

	/**
	 * Get address count by type for user.
	 *
	 * @param int    $user_id      User ID.
	 * @param string $address_type Address type.
	 * @return int
	 */
	public function get_address_count_by_type( $user_id, $address_type );

	/**
	 * Check if a user can add a new address.
	 *
	 * @param int    $user_id      User ID.
	 * @param string $address_type Address type.
	 * @return bool
	 */
	public function can_add_address( $user_id, $address_type = 'shipping' );

	/**
	 * Search addresses for a user.
	 *
	 * @param int    $user_id      User ID.
	 * @param string $term         Search term.
	 * @param string $address_type Address type.
	 * @return array
	 */
	public function search_addresses( $user_id, $term, $address_type = '' );

	/**
	 * Find an existing duplicate address.
	 *
	 * @param int    $user_id      User ID.
	 * @param string $address_type Address type.
	 * @param array  $data         Address data.
	 * @return int Address ID if found, 0 otherwise.
	 */
	public function find_duplicate_address( $user_id, $address_type, $data );

	/**
	 * Update last_used_at timestamp.
	 *
	 * @param int $id Address ID.
	 * @return bool
	 */
	public function update_last_used( $id );

	/**
	 * Delete all addresses for a user upon user deletion.
	 *
	 * @param int $user_id User ID.
	 * @return int Number of deleted rows.
	 */
	public function delete_addresses_by_user( $user_id );
}
