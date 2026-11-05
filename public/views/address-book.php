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

$allow_nickname   = 'yes' === get_option( 'sab_enable_nickname', 'yes' );
$allow_search     = 'yes' === get_option( 'sab_enable_address_search', 'yes' );
$max_allowed      = (int) get_option( 'sab_max_addresses', 0 );
$can_add_billing  = SAB_Database::can_add_address( $user_id, 'billing' );
$can_add_shipping = SAB_Database::can_add_address( $user_id, 'shipping' );
$can_add          = $can_add_billing || $can_add_shipping;
$add_url          = add_query_arg( 'action', 'add', wc_get_account_endpoint_url( 'sab-address-book' ) );
?>

<div class="sab-address-book-wrapper">

	<div class="sab-header-bar">
		<div class="sab-header-title">
			<h3><?php esc_html_e( 'My Address Book', 'smart-address-book' ); ?></h3>
			<p class="sab-subtitle"><?php esc_html_e( 'Manage your saved delivery and billing addresses for fast checkout.', 'smart-address-book' ); ?></p>
		</div>

		<div class="sab-header-actions">
			<?php if ( $can_add ) : ?>
			<a href="<?php echo esc_url( $add_url ); ?>" class="button button-primary sab-add-btn" data-can-add="1">
				<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add New Address', 'smart-address-book' ); ?>
			</a>
			<?php else : ?>
			<button type="button" class="button sab-add-btn sab-disabled" data-can-add="0" title="<?php echo esc_attr( sprintf( __( 'You have reached the maximum limit of %d saved addresses.', 'smart-address-book' ), $max_allowed ) ); ?>">
				<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add New Address', 'smart-address-book' ); ?>
			</button>
			<?php endif; ?>

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

		<?php if ( $max_allowed > 0 && ! $can_add_billing ) : ?>
		<div class="sab-limit-notice sab-billing-limit-notice woocommerce-info" style="margin-bottom: 20px;">
			<p style="margin: 0;">
				<strong><?php esc_html_e( 'Maximum Billing Limit Reached:', 'smart-address-book' ); ?></strong>
				<?php echo esc_html( sprintf( __( 'You have reached the maximum limit of %d saved billing addresses. To add a new billing address, please delete an existing one.', 'smart-address-book' ), $max_allowed ) ); ?>
			</p>
		</div>
		<?php endif; ?>

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

		<?php if ( $max_allowed > 0 && ! $can_add_shipping ) : ?>
		<div class="sab-limit-notice sab-shipping-limit-notice woocommerce-info" style="margin-bottom: 20px;">
			<p style="margin: 0;">
				<strong><?php esc_html_e( 'Maximum Shipping Limit Reached:', 'smart-address-book' ); ?></strong>
				<?php echo esc_html( sprintf( __( 'You have reached the maximum limit of %d saved shipping addresses. To add a new shipping address, please delete an existing one.', 'smart-address-book' ), $max_allowed ) ); ?>
			</p>
		</div>
		<?php endif; ?>

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

	<!-- CSV Import Preview & Confirmation Modal -->
	<div id="sab-csv-import-modal" class="sab-modal-backdrop" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="sab-csv-modal-title">
		<div class="sab-modal-dialog sab-import-modal-dialog">
			<div class="sab-modal-header">
				<h3 id="sab-csv-modal-title"><?php esc_html_e( 'Import Addresses from CSV', 'smart-address-book' ); ?></h3>
				<button type="button" class="sab-modal-close sab-import-modal-close" aria-label="<?php esc_attr_e( 'Close', 'smart-address-book' ); ?>">&times;</button>
			</div>
			<div class="sab-modal-body">
				<div class="sab-import-intro">
					<p><?php esc_html_e( 'Review the addresses detected in your CSV file. Select the addresses you want to import into your address book:', 'smart-address-book' ); ?></p>
				</div>

				<div class="sab-import-toolbar">
					<div class="sab-import-select-controls">
						<button type="button" class="button button-small sab-import-select-all"><?php esc_html_e( 'Select All', 'smart-address-book' ); ?></button>
						<button type="button" class="button button-small sab-import-deselect-all"><?php esc_html_e( 'Deselect All', 'smart-address-book' ); ?></button>
					</div>
					<div class="sab-import-counts">
						<span class="sab-selected-counter">
							<?php esc_html_e( 'Selected:', 'smart-address-book' ); ?> <strong id="sab-import-selected-count">0</strong> / <span id="sab-import-total-count">0</span>
						</span>
					</div>
				</div>

				<div class="sab-import-table-container">
					<table class="sab-import-table widefat striped">
						<thead>
							<tr>
								<th class="sab-th-cb" style="width: 38px; text-align: center;"><input type="checkbox" id="sab-import-master-cb" checked title="<?php esc_attr_e( 'Toggle all', 'smart-address-book' ); ?>" /></th>
								<th class="sab-th-type" style="width: 100px;"><?php esc_html_e( 'Type', 'smart-address-book' ); ?></th>
								<th class="sab-th-nickname" style="width: 130px;"><?php esc_html_e( 'Nickname', 'smart-address-book' ); ?></th>
								<th class="sab-th-name"><?php esc_html_e( 'Recipient', 'smart-address-book' ); ?></th>
								<th class="sab-th-address"><?php esc_html_e( 'Address Details', 'smart-address-book' ); ?></th>
								<th class="sab-th-contact"><?php esc_html_e( 'Contact', 'smart-address-book' ); ?></th>
							</tr>
						</thead>
						<tbody id="sab-import-table-body">
							<!-- Dynamically populated from CSV preview -->
						</tbody>
					</table>
				</div>

				<div id="sab-import-skipped-notice" class="sab-modal-notice sab-notice-warning" style="display:none; margin-top: 15px;"></div>

				<!-- Inline Confirmation Box -->
				<div id="sab-import-confirm-box" class="sab-import-confirm-box" style="display:none;">
					<div class="sab-confirm-inner">
						<span class="dashicons dashicons-warning sab-confirm-icon"></span>
						<div class="sab-confirm-text">
							<p><strong><?php esc_html_e( 'Confirm Import', 'smart-address-book' ); ?>:</strong> <span id="sab-confirm-question-text"><?php esc_html_e( 'Are you sure you want to import the selected address(es)?', 'smart-address-book' ); ?></span></p>
							<p class="sab-confirm-subtext"><?php esc_html_e( 'The selected addresses will be saved into their respective Billing and Shipping sections.', 'smart-address-book' ); ?></p>
						</div>
					</div>
					<div class="sab-confirm-buttons">
						<button type="button" class="button button-primary sab-confirm-proceed-btn"><?php esc_html_e( 'Yes, Confirm & Import', 'smart-address-book' ); ?></button>
						<button type="button" class="button sab-confirm-back-btn"><?php esc_html_e( 'Back to Selection', 'smart-address-book' ); ?></button>
					</div>
				</div>
			</div>
			<div class="sab-modal-footer">
				<button type="button" class="button sab-import-cancel-btn"><?php esc_html_e( 'Cancel', 'smart-address-book' ); ?></button>
				<button type="button" class="button button-primary sab-import-action-btn" id="sab-import-action-btn">
					<?php esc_html_e( 'Import Selected (0)', 'smart-address-book' ); ?>
				</button>
			</div>
		</div>
	</div>

</div>

