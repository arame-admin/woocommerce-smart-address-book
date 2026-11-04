<?php
/**
 * Admin Order Edit - Saved Address Picker Dropdown.
 *
 * @var array  $addresses Customer addresses.
 * @var string $type      'billing' or 'shipping'.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="sab-order-picker-wrap" style="margin-top: 10px; padding: 8px 10px; background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 4px;">
	<?php
	$linked_id = (int) $order->get_meta( '_sab_' . $type . '_address_id' );
	if ( $linked_id ) {
		$linked_addr = SAB_Database::get_address( $linked_id );
		$badge_text  = $linked_addr
			? sprintf( __( 'Order Address Linked To: %s (#%d)', 'smart-address-book' ), $linked_addr->nickname ?: $linked_addr->first_name, $linked_id )
			: sprintf( __( 'Order Address Linked To: Address #%d', 'smart-address-book' ), $linked_id );
		?>
		<div style="margin-bottom: 6px; padding: 3px 6px; background: #e7f3f5; border-left: 3px solid #007185; font-size: 11px; color: #005a6a;">
			<strong><?php echo esc_html( $badge_text ); ?></strong>
		</div>
		<?php
	}
	?>
	<label for="sab_order_<?php echo esc_attr( $type ); ?>_select" style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 12px;">
		<span class="dashicons dashicons-book" style="font-size: 16px; width: 16px; height: 16px; vertical-align: text-top;"></span>
		<?php echo ( 'billing' === $type ) ? esc_html__( 'Load from Customer Billing Addresses:', 'smart-address-book' ) : esc_html__( 'Load from Customer Shipping Addresses:', 'smart-address-book' ); ?>
	</label>
	<select id="sab_order_<?php echo esc_attr( $type ); ?>_select" class="sab-order-address-select widefat" data-type="<?php echo esc_attr( $type ); ?>">
		<option value=""><?php esc_html_e( '— Select saved address —', 'smart-address-book' ); ?></option>
		<?php foreach ( $addresses as $addr ) : ?>
			<?php
			$label = ! empty( $addr->nickname ) ? '[' . $addr->nickname . '] ' : '';
			$label .= $addr->first_name . ' ' . $addr->last_name . ', ' . $addr->address_1 . ', ' . $addr->city;
			if ( (int) $addr->is_default === 1 ) {
				$label .= ' (' . __( 'Default', 'smart-address-book' ) . ')';
			}
			?>
			<option value="<?php echo esc_attr( $addr->id ); ?>" data-address='<?php echo esc_attr( wp_json_encode( $addr ) ); ?>'>
				<?php echo esc_html( $label ); ?>
			</option>
		<?php endforeach; ?>
	</select>
</div>

