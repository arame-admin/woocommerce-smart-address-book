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
?>

<div class="sab-checkout-selector-container sab-checkout-<?php echo esc_attr( $type ); ?>-selector" data-type="<?php echo esc_attr( $type ); ?>">
	<p class="form-row form-row-wide">
		<label for="<?php echo esc_attr( $selector_id ); ?>">
			<strong><?php echo esc_html( $selector_label ); ?></strong>
		</label>
		<select id="<?php echo esc_attr( $selector_id ); ?>" class="sab-checkout-address-dropdown select" data-type="<?php echo esc_attr( $type ); ?>">
			<option value="new"><?php esc_html_e( '+ Enter a new address', 'smart-address-book' ); ?></option>
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
				}
				?>
				<option value="<?php echo esc_attr( $addr->id ); ?>" <?php selected( $is_selected ); ?> data-address='<?php echo esc_attr( wp_json_encode( $addr ) ); ?>'>
					<?php echo esc_html( $display_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
</div>

