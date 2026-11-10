<?php
/**
 * Admin User Profile - Customer Addresses Table and Modal.
 *
 * @var array   $addresses List of customer addresses.
 * @var WP_User $user      Customer WP_User object.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$countries = WC()->countries ? WC()->countries->get_allowed_countries() : array();
?>

<div class="sab-admin-profile-wrapper">
	<h2><?php esc_html_e( 'Customer Address Book', 'smart-address-book' ); ?></h2>
	<p class="description"><?php esc_html_e( 'View, add, edit, or manage multiple saved addresses for this customer.', 'smart-address-book' ); ?></p>

	<table class="wp-list-table widefat fixed striped sab-admin-address-table">
		<thead>
			<tr>
				<th style="width: 15%;"><?php esc_html_e( 'Nickname', 'smart-address-book' ); ?></th>
				<th style="width: 12%;"><?php esc_html_e( 'Type', 'smart-address-book' ); ?></th>
				<th style="width: 38%;"><?php esc_html_e( 'Address Details', 'smart-address-book' ); ?></th>
				<th style="width: 15%;"><?php esc_html_e( 'Default Status', 'smart-address-book' ); ?></th>
				<th style="width: 20%;"><?php esc_html_e( 'Actions', 'smart-address-book' ); ?></th>
			</tr>
		</thead>
		<tbody id="sab-admin-address-tbody">
			<?php if ( empty( $addresses ) ) : ?>
				<tr class="sab-no-addresses">
					<td colspan="5"><?php esc_html_e( 'No addresses saved for this customer yet.', 'smart-address-book' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $addresses as $address ) : ?>
					<?php
					$is_default = ( 1 === (int) $address->is_default );
					$nickname   = ! empty( $address->nickname ) ? $address->nickname : '—';
					?>
					<tr id="sab-admin-row-<?php echo esc_attr( $address->id ); ?>" data-id="<?php echo esc_attr( $address->id ); ?>">
						<td><strong><?php echo esc_html( $nickname ); ?></strong></td>
						<td><span class="sab-type-tag sab-type-<?php echo esc_attr( $address->address_type ); ?>"><?php echo esc_html( ucfirst( $address->address_type ) ); ?></span></td>
						<td><?php echo wp_kses_post( SAB_Address::format_address( $address ) ); ?></td>
						<td>
							<?php if ( $is_default ) : ?>
								<span class="dashicons dashicons-star-filled sab-star-icon"></span> <strong><?php esc_html_e( 'Default', 'smart-address-book' ); ?></strong>
							<?php else : ?>
								<button type="button" class="button button-small sab-admin-set-default" data-id="<?php echo esc_attr( $address->id ); ?>" data-type="<?php echo esc_attr( $address->address_type ); ?>">
									<?php esc_html_e( 'Set Default', 'smart-address-book' ); ?>
								</button>
							<?php endif; ?>
						</td>
						<td>
							<button type="button" class="button button-small sab-admin-edit-btn" data-id="<?php echo esc_attr( $address->id ); ?>">
								<?php esc_html_e( 'Edit', 'smart-address-book' ); ?>
							</button>
							<button type="button" class="button button-small sab-admin-del-btn" data-id="<?php echo esc_attr( $address->id ); ?>">
								<?php esc_html_e( 'Delete', 'smart-address-book' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<p style="margin-top: 15px;">
		<button type="button" class="button button-primary" id="sab-admin-add-address-btn" data-user-id="<?php echo esc_attr( $user->ID ); ?>">
			<span class="dashicons dashicons-plus-alt2" style="vertical-align: middle;"></span> <?php esc_html_e( 'Add Customer Address', 'smart-address-book' ); ?>
		</button>
	</p>

	<!-- Add / Edit Modal -->
	<div id="sab-admin-modal" class="sab-modal" style="display:none;">
		<div class="sab-modal-backdrop"></div>
		<div class="sab-modal-window">
			<div class="sab-modal-header">
				<h3 id="sab-modal-title"><?php esc_html_e( 'Add New Address', 'smart-address-book' ); ?></h3>
				<button type="button" class="sab-modal-close-btn">&times;</button>
			</div>

			<form id="sab-admin-modal-form">
				<input type="hidden" name="address_id" id="sab_modal_address_id" value="" />
				<input type="hidden" name="user_id" id="sab_modal_user_id" value="<?php echo esc_attr( $user->ID ); ?>" />

				<div class="sab-modal-body">
					<div class="sab-form-grid">
						<div class="sab-form-col">
							<label for="sab_modal_type"><strong><?php esc_html_e( 'Address Type', 'smart-address-book' ); ?> *</strong></label>
							<select name="address_type" id="sab_modal_type" class="widefat" required>
								<option value="shipping"><?php esc_html_e( 'Shipping Address', 'smart-address-book' ); ?></option>
								<option value="billing"><?php esc_html_e( 'Billing Address', 'smart-address-book' ); ?></option>
							</select>
						</div>

						<div class="sab-form-col">
							<label for="sab_modal_nickname"><strong><?php esc_html_e( 'Nickname / Label', 'smart-address-book' ); ?></strong></label>
							<input type="text" name="nickname" id="sab_modal_nickname" class="widefat" placeholder="e.g. Headquarters, Warehouse 2" />
						</div>

						<div class="sab-form-col">
							<label for="sab_modal_first_name"><strong><?php esc_html_e( 'First Name', 'smart-address-book' ); ?> *</strong></label>
							<input type="text" name="first_name" id="sab_modal_first_name" class="widefat" required />
						</div>

						<div class="sab-form-col">
							<label for="sab_modal_last_name"><strong><?php esc_html_e( 'Last Name', 'smart-address-book' ); ?> *</strong></label>
							<input type="text" name="last_name" id="sab_modal_last_name" class="widefat" required />
						</div>

						<div class="sab-form-col">
							<label for="sab_modal_company"><strong><?php esc_html_e( 'Company', 'smart-address-book' ); ?></strong></label>
							<input type="text" name="company" id="sab_modal_company" class="widefat" />
						</div>

						<div class="sab-form-col">
							<label for="sab_modal_phone"><strong><?php esc_html_e( 'Phone', 'smart-address-book' ); ?></strong></label>
							<input type="text" name="phone" id="sab_modal_phone" class="widefat" />
						</div>

						<div class="sab-form-col">
							<label for="sab_modal_email"><strong><?php esc_html_e( 'Email', 'smart-address-book' ); ?> *</strong></label>
							<input type="email" name="email" id="sab_modal_email" class="widefat" required />
						</div>

						<div class="sab-form-col">
							<label for="sab_modal_country"><strong><?php esc_html_e( 'Country', 'smart-address-book' ); ?> *</strong></label>
							<select name="country" id="sab_modal_country" class="widefat" required>
								<option value=""><?php esc_html_e( 'Select country...', 'smart-address-book' ); ?></option>
								<?php foreach ( $countries as $country_code => $country_name ) : ?>
									<option value="<?php echo esc_attr( $country_code ); ?>"><?php echo esc_html( $country_name ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="sab-form-col sab-form-full">
							<label for="sab_modal_address_1"><strong><?php esc_html_e( 'Street Address', 'smart-address-book' ); ?> *</strong></label>
							<input type="text" name="address_1" id="sab_modal_address_1" class="widefat" required />
						</div>

						<div class="sab-form-col sab-form-full">
							<label for="sab_modal_address_2"><strong><?php esc_html_e( 'Address Line 2', 'smart-address-book' ); ?></strong></label>
							<input type="text" name="address_2" id="sab_modal_address_2" class="widefat" />
						</div>

						<div class="sab-form-col">
							<label for="sab_modal_city"><strong><?php esc_html_e( 'City', 'smart-address-book' ); ?> *</strong></label>
							<input type="text" name="city" id="sab_modal_city" class="widefat" required />
						</div>

						<div class="sab-form-col">
							<label for="sab_modal_state"><strong><?php esc_html_e( 'State / Province', 'smart-address-book' ); ?></strong></label>
							<input type="text" name="state" id="sab_modal_state" class="widefat" />
						</div>

						<div class="sab-form-col">
							<label for="sab_modal_postal_code"><strong><?php esc_html_e( 'Postal Code', 'smart-address-book' ); ?> *</strong></label>
							<input type="text" name="postal_code" id="sab_modal_postal_code" class="widefat" required />
						</div>

						<div class="sab-form-col">
							<label style="margin-top: 25px; display: block;">
								<input type="checkbox" name="is_default" id="sab_modal_is_default" value="1" />
								<strong><?php esc_html_e( 'Set as default address', 'smart-address-book' ); ?></strong>
							</label>
						</div>
					</div>
				</div>

				<div class="sab-modal-footer">
					<button type="submit" class="button button-primary" id="sab-modal-save-btn">
						<?php esc_html_e( 'Save Address', 'smart-address-book' ); ?>
					</button>
					<button type="button" class="button sab-modal-close-btn">
						<?php esc_html_e( 'Cancel', 'smart-address-book' ); ?>
					</button>
				</div>
			</form>
		</div>
	</div>

</div>

