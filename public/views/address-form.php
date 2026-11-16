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

// Preserve submitted form values on validation error, falling back to existing address or default
$address_type = sanitize_text_field( $_POST['address_type'] ?? ( $is_edit ? $address->address_type : $default_type ) );
$nickname     = sanitize_text_field( $_POST['nickname'] ?? ( $is_edit ? $address->nickname : '' ) );
$first_name   = sanitize_text_field( $_POST['first_name'] ?? ( $is_edit ? $address->first_name : '' ) );
$last_name    = sanitize_text_field( $_POST['last_name'] ?? ( $is_edit ? $address->last_name : '' ) );
$company      = sanitize_text_field( $_POST['company'] ?? ( $is_edit ? $address->company : '' ) );
$country      = sanitize_text_field( $_POST['country'] ?? ( $is_edit ? $address->country : ( WC()->customer ? WC()->customer->get_shipping_country() : 'US' ) ) );
$address_1    = sanitize_text_field( $_POST['address_1'] ?? ( $is_edit ? $address->address_1 : '' ) );
$address_2    = sanitize_text_field( $_POST['address_2'] ?? ( $is_edit ? $address->address_2 : '' ) );
$city         = sanitize_text_field( $_POST['city'] ?? ( $is_edit ? $address->city : '' ) );
$state        = sanitize_text_field( $_POST['state'] ?? ( $is_edit ? $address->state : ( WC()->customer ? WC()->customer->get_shipping_state() : '' ) ) );
$postal_code  = sanitize_text_field( $_POST['postal_code'] ?? ( $is_edit ? $address->postal_code : '' ) );
$phone        = sanitize_text_field( $_POST['phone'] ?? ( $is_edit ? $address->phone : '' ) );
$email        = sanitize_email( $_POST['email'] ?? ( $is_edit ? $address->email : '' ) );
$is_default   = isset( $_POST['sab_save_address'] ) ? ( ! empty( $_POST['is_default'] ) ? 1 : 0 ) : ( $is_edit && (int) $address->is_default === 1 ? 1 : 0 );

$countries = ( 'shipping' === $address_type && function_exists( 'WC' ) && WC()->countries )
	? ( WC()->countries->get_shipping_countries() ?: WC()->countries->get_allowed_countries() )
	: ( ( function_exists( 'WC' ) && WC()->countries ) ? WC()->countries->get_allowed_countries() : array() );
$states    = WC()->countries->get_states( $country );

