<?php
/**
 * Plugin Name: Rawmark
 * Description: Build a page by writing raw HTML, CSS, and JS in a split-pane editor with a live preview. Publishes a clean standalone document with no theme wrapper.
 * Version: 0.2.0
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Author: Rawmark
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: rawmark
 *
 * @package Rawmark
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RAWMARK_VERSION', '0.2.0' );
define( 'RAWMARK_FILE', __FILE__ );
define( 'RAWMARK_DIR', __DIR__ );
define( 'RAWMARK_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	function ( $class ) {
		$prefix = 'Rawmark\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$path = RAWMARK_DIR . '/src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';

		if ( is_readable( $path ) ) {
			require $path;
		}
	}
);

register_activation_hook( __FILE__, array( 'Rawmark\\Plugin', 'activate' ) );

add_action( 'plugins_loaded', array( 'Rawmark\\Plugin', 'boot' ) );
