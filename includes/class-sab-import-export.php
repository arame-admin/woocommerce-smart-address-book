<?php
/**
 * CSV Import and Export for Smart Address Book.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAB_Import_Export {

	/**
	 * Constructor: Register export & import actions.
	 */
	public function __construct() {
		add_action( 'wp_ajax_sab_export_addresses', array( $this, 'ajax_export_addresses' ) );
		add_action( 'wp_ajax_sab_import_addresses', array( $this, 'ajax_import_addresses' ) );
	}

	/**
	 * Return the CSV headers.
	 *
	 * @return array
	 */
	public static function get_csv_headers() {
		return apply_filters(
			'sab_csv_headers',
			array(
				'nickname',
				'address_type',
				'first_name',
				'last_name',
				'company',
				'phone',
				'email',
				'address_1',
				'address_2',
				'city',
				'state',
				'country',
				'postal_code',
				'is_default',
			)
		);
	}

	/**
	 * AJAX endpoint to handle CSV export.
	 */
	public function ajax_export_addresses() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in to export addresses.', 'smart-address-book' ), '', array( 'response' => 401 ) );
		}

		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'sab_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'smart-address-book' ), '', array( 'response' => 403 ) );
		}

		$user_id   = get_current_user_id();
		$addresses = SAB_Database::get_addresses( $user_id, '', 'id' );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=smart-address-book-' . gmdate( 'Y-m-d' ) . '.csv' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output  = fopen( 'php://output', 'w' );
		$headers = self::get_csv_headers();
		fputcsv( $output, $headers );

		foreach ( $addresses as $addr ) {
			$row = array();
			foreach ( $headers as $col ) {
				$row[] = $addr->$col ?? '';
			}
			fputcsv( $output, $row );
		}

		fclose( $output );
		exit;
	}

	/**
	 * AJAX endpoint to handle CSV import.
	 */
	public function ajax_import_addresses() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'smart-address-book' ) ), 401 );
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'sab_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'smart-address-book' ) ), 403 );
		}

		if ( empty( $_FILES['file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'smart-address-book' ) ) );
		}

		$user_id   = get_current_user_id();
		$file_path = sanitize_text_field( wp_unslash( $_FILES['file']['tmp_name'] ) );

		$result = $this->import_csv( $user_id, $file_path );
		wp_send_json_success( $result );
	}

	/**
	 * Parse and import addresses from a CSV file.
	 *
	 * @param int    $user_id User ID.
	 * @param string $file_path Path to uploaded CSV file.
	 * @return array Results summary.
	 */
	public function import_csv( $user_id, $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return array(
				'imported' => 0,
				'skipped'  => 0,
				'errors'   => array( __( 'Cannot read CSV file.', 'smart-address-book' ) ),
			);
		}

		$handle = fopen( $file_path, 'r' );
		if ( false === $handle ) {
			return array(
				'imported' => 0,
				'skipped'  => 0,
				'errors'   => array( __( 'Could not open CSV file.', 'smart-address-book' ) ),
			);
		}

		$headers = fgetcsv( $handle );
		if ( ! $headers ) {
			fclose( $handle );
			return array(
				'imported' => 0,
				'skipped'  => 0,
				'errors'   => array( __( 'CSV file is empty.', 'smart-address-book' ) ),
			);
		}

		// Normalize headers
		$headers = array_map( 'trim', array_map( 'strtolower', $headers ) );

		$imported = 0;
		$skipped  = 0;
		$errors   = array();
		$row_num  = 1;

		$max_allowed = (int) get_option( 'sab_max_addresses', 0 );

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$row_num++;

			if ( empty( array_filter( $row ) ) ) {
				continue;
			}

			// Check limits
			if ( $max_allowed > 0 ) {
				$current_count = SAB_Database::get_address_count( $user_id );
				if ( $current_count >= $max_allowed ) {
					/* translators: %d: Row number */
					$errors[] = sprintf( __( 'Row %d skipped: address limit reached.', 'smart-address-book' ), $row_num );
					$skipped++;
					continue;
				}
			}

			$data = array();
			foreach ( $headers as $idx => $key ) {
				$data[ $key ] = $row[ $idx ] ?? '';
			}

			$data['user_id'] = $user_id;
			$sanitized       = SAB_Address::sanitize( $data );
			$validation      = SAB_Address::validate( $sanitized );

			if ( is_wp_error( $validation ) ) {
				/* translators: 1: Row number, 2: Error message */
				$errors[] = sprintf( __( 'Row %1$d skipped: %2$s', 'smart-address-book' ), $row_num, $validation->get_error_message() );
				$skipped++;
				continue;
			}

			$inserted_id = SAB_Database::add_address( $sanitized );
			if ( $inserted_id ) {
				$imported++;
			} else {
				/* translators: %d: Row number */
				$errors[] = sprintf( __( 'Row %d could not be saved to database.', 'smart-address-book' ), $row_num );
				$skipped++;
			}
		}

		fclose( $handle );

		return array(
			'imported' => $imported,
			'skipped'  => $skipped,
			'errors'   => $errors,
		);
	}
}

