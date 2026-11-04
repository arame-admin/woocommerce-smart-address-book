<?php
/**
 * Customer My Account - Add / Edit Address Form Template.
 *
 * @var object|null $address Address object if editing.
 * @var string      $action  'add' or 'edit'.
 * @var int         $user_id User ID.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_edit      = ( 'edit' === $action && ! empty( $address ) );
$address_id   = $is_edit ? (int) $address->id : 0;
$back_url     = wc_get_account_endpoint_url( 'sab-address-book' );
$default_type = sanitize_text_field( $_GET['type'] ?? 'shipping' );
$type         = $is_edit ? $address->address_type : $default_type;

$country  = $is_edit ? $address->country : ( WC()->customer ? WC()->customer->get_shipping_country() : 'US' );
$state    = $is_edit ? $address->state : ( WC()->customer ? WC()->customer->get_shipping_state() : '' );

$countries = WC()->countries->get_allowed_countries();
$states    = WC()->countries->get_states( $country );
?>

<div class="sab-address-form-wrapper">

	<div class="sab-form-header">
		<h3>
			<?php echo $is_edit ? esc_html__( 'Edit Address', 'smart-address-book' ) : esc_html__( 'Add New Address', 'smart-address-book' ); ?>
		</h3>
		<a href="<?php echo esc_url( $back_url ); ?>" class="button sab-back-link">
			&larr; <?php esc_html_e( 'Back to Address Book', 'smart-address-book' ); ?>
		</a>
	</div>

	<form method="post" action="" class="woocommerce-address-fields sab-address-form" id="sab-address-form">
		<?php wp_nonce_field( 'sab_address_form', 'sab_nonce' ); ?>
		<input type="hidden" name="address_id" value="<?php echo esc_attr( $address_id ); ?>" />

		<div class="woocommerce-address-fields__field-wrapper">

			<!-- Address Type -->
			<p class="form-row form-row-wide" id="sab_address_type_field">
				<label for="sab_address_type">
					<?php esc_html_e( 'Address Type', 'smart-address-book' ); ?> <abbr class="required" title="required">*</abbr>
				</label>
				<select name="address_type" id="sab_address_type" class="select">
					<option value="shipping" <?php selected( $type, 'shipping' ); ?>><?php esc_html_e( 'Shipping Address', 'smart-address-book' ); ?></option>
					<option value="billing" <?php selected( $type, 'billing' ); ?>><?php esc_html_e( 'Billing Address', 'smart-address-book' ); ?></option>
				</select>
			</p>

			<!-- Address Nickname -->
			<?php if ( 'yes' === get_option( 'sab_enable_nickname', 'yes' ) ) : ?>
			<p class="form-row form-row-wide" id="sab_nickname_field">
				<label for="sab_nickname">
					<?php esc_html_e( 'Address Nickname / Label', 'smart-address-book' ); ?> <span class="optional">(<?php esc_html_e( 'optional', 'smart-address-book' ); ?>)</span>
				</label>
				<input type="text" class="input-text" name="nickname" id="sab_nickname" placeholder="<?php esc_attr_e( 'e.g. Home, Office, Parents, Warehouse', 'smart-address-book' ); ?>" value="<?php echo esc_attr( $is_edit ? $address->nickname : '' ); ?>" />
			</p>
			<?php endif; ?>

			<!-- First & Last Name -->
			<p class="form-row form-row-first validate-required" id="sab_first_name_field">
				<label for="sab_first_name">
					<?php esc_html_e( 'First Name', 'smart-address-book' ); ?> <abbr class="required" title="required">*</abbr>
				</label>
				<input type="text" class="input-text" name="first_name" id="sab_first_name" required value="<?php echo esc_attr( $is_edit ? $address->first_name : '' ); ?>" />
			</p>

			<p class="form-row form-row-last validate-required" id="sab_last_name_field">
				<label for="sab_last_name">
					<?php esc_html_e( 'Last Name', 'smart-address-book' ); ?> <abbr class="required" title="required">*</abbr>
				</label>
				<input type="text" class="input-text" name="last_name" id="sab_last_name" required value="<?php echo esc_attr( $is_edit ? $address->last_name : '' ); ?>" />
			</p>

			<!-- Company -->
			<p class="form-row form-row-wide" id="sab_company_field">
				<label for="sab_company">
					<?php esc_html_e( 'Company Name', 'smart-address-book' ); ?> <span class="optional">(<?php esc_html_e( 'optional', 'smart-address-book' ); ?>)</span>
				</label>
				<input type="text" class="input-text" name="company" id="sab_company" value="<?php echo esc_attr( $is_edit ? $address->company : '' ); ?>" />
			</p>

			<!-- Country -->
			<p class="form-row form-row-wide validate-required" id="sab_country_field">
				<label for="sab_country">
					<?php esc_html_e( 'Country / Region', 'smart-address-book' ); ?> <abbr class="required" title="required">*</abbr>
				</label>
				<select name="country" id="sab_country" class="country_to_state country_select" required>
					<option value=""><?php esc_html_e( 'Select a country / region&hellip;', 'smart-address-book' ); ?></option>
					<?php foreach ( $countries as $code => $name ) : ?>
						<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $country, $code ); ?>><?php echo esc_html( $name ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>

			<!-- Street Address -->
			<p class="form-row form-row-wide validate-required" id="sab_address_1_field">
				<label for="sab_address_1">
					<?php esc_html_e( 'Street Address', 'smart-address-book' ); ?> <abbr class="required" title="required">*</abbr>
				</label>
				<input type="text" class="input-text" name="address_1" id="sab_address_1" placeholder="<?php esc_attr_e( 'House number and street name', 'smart-address-book' ); ?>" required value="<?php echo esc_attr( $is_edit ? $address->address_1 : '' ); ?>" />
			</p>

			<p class="form-row form-row-wide" id="sab_address_2_field">
				<label for="sab_address_2" class="screen-reader-text">
					<?php esc_html_e( 'Apartment, suite, unit, etc.', 'smart-address-book' ); ?>
				</label>
				<input type="text" class="input-text" name="address_2" id="sab_address_2" placeholder="<?php esc_attr_e( 'Apartment, suite, unit, etc. (optional)', 'smart-address-book' ); ?>" value="<?php echo esc_attr( $is_edit ? $address->address_2 : '' ); ?>" />
			</p>

			<!-- Town / City -->
			<p class="form-row form-row-wide validate-required" id="sab_city_field">
				<label for="sab_city">
					<?php esc_html_e( 'Town / City', 'smart-address-book' ); ?> <abbr class="required" title="required">*</abbr>
				</label>
				<input type="text" class="input-text" name="city" id="sab_city" required value="<?php echo esc_attr( $is_edit ? $address->city : '' ); ?>" />
			</p>

			<!-- State / County -->
			<p class="form-row form-row-first" id="sab_state_field">
				<label for="sab_state">
					<?php esc_html_e( 'State / County', 'smart-address-book' ); ?>
				</label>
				<?php if ( ! empty( $states ) && is_array( $states ) ) : ?>
					<select name="state" id="sab_state" class="state_select">
						<option value=""><?php esc_html_e( 'Select an option&hellip;', 'smart-address-book' ); ?></option>
						<?php foreach ( $states as $s_code => $s_name ) : ?>
							<option value="<?php echo esc_attr( $s_code ); ?>" <?php selected( $state, $s_code ); ?>><?php echo esc_html( $s_name ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<input type="text" class="input-text" name="state" id="sab_state" value="<?php echo esc_attr( $state ); ?>" />
				<?php endif; ?>
			</p>

			<!-- Postcode / ZIP -->
			<p class="form-row form-row-last validate-required" id="sab_postcode_field">
				<label for="sab_postal_code">
					<?php esc_html_e( 'Postcode / ZIP', 'smart-address-book' ); ?> <abbr class="required" title="required">*</abbr>
				</label>
				<input type="text" class="input-text" name="postal_code" id="sab_postal_code" required value="<?php echo esc_attr( $is_edit ? $address->postal_code : '' ); ?>" />
			</p>

			<!-- Phone -->
			<p class="form-row form-row-first" id="sab_phone_field">
				<label for="sab_phone">
					<?php esc_html_e( 'Phone Number', 'smart-address-book' ); ?> <span class="optional">(<?php esc_html_e( 'optional', 'smart-address-book' ); ?>)</span>
				</label>
				<input type="tel" class="input-text" name="phone" id="sab_phone" value="<?php echo esc_attr( $is_edit ? $address->phone : '' ); ?>" />
			</p>

			<!-- Email -->
			<p class="form-row form-row-last" id="sab_email_field">
				<label for="sab_email">
					<?php esc_html_e( 'Email Address', 'smart-address-book' ); ?> <span class="optional">(<?php esc_html_e( 'optional', 'smart-address-book' ); ?>)</span>
				</label>
				<input type="email" class="input-text" name="email" id="sab_email" value="<?php echo esc_attr( $is_edit ? $address->email : '' ); ?>" />
			</p>

			<!-- Default Checkbox -->
			<p class="form-row form-row-wide" id="sab_is_default_field">
				<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
					<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="is_default" id="sab_is_default" value="1" <?php checked( $is_edit && (int) $address->is_default === 1 ); ?> />
					<span><?php esc_html_e( 'Set as default address for this type', 'smart-address-book' ); ?></span>
				</label>
			</p>

		</div>

		<p class="form-row">
			<button type="submit" class="button button-primary" name="sab_save_address" value="1">
				<?php echo $is_edit ? esc_html__( 'Update Address', 'smart-address-book' ) : esc_html__( 'Save Address', 'smart-address-book' ); ?>
			</button>
			<a href="<?php echo esc_url( $back_url ); ?>" class="button sab-cancel-btn">
				<?php esc_html_e( 'Cancel', 'smart-address-book' ); ?>
			</a>
		</p>
	</form>

</div>

