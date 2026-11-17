<?php
/**
 * Fired during plugin deactivation.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAB_Deactivator {

	/**
	 * Run deactivation logic.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}

