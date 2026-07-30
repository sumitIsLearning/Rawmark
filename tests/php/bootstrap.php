<?php
/**
 * PHPUnit bootstrap for Rawmark.
 *
 * Requires wp-phpunit/wp-phpunit (installed via `composer install`) and a
 * reachable test database (WP_TESTS_DB_NAME etc. in a wp-tests-config.php,
 * or the WP_TESTS_DIR / WP_PHPUNIT__DIR env vars pointing at one).
 *
 * @package Rawmark
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = dirname( __DIR__, 2 ) . '/vendor/wp-phpunit/wp-phpunit';
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually loads the plugin and runs its activation routine - the
 * register_activation_hook callback never fires just from requiring the
 * main file, since that's a WP-admin-only convention.
 */
function _rawmark_manually_load_plugin() {
	require dirname( __DIR__, 2 ) . '/rawmark.php';
	Rawmark\Security\Capabilities::activate();
}
tests_add_filter( 'muplugins_loaded', '_rawmark_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
