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

$selector_id    = 'sab_' . $type . '_address_select';
$selector_label = ( 'billing' === $type )
	? __( 'Select a saved billing address:', 'smart-address-book' )
	: __( 'Select a saved shipping address:', 'smart-address-book' );
$btn_label      = ( 'billing' === $type )
	? __( '+ Add New Billing Address', 'smart-address-book' )
	: __( '+ Add New Shipping Address', 'smart-address-book' );

$enable_checkout_save = 'yes' === get_option( 'sab_enable_checkout_save', 'yes' );
$can_add_address      = SAB_Database::can_add_address( get_current_user_id(), $type );
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
			<?php foreach ( $addresses as $addr ) : ?>
				<?php
				$is_selected = ( (int) $addr->id === $default_id );
				$label_parts = array();

				if ( ! empty( $addr->nickname ) ) {
					$label_parts[] = '[' . $addr->nickname . ']';
				}

				$label_parts[] = trim( $addr->first_name . ' ' . $addr->last_name );
				$label_parts[] = $addr->address_1;
				$label_parts[] = $addr->city . ( $addr->postal_code ? ' ' . $addr->postal_code : '' );

				$display_label = implode( ' — ', array_filter( $label_parts ) );
				if ( (int) $addr->is_default === 1 ) {
					$display_label .= ' (' . __( 'Default', 'smart-address-book' ) . ')';
				} elseif ( ! empty( $addr->is_recently_used ) ) {
					$display_label .= ' (' . __( 'Recently Used', 'smart-address-book' ) . ')';
				}
				?>
				<option value="<?php echo esc_attr( $addr->id ); ?>" <?php selected( $is_selected ); ?> data-address='<?php echo esc_attr( wp_json_encode( $addr ) ); ?>'>
					<?php echo esc_html( $display_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<input type="hidden" name="sab_<?php echo esc_attr( $type ); ?>_address_id" id="sab_<?php echo esc_attr( $type ); ?>_address_id" value="<?php echo esc_attr( $default_id ); ?>" />

	<!-- Address Summary Box (Displays selected address with exactly ONE Edit button beside it) -->
	<?php
	$selected_addr = null;
	foreach ( $addresses as $a ) {
		if ( (int) $a->id === (int) $default_id ) {
			$selected_addr = $a;
			break;
		}
	}
	if ( ! $selected_addr && ! empty( $addresses ) ) {
		$selected_addr = $addresses[0];
	}
	?>

	<?php if ( 'billing' === $type && ! empty( $selected_addr ) ) : ?>
		<style id="sab-billing-form-default-hide">
			.woocommerce-billing-fields__field-wrapper:not(.sab-form-expanded),
			.sab-checkout-save-billing:not(.sab-form-expanded),
			#sab_billing_inline_actions:not(.sab-form-expanded) {
				display: none !important;
			}
		</style>
	<?php elseif ( 'shipping' === $type && ! empty( $selected_addr ) ) : ?>
		<style id="sab-shipping-form-default-hide">
			.woocommerce-shipping-fields__field-wrapper:not(.sab-form-expanded),
			.sab-checkout-save-shipping:not(.sab-form-expanded),
			#sab_shipping_inline_actions:not(.sab-form-expanded) {
				display: none !important;
			}
		</style>
	<?php endif; ?>
	<div class="sab-address-summary-box" id="sab_<?php echo esc_attr( $type ); ?>_summary_box" style="<?php echo empty( $selected_addr ) ? 'display:none;' : ''; ?>">
		<div class="sab-summary-card">
			<div class="sab-summary-content">
				<div class="sab-summary-header-row">
					<span class="sab-summary-title">
						<strong><?php echo esc_html( ( 'billing' === $type ) ? __( 'Billing Address', 'smart-address-book' ) : __( 'Shipping Address', 'smart-address-book' ) ); ?></strong>
					</span>
					<?php if ( $selected_addr && (int) $selected_addr->is_default === 1 ) : ?>
						<span class="sab-badge sab-default-badge"><?php esc_html_e( 'Default', 'smart-address-book' ); ?></span>
					<?php endif; ?>
				</div>
				<div class="sab-summary-text" id="sab_<?php echo esc_attr( $type ); ?>_summary_text">
					<?php
					if ( $selected_addr ) {
						$lines = array();
						$name  = trim( $selected_addr->first_name . ' ' . $selected_addr->last_name );
						if ( ! empty( $selected_addr->nickname ) ) {
							$name .= ' (' . $selected_addr->nickname . ')';
						}
						if ( $name ) {
							$lines[] = '<strong>' . esc_html( $name ) . '</strong>';
						}
						if ( ! empty( $selected_addr->company ) ) {
							$lines[] = esc_html( $selected_addr->company );
						}
						$addr_line = $selected_addr->address_1;
						if ( ! empty( $selected_addr->address_2 ) ) {
							$addr_line .= ', ' . $selected_addr->address_2;
						}
						if ( $addr_line ) {
							$lines[] = esc_html( $addr_line );
						}
						$city_line = $selected_addr->city;
						if ( ! empty( $selected_addr->state ) ) {
							$city_line .= ', ' . $selected_addr->state;
						}
						if ( ! empty( $selected_addr->postal_code ) ) {
							$city_line .= ' ' . $selected_addr->postal_code;
						}
						if ( $city_line ) {
							$lines[] = esc_html( $city_line );
						}
						if ( ! empty( $selected_addr->country ) ) {
							$lines[] = esc_html( $selected_addr->country );
						}
						$contact = array();
						if ( ! empty( $selected_addr->phone ) ) {
							$contact[] = __( 'Phone:', 'smart-address-book' ) . ' ' . esc_html( $selected_addr->phone );
						}
						if ( ! empty( $selected_addr->email ) ) {
							$contact[] = __( 'Email:', 'smart-address-book' ) . ' ' . esc_html( $selected_addr->email );
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
