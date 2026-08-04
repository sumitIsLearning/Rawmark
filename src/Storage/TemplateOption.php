<?php
/**
 * Generic get/set/clear/is_set logic for an option that holds a Snippet post
 * ID — the shared shape behind PostTemplate, HeaderTemplate, and
 * FooterTemplate. Each caller supplies its own option-name string.
 *
 * @package Rawmark
 */

namespace Rawmark\Storage;

use Rawmark\PostType\Snippet;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TemplateOption {

	public static function get_id( string $option_key ): int {
		return (int) get_option( $option_key, 0 );
	}

	public static function set( string $option_key, int $snippet_id ): void {
		update_option( $option_key, $snippet_id );
	}

	public static function clear( string $option_key ): void {
		delete_option( $option_key );
	}

	/**
	 * A deleted or wrong-type post at the stored ID counts as unset - lets
	 * every caller fail safe with no guard duplicated at the call site.
	 */
	public static function is_set( string $option_key ): bool {
		$id = self::get_id( $option_key );

		return $id > 0 && Snippet::SLUG === get_post_type( $id );
	}
}
