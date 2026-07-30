<?php
/**
 * Interface for classes that register WordPress hooks.
 *
 * @package Rawmark
 */

namespace Rawmark\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Hookable {

	/**
	 * Register this service's WordPress hooks.
	 */
	public function register(): void;
}
