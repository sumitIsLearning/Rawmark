<?php
/**
 * Writes - and defends - the sanitized post_content fallback copy.
 *
 * @package Rawmark
 */

namespace Rawmark\Storage;

use Rawmark\Support\Hookable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ContentMirror implements Hookable {

	/**
	 * The post ID this class is currently writing, if any. Only a write
	 * originating from write() below is allowed past the guard.
	 */
	private static ?int $writing_post_id = null;

	public function register(): void {
		add_filter( 'wp_insert_post_data', array( $this, 'protect_mirror' ), 10, 2 );
	}

	/**
	 * The mirror's only writer is write(); everything else that saves a
	 * flagged page gets the stored content handed back to it unchanged.
	 *
	 * Removing `editor` support was supposed to make this structurally
	 * impossible, but it does not: wp-admin/post.php enqueues the autosave
	 * script before edit_form_after_title fires, so the support removal
	 * happens too late to stop it. Autosave then submits
	 * `content: $('#content').val() || ''` - with no #content field in the
	 * DOM that is an empty string, not an omitted key - and
	 * _wp_translate_postdata() treats isset('') as present, so wp_autosave()
	 * calls edit_post() with post_content set to ''. On a draft that blanks
	 * the mirror roughly fifteen seconds after the author touches the title.
	 *
	 * Guarding at the write boundary instead of the render boundary closes
	 * that, the core REST /wp/v2/pages/{id} PUT path, and any third-party
	 * writer, in one place. _rawmark_source is never involved - it is
	 * separate meta - so this protects the fallback copy only.
	 *
	 * @param array<string, mixed> $data    Sanitized, slashed post data headed for the database.
	 * @param array<string, mixed> $postarr Sanitized, slashed post data as assembled by wp_insert_post().
	 * @return array<string, mixed>
	 */
	public function protect_mirror( $data, $postarr ) {
		$post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;

		if ( ! $post_id || $post_id === self::$writing_post_id ) {
			return $data;
		}

		if ( ! PageFlag::is_enabled( $post_id ) ) {
			return $data;
		}

		$stored = get_post( $post_id );

		if ( ! $stored ) {
			return $data;
		}

		// $data is slashed at this point ("Expected_slashed (everything!)"
		// in wp_insert_post()); core unslashes it immediately after this
		// filter. WP_Post properties are raw, so re-slash before handing it
		// back or every backslash in the mirror is eaten on each save.
		$data['post_content'] = wp_slash( $stored->post_content );

		return $data;
	}

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

		// The deliberate bypass for protect_mirror(). Scoped to this one
		// post ID, and cleared in a finally so a failure part-way through
		// cannot leave the guard disabled for the rest of the request.
		self::$writing_post_id = $post_id;

		try {
			wp_update_post(
				wp_slash(
					array(
						'ID'           => $post_id,
						'post_content' => $mirror,
					)
				)
			);
		} finally {
			self::$writing_post_id = null;
		}
	}
}