$validate_required = 'yes' === get_option( 'sab_validate_required_fields', 'yes' );
$req_mark          = $validate_required ? ' <abbr class="required" title="required">*</abbr>' : '';
$req_attr          = $validate_required ? ' required' : '';
$max_allowed       = function_exists( 'sab_get_address_limit' ) ? sab_get_address_limit( $address_type ) : 3;
$can_add_billing   = SAB_Database::can_add_address( $user_id, 'billing' );
$can_add_shipping  = SAB_Database::can_add_address( $user_id, 'shipping' );
$can_add_current   = ( 'billing' === $address_type ) ? $can_add_billing : $can_add_shipping;
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

	<?php if ( ! $is_edit && $max_allowed > 0 && ! $can_add_current ) : ?>
	<div class="sab-limit-notice woocommerce-info" style="margin-bottom: 20px;">
		<p style="margin: 0 0 10px 0;">
			<strong><?php esc_html_e( 'Address limit reached', 'smart-address-book' ); ?></strong><br>
			<?php echo esc_html( ( 'billing' === $address_type ) ? __( 'You have reached the maximum limit of 3 billing addresses. Upgrade to Pro to save unlimited billing addresses.', 'smart-address-book' ) : __( 'You have reached the maximum limit of 3 shipping addresses. Upgrade to Pro to save unlimited shipping addresses.', 'smart-address-book' ) ); ?>
		</p>
		<button type="button" class="button button-primary sab-pro-upgrade-btn">
			<?php esc_html_e( 'Pro Upgrade – Coming Soon', 'smart-address-book' ); ?>
		</button>
		<div class="sab-pro-coming-soon-notice" style="display:none; margin-top: 12px; padding: 10px 14px; background:#e8f4fc; border-left:4px solid #0073aa; border-radius:4px; font-size:13px; color:#1d2327;">
			<strong><?php esc_html_e( "Pro Upgrade is coming soon! You'll soon be able to upgrade your plan and enjoy unlimited billing and shipping addresses.", 'smart-address-book' ); ?></strong>
		</div>
	</div>
	<?php endif; ?>

	<form method="post" action="" class="woocommerce-address-fields sab-address-form" id="sab-address-form" novalidate="novalidate"
		data-is-edit="<?php echo $is_edit ? '1' : '0'; ?>"
		data-max-allowed="<?php echo esc_attr( $max_allowed ); ?>"
		data-can-add-billing="<?php echo $can_add_billing ? '1' : '0'; ?>"
		data-can-add-shipping="<?php echo $can_add_shipping ? '1' : '0'; ?>"
		data-limit-billing="<?php esc_attr_e( 'You have reached the maximum limit of 3 billing addresses. Upgrade to Pro to save unlimited billing addresses.', 'smart-address-book' ); ?>"
		data-limit-shipping="<?php esc_attr_e( 'You have reached the maximum limit of 3 shipping addresses. Upgrade to Pro to save unlimited shipping addresses.', 'smart-address-book' ); ?>">
		<?php wp_nonce_field( 'sab_address_form', 'sab_nonce' ); ?>
		<input type="hidden" name="address_id" value="<?php echo esc_attr( $address_id ); ?>" />

		<div class="woocommerce-address-fields__field-wrapper">

			<!-- Address Type -->
			<p class="form-row form-row-wide" id="sab_address_type_field">
				<label for="sab_address_type">
					<?php esc_html_e( 'Address Type', 'smart-address-book' ); ?> <abbr class="required" title="required">*</abbr>
				</label>
				<select name="address_type" id="sab_address_type" class="select">
					<option value="shipping" <?php selected( $address_type, 'shipping' ); ?>><?php esc_html_e( 'Shipping Address', 'smart-address-book' ); ?></option>
					<option value="billing" <?php selected( $address_type, 'billing' ); ?>><?php esc_html_e( 'Billing Address', 'smart-address-book' ); ?></option>
				</select>
			</p>

			<!-- Address Nickname -->
			<?php if ( 'yes' === get_option( 'sab_enable_nickname', 'yes' ) ) : ?>
			<p class="form-row form-row-wide" id="sab_nickname_field">
				<label for="sab_nickname">
					<?php esc_html_e( 'Address Nickname / Label', 'smart-address-book' ); ?> <span class="optional">(<?php esc_html_e( 'optional', 'smart-address-book' ); ?>)</span>
				</label>
				<input type="text" class="input-text" name="nickname" id="sab_nickname" placeholder="<?php esc_attr_e( 'e.g. Home, Office, Parents, Warehouse', 'smart-address-book' ); ?>" value="<?php echo esc_attr( $nickname ); ?>" />
			</p>
			<?php endif; ?>

			<!-- First & Last Name -->
			<p class="form-row form-row-first <?php echo $validate_required ? 'validate-required' : ''; ?>" id="sab_first_name_field">
				<label for="sab_first_name">
					<?php esc_html_e( 'First Name', 'smart-address-book' ); ?><?php echo $req_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</label>
				<input type="text" class="input-text" name="first_name" id="sab_first_name"<?php echo $req_attr; ?> value="<?php echo esc_attr( $first_name ); ?>" />
			</p>

			<p class="form-row form-row-last <?php echo $validate_required ? 'validate-required' : ''; ?>" id="sab_last_name_field">
				<label for="sab_last_name">
					<?php esc_html_e( 'Last Name', 'smart-address-book' ); ?><?php echo $req_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</label>
				<input type="text" class="input-text" name="last_name" id="sab_last_name"<?php echo $req_attr; ?> value="<?php echo esc_attr( $last_name ); ?>" />
			</p>

			<!-- Company -->
			<p class="form-row form-row-wide" id="sab_company_field">
				<label for="sab_company">
					<?php esc_html_e( 'Company Name', 'smart-address-book' ); ?> <span class="optional">(<?php esc_html_e( 'optional', 'smart-address-book' ); ?>)</span>
				</label>
				<input type="text" class="input-text" name="company" id="sab_company" value="<?php echo esc_attr( $company ); ?>" />
			</p>

			<!-- Country -->
			<p class="form-row form-row-wide <?php echo $validate_required ? 'validate-required' : ''; ?>" id="sab_country_field">
				<label for="sab_country">
					<?php esc_html_e( 'Country / Region', 'smart-address-book' ); ?><?php echo $req_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</label>
				<select name="country" id="sab_country" class="country_to_state country_select"<?php echo $req_attr; ?>>
					<option value=""><?php esc_html_e( 'Select a country / region&hellip;', 'smart-address-book' ); ?></option>
					<?php foreach ( $countries as $country_code => $country_name ) : ?>
						<option value="<?php echo esc_attr( $country_code ); ?>" <?php selected( $country, $country_code ); ?>><?php echo esc_html( $country_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>

			<!-- Street Address -->
			<p class="form-row form-row-wide <?php echo $validate_required ? 'validate-required' : ''; ?>" id="sab_address_1_field">
				<label for="sab_address_1">
					<?php esc_html_e( 'Street Address', 'smart-address-book' ); ?><?php echo $req_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</label>
				<input type="text" class="input-text" name="address_1" id="sab_address_1" placeholder="<?php esc_attr_e( 'House number and street name', 'smart-address-book' ); ?>"<?php echo $req_attr; ?> value="<?php echo esc_attr( $address_1 ); ?>" />
			</p>

			<p class="form-row form-row-wide" id="sab_address_2_field">
				<label for="sab_address_2" class="screen-reader-text">
					<?php esc_html_e( 'Apartment, suite, unit, etc.', 'smart-address-book' ); ?>
				</label>
				<input type="text" class="input-text" name="address_2" id="sab_address_2" placeholder="<?php esc_attr_e( 'Apartment, suite, unit, etc. (optional)', 'smart-address-book' ); ?>" value="<?php echo esc_attr( $address_2 ); ?>" />
			</p>

			<!-- Town / City -->
			<p class="form-row form-row-wide <?php echo $validate_required ? 'validate-required' : ''; ?>" id="sab_city_field">
				<label for="sab_city">
					<?php esc_html_e( 'Town / City', 'smart-address-book' ); ?><?php echo $req_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</label>
				<input type="text" class="input-text" name="city" id="sab_city"<?php echo $req_attr; ?> value="<?php echo esc_attr( $city ); ?>" />
			</p>

			<!-- State / County -->
			<p class="form-row form-row-first" id="sab_state_field">
				<label for="sab_state">
					<?php esc_html_e( 'State / County', 'smart-address-book' ); ?>
				</label>
				<?php if ( ! empty( $states ) && is_array( $states ) ) : ?>
					<select name="state" id="sab_state" class="state_select">
						<option value=""><?php esc_html_e( 'Select an option&hellip;', 'smart-address-book' ); ?></option>
						<?php foreach ( $states as $state_code => $state_name ) : ?>
							<option value="<?php echo esc_attr( $state_code ); ?>" <?php selected( $state, $state_code ); ?>><?php echo esc_html( $state_name ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<input type="text" class="input-text" name="state" id="sab_state" value="<?php echo esc_attr( $state ); ?>" />
				<?php endif; ?>
			</p>

			<!-- Postcode / ZIP -->
			<p class="form-row form-row-last <?php echo $validate_required ? 'validate-required' : ''; ?>" id="sab_postcode_field">
				<label for="sab_postal_code">
					<?php esc_html_e( 'Postcode / ZIP', 'smart-address-book' ); ?><?php echo $req_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</label>
				<input type="text" class="input-text" name="postal_code" id="sab_postal_code"<?php echo $req_attr; ?> value="<?php echo esc_attr( $postal_code ); ?>" />
			</p>

			<!-- Phone -->
			<p class="form-row form-row-first" id="sab_phone_field">
				<label for="sab_phone">
					<?php esc_html_e( 'Phone Number', 'smart-address-book' ); ?> <span class="optional">(<?php esc_html_e( 'optional', 'smart-address-book' ); ?>)</span>
				</label>
				<input type="tel" class="input-text" name="phone" id="sab_phone" value="<?php echo esc_attr( $phone ); ?>" />
			</p>

			<!-- Email -->
			<p class="form-row form-row-last <?php echo $validate_required ? 'validate-required' : ''; ?> validate-email" id="sab_email_field">
				<label for="sab_email">
					<?php esc_html_e( 'Email Address', 'smart-address-book' ); ?><?php echo $req_mark; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</label>
				<input type="email" class="input-text" name="email" id="sab_email"<?php echo $req_attr; ?> value="<?php echo esc_attr( $email ); ?>" />
			</p>

			<!-- Default Checkbox -->
			<p class="form-row form-row-wide" id="sab_is_default_field">
				<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
					<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="is_default" id="sab_is_default" value="1" <?php checked( (int) $is_default === 1 ); ?> />
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
