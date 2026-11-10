<?php
/**
 * Admin Order Edit - Saved Address Picker Dropdown.
 *
 * @var array  $addresses    Customer addresses.
 * @var string $address_type 'billing' or 'shipping'.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$address_type = ! empty( $address_type ) ? $address_type : ( $type ?? 'billing' );
?>

<div class="sab-order-picker-wrap" style="margin-top: 10px; padding: 8px 10px; background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 4px;">
	<?php
	$linked_id = (int) $order->get_meta( '_sab_' . $address_type . '_address_id' );
	if ( $linked_id ) {
		$linked_address = SAB_Database::get_address( $linked_id );
		/* translators: 1: address nickname or first name, 2: address ID */
		$badge_text     = $linked_address
			? sprintf( __( 'Order Address Linked To: %1$s (#%2$d)', 'smart-address-book' ), $linked_address->nickname ?: $linked_address->first_name, $linked_id )
			/* translators: %d: address ID */
			: sprintf( __( 'Order Address Linked To: Address #%d', 'smart-address-book' ), $linked_id );
		?>
		<div style="margin-bottom: 6px; padding: 3px 6px; background: #e7f3f5; border-left: 3px solid #007185; font-size: 11px; color: #005a6a;">
			<strong><?php echo esc_html( $badge_text ); ?></strong>
		</div>
		<?php
	}
	?>
	<label for="sab_order_<?php echo esc_attr( $address_type ); ?>_select" style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 12px;">
		<span class="dashicons dashicons-book" style="font-size: 16px; width: 16px; height: 16px; vertical-align: text-top;"></span>
		<?php echo ( 'billing' === $address_type ) ? esc_html__( 'Load from Customer Billing Addresses:', 'smart-address-book' ) : esc_html__( 'Load from Customer Shipping Addresses:', 'smart-address-book' ); ?>
	</label>
	<select id="sab_order_<?php echo esc_attr( $address_type ); ?>_select" class="sab-order-address-select widefat" data-type="<?php echo esc_attr( $address_type ); ?>">
		<option value=""><?php esc_html_e( '— Select saved address —', 'smart-address-book' ); ?></option>
		<?php foreach ( $addresses as $address ) : ?>
			<?php
			$label = ! empty( $address->nickname ) ? '[' . $address->nickname . '] ' : '';
			$label .= $address->first_name . ' ' . $address->last_name . ', ' . $address->address_1 . ', ' . $address->city;
			if ( (int) $address->is_default === 1 ) {
				$label .= ' (' . __( 'Default', 'smart-address-book' ) . ')';
			}
			?>
			<option value="<?php echo esc_attr( $address->id ); ?>" data-address='<?php echo esc_attr( wp_json_encode( $address ) ); ?>'>
				<?php echo esc_html( $label ); ?>
			</option>
		<?php endforeach; ?>
	</select>
</div>

