<?php
/**
 * Address model and validation helpers for Smart Address Book.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAB_Address {

	/**
	 * Get definitions for all address fields.
	 *
	 * @return array
	 */
	public static function get_fields() {
		$validate_required = 'yes' === get_option( 'sab_validate_required_fields', 'yes' );

		/**
		 * Filter the field configuration definitions for smart address book addresses.
		 *
		 * @param array $fields Associative array of field configurations.
		 */
		return apply_filters(
			'sab_address_fields',
			array(
				'nickname'     => array(
					'label'       => __( 'Address Nickname / Label', 'smart-address-book' ),
					'placeholder' => __( 'e.g. Home, Office, Parents, Warehouse', 'smart-address-book' ),
					'required'    => false,
					'type'        => 'text',
				),
				'first_name'   => array(
					'label'    => __( 'First Name', 'smart-address-book' ),
					'required' => $validate_required,
					'type'     => 'text',
				),
				'last_name'    => array(
					'label'    => __( 'Last Name', 'smart-address-book' ),
					'required' => $validate_required,
					'type'     => 'text',
				),
				'company'      => array(
					'label'    => __( 'Company Name', 'smart-address-book' ),
					'required' => false,
					'type'     => 'text',
				),
				'phone'        => array(
					'label'    => __( 'Phone Number', 'smart-address-book' ),
					'required' => false,
					'type'     => 'tel',
				),
				'email'        => array(
					'label'    => __( 'Email Address', 'smart-address-book' ),
					'required' => $validate_required,
					'type'     => 'email',
				),
				'country'      => array(
					'label'    => __( 'Country / Region', 'smart-address-book' ),
					'required' => $validate_required,
					'type'     => 'country',
				),
				'address_1'    => array(
					'label'       => __( 'Street Address', 'smart-address-book' ),
					'placeholder' => __( 'House number and street name', 'smart-address-book' ),
					'required'    => $validate_required,
					'type'        => 'text',
				),
				'address_2'    => array(
					'label'       => __( 'Apartment, suite, unit, etc.', 'smart-address-book' ),
					'placeholder' => __( 'Apartment, suite, unit, etc. (optional)', 'smart-address-book' ),
					'required'    => false,
					'type'        => 'text',
				),
				'city'         => array(
					'label'    => __( 'Town / City', 'smart-address-book' ),
					'required' => $validate_required,
					'type'     => 'text',
				),
				'state'        => array(
					'label'    => __( 'State / County', 'smart-address-book' ),
					'required' => false,
					'type'     => 'state',
				),
				'postal_code'  => array(
					'label'    => __( 'Postcode / ZIP', 'smart-address-book' ),
					'required' => $validate_required,
					'type'     => 'text',
				),
			)
		);
	}

	/**
	 * Get required fields.
	 *
	 * @return array
	 */
	public static function get_required_fields() {
		$fields   = self::get_fields();
		$required = array();

		foreach ( $fields as $key => $config ) {
			if ( ! empty( $config['required'] ) ) {
				$required[] = $key;
			}
		}

		/**
		 * Filter the list of required address field keys.
		 *
		 * @param array $required Array of required field key strings.
		 */
		return apply_filters( 'sab_required_fields', $required );
	}

	/**
	 * FR-11 Address Validation:
	 * Postal Code, State, Country, Phone Number, Required Fields.
	 *
	 * @param array $data Raw address input.
	 * @return true|WP_Error
	 */
	public static function validate( $data ) {
		$errors = new WP_Error();

		$validate_required = 'yes' === get_option( 'sab_validate_required_fields', 'yes' );
		$validate_postcode = 'yes' === get_option( 'sab_validate_postal_code', 'yes' );
		$validate_phone    = 'yes' === get_option( 'sab_validate_phone', 'yes' );

		// 1. Required fields check
		if ( $validate_required ) {
			$required_fields = self::get_required_fields();
			$field_configs   = self::get_fields();

			foreach ( $required_fields as $field ) {
				$val = $data[ $field ] ?? ( 'postal_code' === $field ? ( $data['postcode'] ?? '' ) : '' );
				if ( '' === trim( (string) $val ) ) {
					$label = $field_configs[ $field ]['label'] ?? ucfirst( str_replace( '_', ' ', $field ) );
					/* translators: %s: Field label */
					$errors->add( 'empty_' . $field, sprintf( __( '%s is a required field.', 'smart-address-book' ), $label ) );
				}
			}
		}

		// 2. Country check
		$country = sanitize_text_field( $data['country'] ?? '' );
		if ( ! empty( $country ) && function_exists( 'WC' ) ) {
			$allowed_countries = WC()->countries->get_allowed_countries();
			if ( ! array_key_exists( $country, $allowed_countries ) ) {
				$errors->add( 'invalid_country', __( 'Please choose a valid country / region.', 'smart-address-book' ) );
			}
		}

		// 3. State validation based on country
		$state = sanitize_text_field( $data['state'] ?? '' );
		if ( ! empty( $country ) && function_exists( 'WC' ) ) {
			$states = WC()->countries->get_states( $country );
			if ( ! empty( $states ) && is_array( $states ) ) {
				if ( empty( $state ) ) {
					if ( $validate_required ) {
						$errors->add( 'empty_state', __( 'State / County is required for this country.', 'smart-address-book' ) );
					}
				} elseif ( ! array_key_exists( $state, $states ) && ! in_array( $state, $states, true ) ) {
					$errors->add( 'invalid_state', __( 'Please enter a valid State / County.', 'smart-address-book' ) );
				}
			}
		}

		// 4. Postal Code validation
		$postcode = sanitize_text_field( $data['postal_code'] ?? ( $data['postcode'] ?? '' ) );
		if ( $validate_postcode && ! empty( $postcode ) && ! empty( $country ) && function_exists( 'WC' ) ) {
			$valid_postcode = WC_Validation::is_postcode( $postcode, $country );
			if ( ! $valid_postcode ) {
				$errors->add( 'invalid_postcode', __( 'Please enter a valid Postcode / ZIP.', 'smart-address-book' ) );
			}
		}

		// 5. Phone validation
		$phone = sanitize_text_field( $data['phone'] ?? '' );
		if ( $validate_phone && ! empty( $phone ) ) {
			if ( function_exists( 'WC_Validation' ) && method_exists( 'WC_Validation', 'is_phone' ) ) {
				if ( ! WC_Validation::is_phone( $phone ) ) {
					$errors->add( 'invalid_phone', __( 'Please enter a valid phone number.', 'smart-address-book' ) );
				}
			} else {
				// Fallback regex: allow optional +, digits, spaces, hyphens, parentheses (7 to 20 chars)
				if ( ! preg_match( '/^[0-9\-\+\(\)\s]{7,25}$/', $phone ) ) {
					$errors->add( 'invalid_phone', __( 'Please enter a valid phone number.', 'smart-address-book' ) );
				}
			}
		}

		// 6. Email validation
		$raw_email = trim( (string) ( $data['email'] ?? '' ) );
		$email     = sanitize_email( $raw_email );
		if ( empty( $raw_email ) ) {
			if ( $validate_required ) {
				if ( ! $errors->get_error_message( 'empty_email' ) ) {
					$errors->add( 'empty_email', __( 'Email Address is a required field.', 'smart-address-book' ) );
				}
			}
		} elseif ( ! is_email( $email ) ) {
			$errors->add( 'invalid_email', __( 'Please enter a valid email address.', 'smart-address-book' ) );
		}

		if ( $errors->has_errors() ) {
			return $errors;
		}

		return true;
	}

	/**
	 * Sanitize raw address input.
	 *
	 * @param array $data Raw input.
	 * @return array
	 */
	public static function sanitize( $data ) {
		$sanitized = array();

		$text_fields = array(
			'nickname',
			'first_name',
			'last_name',
			'company',
			'phone',
			'address_1',
			'address_2',
			'city',
			'state',
			'country',
			'postal_code',
			'postcode',
		);

		foreach ( $text_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$sanitized[ $field ] = sanitize_text_field( wp_unslash( $data[ $field ] ) );
			}
		}

		if ( isset( $data['email'] ) ) {
			$sanitized['email'] = sanitize_email( wp_unslash( $data['email'] ) );
		}

		if ( isset( $data['address_type'] ) ) {
			$sanitized['address_type'] = in_array( $data['address_type'], array( 'billing', 'shipping' ), true )
				? $data['address_type']
				: 'shipping';
		}

		if ( isset( $data['is_default'] ) ) {
			$sanitized['is_default'] = ! empty( $data['is_default'] ) ? 1 : 0;
		}

		if ( isset( $data['user_id'] ) ) {
			$sanitized['user_id'] = absint( $data['user_id'] );
		}

		// Ensure postal_code is set
		if ( empty( $sanitized['postal_code'] ) && ! empty( $sanitized['postcode'] ) ) {
			$sanitized['postal_code'] = $sanitized['postcode'];
		}

		// Normalize state code if full name was passed
		if ( ! empty( $sanitized['country'] ) && ! empty( $sanitized['state'] ) && function_exists( 'WC' ) && WC()->countries ) {
			$country_states = WC()->countries->get_states( $sanitized['country'] );
			if ( ! empty( $country_states ) && is_array( $country_states ) ) {
				$code = array_search( $sanitized['state'], $country_states, true );
				if ( false !== $code ) {
					$sanitized['state'] = (string) $code;
				}
			}
		}

		return $sanitized;
	}

	/**
	 * Format an address object or array for display.
	 *
	 * @param object|array $address Address data.
	 * @return string HTML formatted address.
	 */
	public static function format_address( $address ) {
		if ( is_object( $address ) ) {
			$address = (array) $address;
		}

		$formatted_args = array(
			'first_name' => $address['first_name'] ?? '',
			'last_name'  => $address['last_name'] ?? '',
			'company'    => $address['company'] ?? '',
			'address_1'  => $address['address_1'] ?? '',
			'address_2'  => $address['address_2'] ?? '',
			'city'       => $address['city'] ?? '',
			'state'      => $address['state'] ?? '',
			'postcode'   => $address['postal_code'] ?? ( $address['postcode'] ?? '' ),
			'country'    => $address['country'] ?? '',
		);

		if ( function_exists( 'WC' ) && isset( WC()->countries ) ) {
			return WC()->countries->get_formatted_address( $formatted_args );
		}

		// Fallback formatting
		$lines = array_filter(
			array(
				trim( ( $formatted_args['first_name'] . ' ' . $formatted_args['last_name'] ) ),
				$formatted_args['company'],
				$formatted_args['address_1'],
				$formatted_args['address_2'],
				trim( $formatted_args['city'] . ', ' . $formatted_args['state'] . ' ' . $formatted_args['postcode'] ),
				$formatted_args['country'],
			)
		);

		return implode( '<br/>', array_map( 'esc_html', $lines ) );
	}
}

