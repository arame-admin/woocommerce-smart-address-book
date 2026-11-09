<?php
/**
 * Checkout Address Selector Dropdown Template.
 *
 * @var array  $addresses  Array of address objects for current user.
 * @var string $type       'billing' or 'shipping'.
 * @var int    $default_id ID of default address.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$address_type = ! empty( $address_type ) ? $address_type : ( $type ?? 'billing' );
$type         = $address_type;

$selector_id    = 'sab_' . $address_type . '_address_select';
$selector_label = ( 'billing' === $address_type )
	? __( 'Select a saved billing address:', 'smart-address-book' )
	: __( 'Select a saved shipping address:', 'smart-address-book' );
$btn_label      = ( 'billing' === $address_type )
	? __( '+ Add New Billing Address', 'smart-address-book' )
	: __( '+ Add New Shipping Address', 'smart-address-book' );

$enable_checkout_save = 'yes' === get_option( 'sab_enable_checkout_save', 'yes' );
$can_add_address      = SAB_Database::can_add_address( get_current_user_id(), $address_type );
?>

<div class="sab-checkout-selector-container sab-checkout-<?php echo esc_attr( $type ); ?>-selector" data-type="<?php echo esc_attr( $type ); ?>">
	<div class="sab-selector-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
		<label for="<?php echo esc_attr( $selector_id ); ?>" class="sab-selector-label" style="font-weight: 600; margin-bottom: 0;">
			<?php echo esc_html( $selector_label ); ?>
		</label>
		<?php if ( $enable_checkout_save && $can_add_address ) : ?>
		<button type="button" class="button sab-add-new-address-btn" data-type="<?php echo esc_attr( $type ); ?>" style="font-size: 13px; padding: 4px 10px; cursor: pointer;">
			<?php echo esc_html( $btn_label ); ?>
		</button>
		<?php endif; ?>
	</div>
	<p class="form-row form-row-wide">
		<select id="<?php echo esc_attr( $selector_id ); ?>" class="sab-checkout-address-dropdown select" data-type="<?php echo esc_attr( $type ); ?>">
			<?php foreach ( $addresses as $address ) : ?>
				<?php
				$is_selected = ( (int) $address->id === $default_id );
				$label_parts = array();

				if ( ! empty( $address->nickname ) ) {
					$label_parts[] = '[' . $address->nickname . ']';
				}

				$label_parts[] = trim( $address->first_name . ' ' . $address->last_name );
				$label_parts[] = $address->address_1;
				$label_parts[] = $address->city . ( $address->postal_code ? ' ' . $address->postal_code : '' );

				$display_label = implode( ' — ', array_filter( $label_parts ) );
				if ( (int) $address->is_default === 1 ) {
					$display_label .= ' (' . __( 'Default', 'smart-address-book' ) . ')';
				} elseif ( ! empty( $address->is_recently_used ) ) {
					$display_label .= ' (' . __( 'Recently Used', 'smart-address-book' ) . ')';
				}
				?>
				<option value="<?php echo esc_attr( $address->id ); ?>" <?php selected( $is_selected ); ?> data-address='<?php echo esc_attr( wp_json_encode( $address ) ); ?>'>
					<?php echo esc_html( $display_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<input type="hidden" name="sab_<?php echo esc_attr( $type ); ?>_address_id" id="sab_<?php echo esc_attr( $type ); ?>_address_id" value="<?php echo esc_attr( $default_id ); ?>" />

	<!-- Address Summary Box (Displays selected address with exactly ONE Edit button beside it) -->
	<?php
	$selected_address = null;
	foreach ( $addresses as $address ) {
		if ( (int) $address->id === (int) $default_id ) {
			$selected_address = $address;
			break;
		}
	}
	if ( ! $selected_address && ! empty( $addresses ) ) {
		$selected_address = $addresses[0];
	}
	?>

	<?php if ( 'billing' === $type && ! empty( $selected_address ) ) : ?>
		<style id="sab-billing-form-default-hide">
			.woocommerce-billing-fields__field-wrapper:not(.sab-form-expanded),
			.sab-checkout-save-billing:not(.sab-form-expanded),
			#sab_billing_inline_actions:not(.sab-form-expanded) {
				display: none !important;
			}
		</style>
	<?php elseif ( 'shipping' === $type && ! empty( $selected_address ) ) : ?>
		<style id="sab-shipping-form-default-hide">
			.woocommerce-shipping-fields__field-wrapper:not(.sab-form-expanded),
			.sab-checkout-save-shipping:not(.sab-form-expanded),
			#sab_shipping_inline_actions:not(.sab-form-expanded) {
				display: none !important;
			}
		</style>
	<?php endif; ?>
	<div class="sab-address-summary-box" id="sab_<?php echo esc_attr( $type ); ?>_summary_box" style="<?php echo empty( $selected_address ) ? 'display:none;' : ''; ?>">
		<div class="sab-summary-card">
			<div class="sab-summary-content">
				<div class="sab-summary-header-row">
					<span class="sab-summary-title">
						<strong><?php echo esc_html( ( 'billing' === $type ) ? __( 'Billing Address', 'smart-address-book' ) : __( 'Shipping Address', 'smart-address-book' ) ); ?></strong>
					</span>
					<?php if ( $selected_address && (int) $selected_address->is_default === 1 ) : ?>
						<span class="sab-badge sab-default-badge"><?php esc_html_e( 'Default', 'smart-address-book' ); ?></span>
					<?php endif; ?>
				</div>
				<div class="sab-summary-text" id="sab_<?php echo esc_attr( $type ); ?>_summary_text">
					<?php
					if ( $selected_address ) {
						$lines = array();
						$name  = trim( $selected_address->first_name . ' ' . $selected_address->last_name );
						if ( ! empty( $selected_address->nickname ) ) {
							$name .= ' (' . $selected_address->nickname . ')';
						}
						if ( $name ) {
							$lines[] = '<strong>' . esc_html( $name ) . '</strong>';
						}
						if ( ! empty( $selected_address->company ) ) {
							$lines[] = esc_html( $selected_address->company );
						}
						$addr_line = $selected_address->address_1;
						if ( ! empty( $selected_address->address_2 ) ) {
							$addr_line .= ', ' . $selected_address->address_2;
						}
						if ( $addr_line ) {
							$lines[] = esc_html( $addr_line );
						}
						$city_line = $selected_address->city;
						if ( ! empty( $selected_address->state ) ) {
							$city_line .= ', ' . $selected_address->state;
						}
						if ( ! empty( $selected_address->postal_code ) ) {
							$city_line .= ' ' . $selected_address->postal_code;
						}
						if ( $city_line ) {
							$lines[] = esc_html( $city_line );
						}
						if ( ! empty( $selected_address->country ) ) {
							$lines[] = esc_html( $selected_address->country );
						}
						$contact = array();
						if ( ! empty( $selected_address->phone ) ) {
							$contact[] = __( 'Phone:', 'smart-address-book' ) . ' ' . esc_html( $selected_address->phone );
						}
						if ( ! empty( $selected_address->email ) ) {
							$contact[] = __( 'Email:', 'smart-address-book' ) . ' ' . esc_html( $selected_address->email );
						}
						if ( ! empty( $contact ) ) {
							$lines[] = implode( ' | ', $contact );
						}
						echo implode( '<br>', $lines );
					}
					?>
				</div>
			</div>
			<div class="sab-summary-action">
				<button type="button" class="button sab-summary-edit-btn" data-type="<?php echo esc_attr( $type ); ?>">
					<?php esc_html_e( 'Edit', 'smart-address-book' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>
