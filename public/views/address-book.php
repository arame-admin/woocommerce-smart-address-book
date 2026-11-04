<?php
/**
 * Customer My Account - Address Book List View Template.
 *
 * @var array $billing_addresses  List of billing address objects.
 * @var array $shipping_addresses List of shipping address objects.
 * @var int   $user_id            Current user ID.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$allow_nickname = 'yes' === get_option( 'sab_enable_nickname', 'yes' );
$allow_search   = 'yes' === get_option( 'sab_enable_address_search', 'yes' );
$add_url        = add_query_arg( 'action', 'add', wc_get_account_endpoint_url( 'sab-address-book' ) );
?>

<div class="sab-address-book-wrapper">

	<div class="sab-header-bar">
		<div class="sab-header-title">
			<h3><?php esc_html_e( 'My Address Book', 'smart-address-book' ); ?></h3>
			<p class="sab-subtitle"><?php esc_html_e( 'Manage your saved delivery and billing addresses for fast checkout.', 'smart-address-book' ); ?></p>
		</div>

		<div class="sab-header-actions">
			<a href="<?php echo esc_url( $add_url ); ?>" class="button button-primary sab-add-btn">
				<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add New Address', 'smart-address-book' ); ?>
			</a>

			<div class="sab-csv-actions">
				<button type="button" class="button sab-export-btn" title="<?php esc_attr_e( 'Export all addresses to CSV', 'smart-address-book' ); ?>">
					<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Export CSV', 'smart-address-book' ); ?>
				</button>
				<button type="button" class="button sab-import-btn" title="<?php esc_attr_e( 'Import addresses from CSV', 'smart-address-book' ); ?>">
					<span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Import CSV', 'smart-address-book' ); ?>
				</button>
				<input type="file" id="sab-csv-file-input" accept=".csv" style="display:none;" />
			</div>
		</div>
	</div>

	<?php if ( $allow_search ) : ?>
	<div class="sab-search-wrapper">
		<input type="text" id="sab-address-search-input" class="sab-search-input" placeholder="<?php esc_attr_e( 'Search by nickname, name, city, postcode, or address...', 'smart-address-book' ); ?>" />
		<span class="dashicons dashicons-search sab-search-icon"></span>
	</div>
	<?php endif; ?>

	<div id="sab-notices"></div>

	<!-- Billing Addresses Section -->
	<?php if ( 'yes' === get_option( 'sab_enable_billing', 'yes' ) ) : ?>
	<div class="sab-section sab-billing-section">
		<div class="sab-section-header">
			<h4><?php esc_html_e( 'Billing Addresses', 'smart-address-book' ); ?></h4>
			<span class="sab-count-badge"><?php echo esc_html( count( $billing_addresses ) ); ?></span>
		</div>

		<div class="sab-address-grid sab-billing-grid">
			<?php if ( empty( $billing_addresses ) ) : ?>
				<div class="sab-empty-state">
					<p><?php esc_html_e( 'No saved billing addresses yet.', 'smart-address-book' ); ?></p>
					<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'add', 'type' => 'billing' ), wc_get_account_endpoint_url( 'sab-address-book' ) ) ); ?>" class="button">
						<?php esc_html_e( 'Add Billing Address', 'smart-address-book' ); ?>
					</a>
				</div>
			<?php else : ?>
				<?php foreach ( $billing_addresses as $address ) : ?>
					<?php
					$is_def    = (int) $address->is_default === 1;
					$edit_url  = add_query_arg( array( 'action' => 'edit', 'address_id' => $address->id ), wc_get_account_endpoint_url( 'sab-address-book' ) );
					$nickname  = ! empty( $address->nickname ) ? $address->nickname : __( 'Billing Address', 'smart-address-book' );
					?>
					<div class="sab-card <?php echo $is_def ? 'sab-is-default' : ''; ?>" data-id="<?php echo esc_attr( $address->id ); ?>" data-type="billing">
						<div class="sab-card-top">
							<span class="sab-card-nickname">
								<span class="dashicons dashicons-location"></span>
								<strong><?php echo esc_html( $nickname ); ?></strong>
							</span>
							<?php if ( $is_def ) : ?>
								<span class="sab-badge sab-default-badge"><?php esc_html_e( 'Default Billing', 'smart-address-book' ); ?></span>
							<?php endif; ?>
						</div>

						<div class="sab-card-body">
							<div class="sab-formatted-address">
								<?php echo wp_kses_post( SAB_Address::format_address( $address ) ); ?>
							</div>
							<?php if ( ! empty( $address->phone ) ) : ?>
								<p class="sab-card-meta"><span class="dashicons dashicons-phone"></span> <?php echo esc_html( $address->phone ); ?></p>
							<?php endif; ?>
							<?php if ( ! empty( $address->email ) ) : ?>
								<p class="sab-card-meta"><span class="dashicons dashicons-email-alt"></span> <?php echo esc_html( $address->email ); ?></p>
							<?php endif; ?>
						</div>

						<div class="sab-card-actions">
							<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small sab-action-edit">
								<?php esc_html_e( 'Edit', 'smart-address-book' ); ?>
							</a>
							<button type="button" class="button button-small sab-action-delete" data-id="<?php echo esc_attr( $address->id ); ?>">
								<?php esc_html_e( 'Delete', 'smart-address-book' ); ?>
							</button>
							<?php if ( ! $is_def ) : ?>
								<button type="button" class="button button-small sab-action-set-default" data-id="<?php echo esc_attr( $address->id ); ?>" data-type="billing">
									<?php esc_html_e( 'Set as Default', 'smart-address-book' ); ?>
								</button>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>
	<?php endif; ?>

	<!-- Shipping Addresses Section -->
	<?php if ( 'yes' === get_option( 'sab_enable_shipping', 'yes' ) ) : ?>
	<div class="sab-section sab-shipping-section">
		<div class="sab-section-header">
			<h4><?php esc_html_e( 'Shipping Addresses', 'smart-address-book' ); ?></h4>
			<span class="sab-count-badge"><?php echo esc_html( count( $shipping_addresses ) ); ?></span>
		</div>

		<div class="sab-address-grid sab-shipping-grid">
			<?php if ( empty( $shipping_addresses ) ) : ?>
				<div class="sab-empty-state">
					<p><?php esc_html_e( 'No saved shipping addresses yet.', 'smart-address-book' ); ?></p>
					<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'add', 'type' => 'shipping' ), wc_get_account_endpoint_url( 'sab-address-book' ) ) ); ?>" class="button">
						<?php esc_html_e( 'Add Shipping Address', 'smart-address-book' ); ?>
					</a>
				</div>
			<?php else : ?>
				<?php foreach ( $shipping_addresses as $address ) : ?>
					<?php
					$is_def    = (int) $address->is_default === 1;
					$edit_url  = add_query_arg( array( 'action' => 'edit', 'address_id' => $address->id ), wc_get_account_endpoint_url( 'sab-address-book' ) );
					$nickname  = ! empty( $address->nickname ) ? $address->nickname : __( 'Shipping Address', 'smart-address-book' );
					?>
					<div class="sab-card <?php echo $is_def ? 'sab-is-default' : ''; ?>" data-id="<?php echo esc_attr( $address->id ); ?>" data-type="shipping">
						<div class="sab-card-top">
							<span class="sab-card-nickname">
								<span class="dashicons dashicons-cart"></span>
								<strong><?php echo esc_html( $nickname ); ?></strong>
							</span>
							<?php if ( $is_def ) : ?>
								<span class="sab-badge sab-default-badge"><?php esc_html_e( 'Default Shipping', 'smart-address-book' ); ?></span>
							<?php endif; ?>
						</div>

						<div class="sab-card-body">
							<div class="sab-formatted-address">
								<?php echo wp_kses_post( SAB_Address::format_address( $address ) ); ?>
							</div>
							<?php if ( ! empty( $address->phone ) ) : ?>
								<p class="sab-card-meta"><span class="dashicons dashicons-phone"></span> <?php echo esc_html( $address->phone ); ?></p>
							<?php endif; ?>
							<?php if ( ! empty( $address->email ) ) : ?>
								<p class="sab-card-meta"><span class="dashicons dashicons-email-alt"></span> <?php echo esc_html( $address->email ); ?></p>
							<?php endif; ?>
						</div>

						<div class="sab-card-actions">
							<a href="<?php echo esc_url( $edit_url ); ?>" class="button button-small sab-action-edit">
								<?php esc_html_e( 'Edit', 'smart-address-book' ); ?>
							</a>
							<button type="button" class="button button-small sab-action-delete" data-id="<?php echo esc_attr( $address->id ); ?>">
								<?php esc_html_e( 'Delete', 'smart-address-book' ); ?>
							</button>
							<?php if ( ! $is_def ) : ?>
								<button type="button" class="button button-small sab-action-set-default" data-id="<?php echo esc_attr( $address->id ); ?>" data-type="shipping">
									<?php esc_html_e( 'Set as Default', 'smart-address-book' ); ?>
								</button>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>
	<?php endif; ?>

</div>

