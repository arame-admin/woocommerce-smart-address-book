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
		add_action( 'wp_ajax_sab_preview_import_csv', array( $this, 'ajax_preview_import_csv' ) );
		add_action( 'wp_ajax_sab_confirm_import_addresses', array( $this, 'ajax_confirm_import_addresses' ) );
	}

	/**
	 * Return the CSV headers.
	 *
	 * @return array
	 */
	public static function get_csv_headers() {
		/**
		 * Filter the column headers used for CSV export and import.
		 *
		 * @param array $headers List of CSV header column keys.
		 */
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

		// Clean any previous output buffer to prevent blank lines before CSV
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=smart-address-book-' . gmdate( 'Y-m-d' ) . '.csv' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output  = fopen( 'php://output', 'w' );
		$headers = self::get_csv_headers();
		fputcsv( $output, $headers );

		foreach ( $addresses as $address ) {
			$row = array();
			foreach ( $headers as $col ) {
				$val         = (string) ( $address->$col ?? '' );
				$trimmed_val = ltrim( $val, " \t\n\r\0\x0B" );
				// Mitigate CSV formula injection in spreadsheet software (Point 16 & Issue 4)
				// Prevent bypasses using leading whitespace or control characters before formula triggers
				if ( '' !== $trimmed_val && in_array( $trimmed_val[0], array( '=', '+', '-', '@', "\t", "\r", '%' ), true ) ) {
					$val = "'" . $val;
				}
				$row[] = $val;
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

		if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'smart-address-book' ) ) );
		}

		$file = $_FILES['file'];

		// Check PHP upload error codes
		if ( isset( $file['error'] ) && UPLOAD_ERR_OK !== $file['error'] ) {
			$upload_errors = array(
				UPLOAD_ERR_INI_SIZE   => __( 'The uploaded file exceeds the upload_max_filesize directive in php.ini.', 'smart-address-book' ),
				UPLOAD_ERR_FORM_SIZE  => __( 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.', 'smart-address-book' ),
				UPLOAD_ERR_PARTIAL    => __( 'The uploaded file was only partially uploaded.', 'smart-address-book' ),
				UPLOAD_ERR_NO_FILE    => __( 'No file was uploaded.', 'smart-address-book' ),
				UPLOAD_ERR_NO_TMP_DIR => __( 'Missing a temporary folder on the server.', 'smart-address-book' ),
				UPLOAD_ERR_CANT_WRITE => __( 'Failed to write file to disk.', 'smart-address-book' ),
				UPLOAD_ERR_EXTENSION  => __( 'A PHP extension stopped the file upload.', 'smart-address-book' ),
			);
			$error_msg = $upload_errors[ $file['error'] ] ?? __( 'File upload error occurred.', 'smart-address-book' );
			wp_send_json_error( array( 'message' => $error_msg ) );
		}

		// Verify temporary path and uploaded file
		$tmp_name = $file['tmp_name'] ?? '';
		if ( empty( $tmp_name ) || ! is_uploaded_file( $tmp_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid or unreadable uploaded file.', 'smart-address-book' ) ) );
		}

		// Verify file extension
		$orig_name = sanitize_file_name( $file['name'] ?? '' );
		$extension = strtolower( pathinfo( $orig_name, PATHINFO_EXTENSION ) );
		if ( ! empty( $orig_name ) && ! in_array( $extension, array( 'csv', 'txt' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Please upload a valid CSV file (.csv).', 'smart-address-book' ) ) );
		}

		$user_id = get_current_user_id();
		// Pass raw temp file path without wp_unslash to preserve Windows directory slashes (Point 21)
		$result  = $this->process_csv_import_file( $user_id, $tmp_name );
		wp_send_json_success( $result );
	}

	/**
	 * AJAX endpoint to parse and preview addresses from uploaded CSV.
	 */
	public function ajax_preview_import_csv() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'smart-address-book' ) ), 401 );
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'sab_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'smart-address-book' ) ), 403 );
		}

		if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'smart-address-book' ) ) );
		}

		$file = $_FILES['file'];

		if ( isset( $file['error'] ) && UPLOAD_ERR_OK !== $file['error'] ) {
			$upload_errors = array(
				UPLOAD_ERR_INI_SIZE   => __( 'The uploaded file exceeds upload_max_filesize in php.ini.', 'smart-address-book' ),
				UPLOAD_ERR_FORM_SIZE  => __( 'The uploaded file exceeds MAX_FILE_SIZE.', 'smart-address-book' ),
				UPLOAD_ERR_PARTIAL    => __( 'The uploaded file was only partially uploaded.', 'smart-address-book' ),
				UPLOAD_ERR_NO_FILE    => __( 'No file was uploaded.', 'smart-address-book' ),
				UPLOAD_ERR_NO_TMP_DIR => __( 'Missing a temporary folder on the server.', 'smart-address-book' ),
				UPLOAD_ERR_CANT_WRITE => __( 'Failed to write file to disk.', 'smart-address-book' ),
				UPLOAD_ERR_EXTENSION  => __( 'A PHP extension stopped the file upload.', 'smart-address-book' ),
			);
			$error_msg = $upload_errors[ $file['error'] ] ?? __( 'File upload error occurred.', 'smart-address-book' );
			wp_send_json_error( array( 'message' => $error_msg ) );
		}

		$tmp_name = $file['tmp_name'] ?? '';
		if ( empty( $tmp_name ) || ! is_uploaded_file( $tmp_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid or unreadable uploaded file.', 'smart-address-book' ) ) );
		}

		$orig_name = sanitize_file_name( $file['name'] ?? '' );
		$extension = strtolower( pathinfo( $orig_name, PATHINFO_EXTENSION ) );
		if ( ! empty( $orig_name ) && ! in_array( $extension, array( 'csv', 'txt' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Please upload a valid CSV file (.csv).', 'smart-address-book' ) ) );
		}

		$user_id = get_current_user_id();
		$parsed  = $this->parse_csv_file( $tmp_name, $user_id );

		if ( ! $parsed['success'] ) {
			wp_send_json_error( array( 'message' => $parsed['message'] ) );
		}

		if ( empty( $parsed['addresses'] ) ) {
			$msg = __( 'No valid addresses found in the CSV file.', 'smart-address-book' );
			if ( ! empty( $parsed['skipped'] ) ) {
				$msg .= "\n" . implode( "\n", $parsed['skipped'] );
			}
			wp_send_json_error( array( 'message' => $msg ) );
		}

		wp_send_json_success( $parsed );
	}

	/**
	 * AJAX endpoint to import only selected addresses after customer confirmation.
	 */
	public function ajax_confirm_import_addresses() {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'smart-address-book' ) ), 401 );
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'sab_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'smart-address-book' ) ), 403 );
		}

		$raw_selected = $_POST['selected_addresses'] ?? '';
		if ( is_string( $raw_selected ) ) {
			$selected = json_decode( wp_unslash( $raw_selected ), true );
		} else {
			$selected = (array) $raw_selected;
		}

		if ( empty( $selected ) || ! is_array( $selected ) ) {
			wp_send_json_error( array( 'message' => __( 'No addresses selected for import.', 'smart-address-book' ) ) );
		}

		$user_id          = get_current_user_id();
		$imported         = 0;
		$skipped          = 0;
		$errors           = array();
		$limit_hit        = array(
			'billing'  => false,
			'shipping' => false,
		);
		$skipped_by_limit = array(
			'billing'  => 0,
			'shipping' => 0,
		);
		$limits           = array(
			'billing'  => function_exists( 'sab_get_address_limit' ) ? sab_get_address_limit( 'billing' ) : 3,
			'shipping' => function_exists( 'sab_get_address_limit' ) ? sab_get_address_limit( 'shipping' ) : 3,
		);

		foreach ( $selected as $idx => $addr_data ) {
			if ( ! is_array( $addr_data ) ) {
				continue;
			}

			// Strictly resolve address type ('billing' or 'shipping')
			$raw_type = strtolower( trim( (string) ( $addr_data['address_type'] ?? 'billing' ) ) );
			if ( strpos( $raw_type, 'ship' ) !== false ) {
				$address_type = 'shipping';
			} elseif ( strpos( $raw_type, 'bill' ) !== false ) {
				$address_type = 'billing';
			} else {
				$address_type = in_array( $raw_type, array( 'billing', 'shipping' ), true ) ? $raw_type : 'billing';
			}
			$addr_data['address_type'] = $address_type;

			$nickname = ! empty( $addr_data['nickname'] ) ? $addr_data['nickname'] : ( '#' . ( $idx + 1 ) );

			// Check if address book feature is enabled for this type
			if ( 'yes' !== get_option( 'sab_enable_' . $address_type, 'yes' ) ) {
				/* translators: 1: Address nickname, 2: Address type */
				$errors[] = sprintf( __( '%1$s skipped: %2$s address book is currently disabled.', 'smart-address-book' ), $nickname, ucfirst( $address_type ) );
				$skipped++;
				continue;
			}

			$addr_data['user_id'] = $user_id;

			$sanitized                 = SAB_Address::sanitize( $addr_data );
			$sanitized['user_id']      = $user_id;
			$sanitized['address_type'] = $address_type;
			$validation                = SAB_Address::validate_address( $sanitized );

			if ( is_wp_error( $validation ) ) {
				$errors[] = sprintf( __( '%s skipped: %s', 'smart-address-book' ), $nickname, $validation->get_error_message() );
				$skipped++;
				continue;
			}

			// Check for duplicate of an existing address for this customer (skip duplicate; do not count as newly imported)
			$duplicate_id = SAB_Database::find_duplicate_address( $user_id, $address_type, $sanitized );
			if ( $duplicate_id ) {
				$errors[] = sprintf( __( '%s skipped: this address already exists in your address book.', 'smart-address-book' ), $nickname );
				$skipped++;
				continue;
			}

			// Check independent limit for this address type
			$type_limit = $limits[ $address_type ];
			if ( $type_limit > 0 && ! SAB_Database::can_add_address( $user_id, $address_type ) ) {
				$limit_hit[ $address_type ] = true;
				$skipped_by_limit[ $address_type ]++;
				/* translators: 1: Address nickname, 2: Maximum allowed, 3: Address type */
				$errors[] = sprintf( __( '%1$s skipped: maximum limit of %2$d saved %3$s addresses reached.', 'smart-address-book' ), $nickname, $type_limit, $address_type );
				$skipped++;
				continue;
			}

			$inserted_id = SAB_Database::add_address( $sanitized );
			if ( $inserted_id && (int) $inserted_id !== (int) $duplicate_id ) {
				$imported++;
			} else {
				$errors[] = sprintf( __( '%s could not be saved to database.', 'smart-address-book' ), $nickname );
				$skipped++;
			}
		}

		// Build customer-facing summary message
		$is_pro        = function_exists( 'sab_is_pro_active' ) ? sab_is_pro_active() : false;
		$limit_details = array();

		if ( $skipped_by_limit['billing'] > 0 ) {
			$limit_details[] = sprintf(
				_n(
					'%d billing address was skipped because you have reached your maximum billing address limit.',
					'%d billing addresses were skipped because you have reached your maximum billing address limit.',
					$skipped_by_limit['billing'],
					'smart-address-book'
				),
				$skipped_by_limit['billing']
			);
		}

		if ( $skipped_by_limit['shipping'] > 0 ) {
			$limit_details[] = sprintf(
				_n(
					'%d shipping address was skipped because you have reached your maximum shipping address limit.',
					'%d shipping addresses were skipped because you have reached your maximum shipping address limit.',
					$skipped_by_limit['shipping'],
					'smart-address-book'
				),
				$skipped_by_limit['shipping']
			);
		}

		$summary_message = sprintf(
			__( 'Import completed: %1$d imported, %2$d skipped.', 'smart-address-book' ),
			$imported,
			$skipped
		);

		if ( ! empty( $limit_details ) ) {
			$summary_message .= ' ' . implode( ' ', $limit_details );
			if ( ! $is_pro ) {
				$summary_message .= ' ' . __( 'Upgrade to Pro to save unlimited addresses.', 'smart-address-book' );
			}
		}

		wp_send_json_success(
			array(
				'imported' => $imported,
				'skipped'  => $skipped,
				'message'  => $summary_message,
				'errors'   => $errors,
			)
		);
	}

	/**
	 * Parse CSV file and extract addresses with validation.
	 *
	 * @param string $file_path Path to uploaded CSV file.
	 * @param int    $user_id   User ID.
	 * @return array
	 */
	public static function parse_csv_file( $file_path, $user_id ) {
		$file_path = wp_normalize_path( $file_path );

		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'Cannot read CSV file: file does not exist.', 'smart-address-book' ),
			);
		}

		// Enable auto-detect line endings for legacy Mac / Windows CSVs
		$orig_auto_detect = ini_get( 'auto_detect_line_endings' );
		ini_set( 'auto_detect_line_endings', '1' );

		$handle = @fopen( $file_path, 'r' );
		if ( false === $handle ) {
			ini_set( 'auto_detect_line_endings', $orig_auto_detect );
			return array(
				'success' => false,
				'message' => __( 'Cannot read CSV file.', 'smart-address-book' ),
			);
		}

		// Detect and strip UTF-8 BOM if present at the start of the file
		$bom = fread( $handle, 3 );
		if ( "\xEF\xBB\xBF" !== $bom ) {
			rewind( $handle );
		}

		// Find the header row (skip any leading blank lines or whitespace)
		$raw_headers = false;
		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			if ( is_array( $row ) && ! empty( array_filter( array_map( 'trim', $row ) ) ) ) {
				$raw_headers = $row;
				break;
			}
		}

		if ( empty( $raw_headers ) ) {
			fclose( $handle );
			ini_set( 'auto_detect_line_endings', $orig_auto_detect );
			return array(
				'success' => false,
				'message' => __( 'CSV file is empty or missing headers.', 'smart-address-book' ),
			);
		}

		// Strip UTF-8 BOM from first header column if present
		if ( isset( $raw_headers[0] ) ) {
			$raw_headers[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $raw_headers[0] );
		}

		// Map common header variations to expected fields
		$header_aliases = array(
			'postcode'         => 'postal_code',
			'post code'        => 'postal_code',
			'postal code'      => 'postal_code',
			'postal_code'      => 'postal_code',
			'zip'              => 'postal_code',
			'zipcode'          => 'postal_code',
			'zip code'         => 'postal_code',
			'zip_code'         => 'postal_code',
			'first name'       => 'first_name',
			'first_name'       => 'first_name',
			'last name'        => 'last_name',
			'last_name'        => 'last_name',
			'company name'     => 'company',
			'company'          => 'company',
			'phone number'     => 'phone',
			'phone'            => 'phone',
			'email address'    => 'email',
			'email'            => 'email',
			'street address'   => 'address_1',
			'street address 1' => 'address_1',
			'street address 2' => 'address_2',
			'street'           => 'address_1',
			'street 1'         => 'address_1',
			'street 2'         => 'address_2',
			'address'          => 'address_1',
			'address 1'        => 'address_1',
			'address 2'        => 'address_2',
			'address1'         => 'address_1',
			'address2'         => 'address_2',
			'address_1'        => 'address_1',
			'address_2'        => 'address_2',
			'type'             => 'address_type',
			'address type'     => 'address_type',
			'address_type'     => 'address_type',
			'address-type'     => 'address_type',
			'addresstype'      => 'address_type',
			'addr type'        => 'address_type',
			'addr_type'        => 'address_type',
			'default'          => 'is_default',
			'is default'       => 'is_default',
			'is_default'       => 'is_default',
		);

		$headers = array();
		foreach ( $raw_headers as $idx => $hdr ) {
			// Normalize header aliases (Point 11)
			$normalized_header = strtolower( trim( (string) $hdr ) );
			$normalized_header = preg_replace( '/\s+/', ' ', $normalized_header );
			if ( isset( $header_aliases[ $normalized_header ] ) ) {
				$normalized_header = $header_aliases[ $normalized_header ];
			}
			$headers[ $idx ] = $normalized_header;
		}

		$valid_addresses = array();
		$skipped_rows    = array();
		$row_num         = 1;

		$user_email = '';
		$user_obj   = get_userdata( $user_id );
		if ( $user_obj && ! empty( $user_obj->user_email ) ) {
			$user_email = $user_obj->user_email;
		}

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$row_num++;

			// Skip empty lines
			if ( ! is_array( $row ) || empty( array_filter( array_map( 'trim', $row ) ) ) ) {
				continue;
			}

			$data = array();
			foreach ( $headers as $idx => $key ) {
				if ( ! empty( $key ) ) {
					$data[ $key ] = isset( $row[ $idx ] ) ? trim( (string) $row[ $idx ] ) : '';
				}
			}

			// Fallback check for address type if key was slightly different
			if ( empty( $data['address_type'] ) ) {
				foreach ( array( 'address type', 'type', 'addr type', 'addresstype', 'address-type', 'address_type' ) as $type_alias ) {
					if ( ! empty( $data[ $type_alias ] ) ) {
						$data['address_type'] = $data[ $type_alias ];
						break;
					}
				}
			}

			// Strictly normalize address_type
			$raw_type = strtolower( trim( (string) ( $data['address_type'] ?? '' ) ) );
			if ( strpos( $raw_type, 'ship' ) !== false ) {
				$data['address_type'] = 'shipping';
			} elseif ( strpos( $raw_type, 'bill' ) !== false ) {
				$data['address_type'] = 'billing';
			} elseif ( in_array( $raw_type, array( 'billing', 'shipping' ), true ) ) {
				$data['address_type'] = $raw_type;
			} else {
				$data['address_type'] = 'shipping';
			}
			$data['address_type_label'] = ( 'billing' === $data['address_type'] ) ? __( 'Billing', 'smart-address-book' ) : __( 'Shipping', 'smart-address-book' );

			// Enforce feature settings per type
			if ( 'yes' !== get_option( 'sab_enable_' . $data['address_type'], 'yes' ) ) {
				/* translators: 1: Row number, 2: Address type */
				$skipped_rows[] = sprintf( __( 'Row %1$d skipped: %2$s address book is currently disabled.', 'smart-address-book' ), $row_num, ucfirst( $data['address_type'] ) );
				continue;
			}

			// Normalize is_default
			if ( isset( $data['is_default'] ) ) {
				$data['is_default'] = in_array( strtolower( (string) $data['is_default'] ), array( '1', 'true', 'yes', 'default' ), true ) ? 1 : 0;
			} else {
				$data['is_default'] = 0;
			}

			// Associate with currently logged-in customer
			$data['user_id'] = $user_id;

			// If email is empty, fallback to customer user_email
			if ( empty( $data['email'] ) && ! empty( $user_email ) ) {
				$data['email'] = $user_email;
			}

			// Generate default nickname if empty
			if ( empty( $data['nickname'] ) ) {
				$count            = count( $valid_addresses ) + 1;
				$data['nickname'] = sprintf( '%s #%d', ucfirst( $data['address_type'] ), $count );
			}

			$sanitized            = SAB_Address::sanitize( $data );
			$sanitized['user_id'] = $user_id;
			$validation           = SAB_Address::validate_address( $sanitized );

			if ( is_wp_error( $validation ) ) {
				/* translators: 1: Row number, 2: Error message */
				$skipped_rows[] = sprintf( __( 'Row %1$d skipped: %2$s', 'smart-address-book' ), $row_num, $validation->get_error_message() );
				continue;
			}

			$sanitized['row_num'] = $row_num;
			$valid_addresses[]    = $sanitized;
		}

		fclose( $handle );
		ini_set( 'auto_detect_line_endings', $orig_auto_detect );

		return array(
			'success'       => true,
			'addresses'     => $valid_addresses,
			'total_valid'   => count( $valid_addresses ),
			'skipped_count' => count( $skipped_rows ),
			'skipped'       => $skipped_rows,
		);
	}

	/**
	 * Parse and import addresses directly from a CSV file (Point 21).
	 *
	 * @param int    $user_id User ID.
	 * @param string $file_path Path to uploaded CSV file.
	 * @return array Results summary.
	 */
	public function process_csv_import_file( $user_id, $file_path ) {
		$parsed = $this->parse_csv_file( $file_path, $user_id );
		if ( ! $parsed['success'] ) {
			return array(
				'imported' => 0,
				'skipped'  => 0,
				'errors'   => array( $parsed['message'] ),
			);
		}

		$valid_addresses  = $parsed['addresses'];
		$errors           = $parsed['skipped'];
		$imported         = 0;
		$skipped          = $parsed['skipped_count'];
		$limit_hit        = array(
			'billing'  => false,
			'shipping' => false,
		);
		$skipped_by_limit = array(
			'billing'  => 0,
			'shipping' => 0,
		);
		$limits           = array(
			'billing'  => function_exists( 'sab_get_address_limit' ) ? sab_get_address_limit( 'billing' ) : 3,
			'shipping' => function_exists( 'sab_get_address_limit' ) ? sab_get_address_limit( 'shipping' ) : 3,
		);

		foreach ( $valid_addresses as $sanitized ) {
			// Strictly resolve address type ('billing' or 'shipping')
			$raw_type = strtolower( trim( (string) ( $sanitized['address_type'] ?? 'billing' ) ) );
			if ( strpos( $raw_type, 'ship' ) !== false ) {
				$address_type = 'shipping';
			} elseif ( strpos( $raw_type, 'bill' ) !== false ) {
				$address_type = 'billing';
			} else {
				$address_type = in_array( $raw_type, array( 'billing', 'shipping' ), true ) ? $raw_type : 'billing';
			}
			$sanitized['address_type'] = $address_type;

			if ( 'yes' !== get_option( 'sab_enable_' . $address_type, 'yes' ) ) {
				/* translators: 1: Row number, 2: Address type */
				$errors[] = sprintf( __( 'Row %1$d skipped: %2$s address book is currently disabled.', 'smart-address-book' ), $sanitized['row_num'], ucfirst( $address_type ) );
				$skipped++;
				continue;
			}

			// Check for duplicate of an existing address (skip duplicate; do not count as newly imported)
			$duplicate_id = SAB_Database::find_duplicate_address( $user_id, $address_type, $sanitized );
			if ( $duplicate_id ) {
				/* translators: %d: Row number */
				$errors[] = sprintf( __( 'Row %d skipped: this address already exists in your address book.', 'smart-address-book' ), $sanitized['row_num'] );
				$skipped++;
				continue;
			}

			// Check independent limit for this address type
			$type_limit = $limits[ $address_type ];
			if ( $type_limit > 0 && ! SAB_Database::can_add_address( $user_id, $address_type ) ) {
				$limit_hit[ $address_type ] = true;
				$skipped_by_limit[ $address_type ]++;
				/* translators: 1: Row number, 2: Maximum allowed, 3: Address type */
				$errors[] = sprintf( __( 'Row %1$d skipped: maximum limit of %2$d saved %3$s addresses reached.', 'smart-address-book' ), $sanitized['row_num'], $type_limit, $address_type );
				$skipped++;
				continue;
			}

			$inserted_id = SAB_Database::add_address( $sanitized );
			if ( $inserted_id && (int) $inserted_id !== (int) $duplicate_id ) {
				$imported++;
			} else {
				/* translators: %d: Row number */
				$errors[] = sprintf( __( 'Row %d could not be saved to database.', 'smart-address-book' ), $sanitized['row_num'] );
				$skipped++;
			}
		}

		// Build customer-facing summary message
		$is_pro        = function_exists( 'sab_is_pro_active' ) ? sab_is_pro_active() : false;
		$limit_details = array();

		if ( $skipped_by_limit['billing'] > 0 ) {
			$limit_details[] = sprintf(
				_n(
					'%d billing address was skipped because you have reached your maximum billing address limit.',
					'%d billing addresses were skipped because you have reached your maximum billing address limit.',
					$skipped_by_limit['billing'],
					'smart-address-book'
				),
				$skipped_by_limit['billing']
			);
		}

		if ( $skipped_by_limit['shipping'] > 0 ) {
			$limit_details[] = sprintf(
				_n(
					'%d shipping address was skipped because you have reached your maximum shipping address limit.',
					'%d shipping addresses were skipped because you have reached your maximum shipping address limit.',
					$skipped_by_limit['shipping'],
					'smart-address-book'
				),
				$skipped_by_limit['shipping']
			);
		}

		$summary_message = sprintf(
			__( 'Import completed: %1$d imported, %2$d skipped.', 'smart-address-book' ),
			$imported,
			$skipped
		);

		if ( ! empty( $limit_details ) ) {
			$summary_message .= ' ' . implode( ' ', $limit_details );
			if ( ! $is_pro ) {
				$summary_message .= ' ' . __( 'Upgrade to Pro to save unlimited addresses.', 'smart-address-book' );
			}
		}

		return array(
			'imported' => $imported,
			'skipped'  => $skipped,
			'message'  => $summary_message,
			'errors'   => $errors,
		);
	}

	/**
	 * Backward compatibility alias for process_csv_import_file (Point 21).
	 *
	 * @param int    $user_id   User ID.
	 * @param string $file_path File path.
	 * @return array
	 */
	public function import_csv( $user_id, $file_path ) {
		return $this->process_csv_import_file( $user_id, $file_path );
	}
}

