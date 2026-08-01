<?php
/**
 * A Document sidebar panel in the block editor offering "Edit with Rawmark"
 * on an eligible Page or Post that isn't flagged yet.
 *
 * @package Rawmark
 */

namespace Rawmark\Admin;

use Rawmark\Storage\PageFlag;
use Rawmark\Support\Hookable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GutenbergPanel implements Hookable {

	public function register(): void {
		add_action( 'enqueue_block_editor_assets', array( $this, 'maybe_enqueue' ) );
	}

	/**
	 * Once a Page or Post is actually flagged, use_block_editor_for_post
	 * (see EditorLock) returns false and the block editor - this hook
	 * included - never loads for it at all. So this only ever needs to
	 * handle the "not yet enabled" case; there is no enabled branch to guard
	 * against here.
	 */
	public function maybe_enqueue(): void {
		$screen = get_current_screen();

		if ( ! $screen || ! in_array( $screen->post_type, PageFlag::ELIGIBLE_TYPES, true ) ) {
			return;
		}

		$post_id = get_the_ID();

		if ( ! $post_id || ! PageModeToggle::user_may_toggle( $post_id ) ) {
			return;
		}

		wp_enqueue_script(
			'rawmark-gutenberg-panel',
			RAWMARK_URL . 'assets/dist/gutenberg-panel.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-element' ),
			RAWMARK_VERSION,
			true
		);

		wp_localize_script(
			'rawmark-gutenberg-panel',
			'rawmarkGutenberg',
			array(
				'enableUrl' => PageModeToggle::enable_url( $post_id ),
			)
		);
	}
}
