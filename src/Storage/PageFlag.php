<?php
/**
 * Reads and writes the _rawmark_enabled page flag.
 *
 * @package Rawmark
 */

namespace Rawmark\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PageFlag {

	public const META_KEY = '_rawmark_enabled';

	/**
	 * Both eligible for the same raw-render treatment: flag, editor, router.
	 * A Page and a Post store their content identically once flagged - the
	 * type only ever matters for this list.
	 */
	public const ELIGIBLE_TYPES = array( 'page', 'post' );

	/**
	 * The single answer to "is this a Rawmark page?". The post type is part
	 * of the test on purpose: a meta row alone must never be enough to make
	 * the renderer take over an arbitrary post.
	 */
	public static function is_enabled( int $post_id ): bool {
		if ( ! in_array( get_post_type( $post_id ), self::ELIGIBLE_TYPES, true ) ) {
			return false;
		}

		return '1' === (string) get_post_meta( $post_id, self::META_KEY, true );
	}

	public static function enable( int $post_id ): void {
		update_post_meta( $post_id, self::META_KEY, '1' );
	}

	public static function disable( int $post_id ): void {
		delete_post_meta( $post_id, self::META_KEY );
	}
}
