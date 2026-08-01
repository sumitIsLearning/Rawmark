<?php
/**
 * Reads and writes which Snippet, if any, is the site's Post Template.
 *
 * @package Rawmark
 */

namespace Rawmark\Storage;

use Rawmark\PostType\Snippet;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PostTemplate {

	public const OPTION_KEY = 'rawmark_post_template_id';

	public static function get_id(): int {
		return (int) get_option( self::OPTION_KEY, 0 );
	}

	public static function set( int $snippet_id ): void {
		update_option( self::OPTION_KEY, $snippet_id );
	}

	public static function clear(): void {
		delete_option( self::OPTION_KEY );
	}

	/**
	 * The single answer to "does a valid template exist right now?". A
	 * deleted or wrong-type post at the stored ID counts as unset, the same
	 * way SnippetComposer::resolve() treats a stale reference as absent -
	 * this is what lets Router fail safe to the theme with no extra
	 * guarding anywhere else.
	 */
	public static function is_set(): bool {
		$id = self::get_id();

		return $id > 0 && Snippet::SLUG === get_post_type( $id );
	}
}
