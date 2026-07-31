<?php
/**
 * Writes the sanitized post_content fallback copy.
 *
 * @package Rawmark
 */

namespace Rawmark\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ContentMirror {

	/**
	 * The renderer never reads this. It exists so that deactivating Rawmark,
	 * or switching a page back to the block editor, leaves readable content
	 * instead of a blank page - and so native search and export see the HTML.
	 *
	 * Pre-sanitizing with wp_kses_post() avoids colliding with WordPress's own
	 * wp_filter_post_kses behaviour for users without unfiltered_html.
	 */
	public static function write( int $post_id, string $html ): void {
		$mirror = wp_kses_post( $html );

		wp_update_post(
			wp_slash(
				array(
					'ID'           => $post_id,
					'post_content' => $mirror,
				)
			)
		);
	}
}
