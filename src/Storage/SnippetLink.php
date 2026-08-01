<?php
/**
 * Reads and writes the _rawmark_linked flag on a Snippet.
 *
 * @package Rawmark
 */

namespace Rawmark\Storage;

use Rawmark\PostType\Snippet;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SnippetLink {

	public const META_KEY = '_rawmark_linked';

	public static function is_linked( int $snippet_id ): bool {
		if ( Snippet::SLUG !== get_post_type( $snippet_id ) ) {
			return false;
		}

		return '1' === (string) get_post_meta( $snippet_id, self::META_KEY, true );
	}

	public static function link( int $snippet_id ): void {
		update_post_meta( $snippet_id, self::META_KEY, '1' );
	}

	public static function unlink( int $snippet_id ): void {
		delete_post_meta( $snippet_id, self::META_KEY );
	}
}
