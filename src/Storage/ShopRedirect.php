<?php
/**
 * Reads and writes which Page, if any, stands in for the default
 * WooCommerce Shop archive.
 *
 * @package Rawmark
 */

namespace Rawmark\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ShopRedirect {

	public const OPTION_KEY = 'rawmark_shop_redirect_page_id';

	public static function get_page_id(): int {
		return (int) get_option( self::OPTION_KEY, 0 );
	}

	public static function set( int $page_id ): void {
		update_option( self::OPTION_KEY, $page_id );
	}

	public static function clear(): void {
		delete_option( self::OPTION_KEY );
	}

	/**
	 * A deleted or wrong-type post at the stored ID counts as unconfigured -
	 * lets the redirect fail safe with no guard duplicated at the call site.
	 */
	public static function is_configured(): bool {
		$id = self::get_page_id();

		return $id > 0 && 'page' === get_post_type( $id );
	}
}
