<?php
/**
 * GDPR & Privacy Compliance for Smart Address Book.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAB_Privacy {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * Register personal data exporter.
	 *
	 * @param array $exporters Exporters list.
	 * @return array
	 */
	public function register_exporter( $exporters ) {
		$exporters['smart-address-book'] = array(
			'exporter_friendly_name' => __( 'Smart Address Book Saved Addresses', 'smart-address-book' ),
			'callback'               => array( $this, 'export_user_data' ),
		);
		return $exporters;
	}

	/**
	 * Export user personal data.
	 *
	 * @param string $email_address User email.
	 * @param int    $page Page number.
	 * @return array
	 */
	public function export_user_data( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$addresses = SAB_Database::get_addresses( $user->ID, '', 'id' );
		$data_to_export = array();

		foreach ( $addresses as $addr ) {
			$item_data = array(
				array(
					'name'  => __( 'Nickname', 'smart-address-book' ),
					'value' => $addr->nickname,
				),
				array(
					'name'  => __( 'Type', 'smart-address-book' ),
					'value' => ucfirst( $addr->address_type ),
				),
				array(
					'name'  => __( 'First Name', 'smart-address-book' ),
					'value' => $addr->first_name,
				),
				array(
					'name'  => __( 'Last Name', 'smart-address-book' ),
					'value' => $addr->last_name,
				),
				array(
					'name'  => __( 'Company', 'smart-address-book' ),
					'value' => $addr->company,
				),
				array(
					'name'  => __( 'Address 1', 'smart-address-book' ),
					'value' => $addr->address_1,
				),
				array(
					'name'  => __( 'Address 2', 'smart-address-book' ),
					'value' => $addr->address_2,
				),
				array(
					'name'  => __( 'City', 'smart-address-book' ),
					'value' => $addr->city,
				),
				array(
					'name'  => __( 'State', 'smart-address-book' ),
					'value' => $addr->state,
				),
				array(
					'name'  => __( 'Country', 'smart-address-book' ),
					'value' => $addr->country,
				),
				array(
					'name'  => __( 'Postcode', 'smart-address-book' ),
					'value' => $addr->postal_code,
				),
				array(
					'name'  => __( 'Phone', 'smart-address-book' ),
					'value' => $addr->phone,
				),
				array(
					'name'  => __( 'Email', 'smart-address-book' ),
					'value' => $addr->email,
				),
				array(
					'name'  => __( 'Is Default', 'smart-address-book' ),
					'value' => $addr->is_default ? __( 'Yes', 'smart-address-book' ) : __( 'No', 'smart-address-book' ),
				),
			);

			$data_to_export[] = array(
				'group_id'    => 'smart_address_book',
				'group_label' => __( 'Smart Address Book Saved Addresses', 'smart-address-book' ),
				'item_id'     => 'sab-address-' . $addr->id,
				'data'        => $item_data,
			);
		}

		return array(
			'data' => $data_to_export,
			'done' => true,
		);
	}

	/**
	 * Register personal data eraser.
	 *
	 * @param array $erasers Erasers list.
	 * @return array
	 */
	public function register_eraser( $erasers ) {
		$erasers['smart-address-book'] = array(
			'eraser_friendly_name' => __( 'Smart Address Book Saved Addresses', 'smart-address-book' ),
			'callback'              => array( $this, 'erase_user_data' ),
		);
		return $erasers;
	}

	/**
	 * Erase user personal data.
	 *
	 * @param string $email_address User email.
	 * @param int    $page Page number.
	 * @return array
	 */
	public function erase_user_data( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'items_removed'  => 0,
				'items_retained' => 0,
				'messages'       => array(),
				'done'           => true,
			);
		}

		global $wpdb;
		$table_name = SAB_Database::table_name();

		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user->ID
			)
		);

		$wpdb->delete( $table_name, array( 'user_id' => $user->ID ), array( '%d' ) );

		return array(
			'items_removed'  => $count,
			'items_retained' => 0,
			'messages'       => array( __( 'Removed customer saved addresses from Smart Address Book.', 'smart-address-book' ) ),
			'done'           => true,
		);
	}
}

