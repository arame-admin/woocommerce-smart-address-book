<?php
/**
 * Main plugin orchestrator for Smart Address Book.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAB_Plugin {

	/**
	 * Single instance of SAB_Plugin.
	 *
	 * @var SAB_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Public instance.
	 *
	 * @var SAB_Public|null
	 */
	public $public = null;

	/**
	 * Admin instance.
	 *
	 * @var SAB_Admin|null
	 */
	public $admin = null;

	/**
	 * AJAX instance.
	 *
	 * @var SAB_Ajax|null
	 */
	public $ajax = null;

	/**
	 * Privacy instance.
	 *
	 * @var SAB_Privacy|null
	 */
	public $privacy = null;

	/**
	 * Import/Export instance.
	 *
	 * @var SAB_Import_Export|null
	 */
	public $import_export = null;

	/**
	 * Get singleton instance.
	 *
	 * @return SAB_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_components();
		$this->register_hooks();
	}

	/**
	 * Initialize components.
	 */
	private function init_components() {
		$this->ajax          = new SAB_Ajax();
		$this->privacy       = new SAB_Privacy();
		$this->import_export = new SAB_Import_Export();
		$this->public        = new SAB_Public();

		if ( is_admin() ) {
			$this->admin = new SAB_Admin();
		}
	}

	/**
	 * Register general hooks and My Account endpoints.
	 */
	private function register_hooks() {
		// Register My Account endpoint
		add_action( 'init', array( $this, 'add_endpoints' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );

		// Insert Address Book into WooCommerce Account Menu
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_account_menu_item' ), 40 );

		// Load plugin textdomain
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Register WooCommerce Checkout Blocks integration
		add_action( 'woocommerce_blocks_checkout_block_registration', array( $this, 'register_blocks_integration' ) );
	}

	/**
	 * Register WooCommerce Checkout Blocks Integration.
	 *
	 * @param \Automattic\WooCommerce\Blocks\Integrations\IntegrationRegistry $integration_registry Integration registry.
	 */
	public function register_blocks_integration( $integration_registry ) {
		require_once SAB_PLUGIN_DIR . 'includes/class-sab-blocks-integration.php';
		if ( class_exists( 'SAB_Blocks_Integration' ) ) {
			$integration_registry->register( new SAB_Blocks_Integration() );
		}
	}

	/**
	 * Add rewrite endpoint for My Account.
	 */
	public function add_endpoints() {
		add_rewrite_endpoint( 'sab-address-book', EP_ROOT | EP_PAGES );
	}

	/**
	 * Add query variable.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'sab-address-book';
		return $vars;
	}

	/**
	 * Add 'Address Book' item right after standard 'edit-address'.
	 *
	 * @param array $items Existing menu items.
	 * @return array
	 */
	public function add_account_menu_item( $items ) {
		$new_items = array();

		foreach ( $items as $key => $title ) {
			$new_items[ $key ] = $title;
			if ( 'edit-address' === $key ) {
				$new_items['sab-address-book'] = __( 'Address Book', 'smart-address-book' );
			}
		}

		if ( ! isset( $new_items['sab-address-book'] ) ) {
			$new_items['sab-address-book'] = __( 'Address Book', 'smart-address-book' );
		}

		return apply_filters( 'sab_account_menu_items', $new_items );
	}

	/**
	 * Load plugin text domain for i18n.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'smart-address-book',
			false,
			dirname( SAB_PLUGIN_BASENAME ) . '/languages/'
		);
	}
}

