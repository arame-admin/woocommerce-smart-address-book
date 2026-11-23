<?php
/**
 * Centralized Template Loader for Smart Address Book.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAB_Template_Loader {

	/**
	 * Locate a template file, prioritizing theme overrides.
	 *
	 * Theme paths checked:
	 * 1. yourtheme/woocommerce/smart-address-book/$template_name
	 * 2. yourtheme/smart-address-book/$template_name
	 * 3. yourtheme/woocommerce/$template_name
	 * 4. Plugin fallback directory ($default_dir / public/views / admin/views)
	 *
	 * @param string $template_name Template file name (e.g. 'address-book.php').
	 * @param string $default_dir   Optional plugin fallback directory path.
	 * @return string File path to template, or empty string if not found.
	 */
	public static function locate( $template_name, $default_dir = '' ) {
		$template_name = ltrim( $template_name, '/\\' );

		// 1. Check in child theme and parent theme if locate_template is available
		if ( function_exists( 'locate_template' ) ) {
			$theme_path = locate_template(
				array(
					'woocommerce/smart-address-book/' . $template_name,
					'smart-address-book/' . $template_name,
					'woocommerce/' . $template_name,
				)
			);

			if ( ! empty( $theme_path ) && file_exists( $theme_path ) ) {
				return apply_filters( 'sab_locate_template', $theme_path, $template_name, $default_dir );
			}
		}

		// 2. Check explicit default dir if provided
		if ( ! empty( $default_dir ) ) {
			$file = trailingslashit( $default_dir ) . $template_name;
			if ( file_exists( $file ) ) {
				return apply_filters( 'sab_locate_template', $file, $template_name, $default_dir );
			}
		}

		// 3. Fallback to plugin public/views/
		$plugin_public = trailingslashit( SAB_PLUGIN_DIR ) . 'public/views/' . $template_name;
		if ( file_exists( $plugin_public ) ) {
			return apply_filters( 'sab_locate_template', $plugin_public, $template_name, $default_dir );
		}

		// 4. Fallback to plugin admin/views/
		$plugin_admin = trailingslashit( SAB_PLUGIN_DIR ) . 'admin/views/' . $template_name;
		if ( file_exists( $plugin_admin ) ) {
			return apply_filters( 'sab_locate_template', $plugin_admin, $template_name, $default_dir );
		}

		return apply_filters( 'sab_locate_template', '', $template_name, $default_dir );
	}

	/**
	 * Load and include a template file with variable extraction.
	 * Validates file existence prior to including (Point 28).
	 *
	 * @param string $template_name Template file name.
	 * @param array  $args          Associative array of arguments to extract.
	 * @param string $default_dir   Optional default plugin directory.
	 * @return bool True if loaded successfully, false otherwise.
	 */
	public static function load( $template_name, $args = array(), $default_dir = '' ) {
		$template_path = self::locate( $template_name, $default_dir );

		if ( empty( $template_path ) || ! file_exists( $template_path ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf( '[Smart Address Book] Template file "%s" could not be located.', $template_name ) );
			}
			return false;
		}

		if ( ! empty( $args ) && is_array( $args ) ) {
			extract( $args ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		}

		do_action( 'sab_before_template_load', $template_name, $template_path, $args );

		include $template_path;

		do_action( 'sab_after_template_load', $template_name, $template_path, $args );

		return true;
	}

	/**
	 * Render a template file and return the output as a string.
	 *
	 * @param string $template_name Template file name.
	 * @param array  $args          Associative array of arguments to extract.
	 * @param string $default_dir   Optional default plugin directory.
	 * @return string Rendered template HTML.
	 */
	public static function render( $template_name, $args = array(), $default_dir = '' ) {
		ob_start();
		self::load( $template_name, $args, $default_dir );
		return ob_get_clean();
	}
}
