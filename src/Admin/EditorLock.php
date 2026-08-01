<?php
/**
 * Disables the block editor for Rawmark pages and shows a lock panel.
 *
 * @package Rawmark
 */

namespace Rawmark\Admin;

use Rawmark\Security\Capabilities;
use Rawmark\Storage\PageFlag;
use Rawmark\Support\Hookable;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EditorLock implements Hookable {

	public function register(): void {
		add_filter( 'use_block_editor_for_post', array( $this, 'disable_block_editor' ), 10, 2 );
		add_action( 'edit_form_after_title', array( $this, 'render_panel' ) );
	}

	/**
	 * Forces the classic screen for Rawmark pages. Combined with removing
	 * `editor` support below, the page's content field is never rendered and
	 * therefore never submitted - so saving from wp-admin cannot overwrite
	 * the mirror behind _rawmark_source's back.
	 *
	 * @param bool     $use_block_editor Whether to use the block editor.
	 * @param \WP_Post $post             Post being edited.
	 */
	public function disable_block_editor( $use_block_editor, $post ) {
		if ( $post instanceof WP_Post && PageFlag::is_enabled( $post->ID ) ) {
			return false;
		}

		return $use_block_editor;
	}

	public function render_panel( WP_Post $post ): void {
		if ( ! PageFlag::is_enabled( $post->ID ) ) {
			return;
		}

		// Removing editor support is what actually stops post_content being
		// submitted. Doing it here, on the screen that is about to render,
		// keeps the change scoped to this request. The post's own type, not
		// a hard-coded 'page' - this now also runs for a flagged Post, and
		// hard-coding 'page' here would silently leave a Post's content
		// field open to being overwritten by the classic editor.
		remove_post_type_support( $post->post_type, 'editor' );

		$may_edit = current_user_can( Capabilities::CAP );
		?>
		<div class="notice notice-info inline" style="padding:16px;margin:16px 0;">
			<h2 style="margin-top:0;"><?php esc_html_e( 'This page is built with Rawmark', 'rawmark' ); ?></h2>
			<p>
				<?php esc_html_e( 'Its HTML, CSS, and JavaScript are edited in the Rawmark editor. The block editor is turned off for this page so it cannot overwrite that code.', 'rawmark' ); ?>
			</p>
			<p>
				<?php if ( $may_edit ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . EditorScreen::PAGE_SLUG . '&post=' . $post->ID ) ); ?>">
						<?php esc_html_e( 'Edit with Rawmark', 'rawmark' ); ?>
					</a>
					<a class="button" href="<?php echo esc_url( PageModeToggle::disable_url( $post->ID ) ); ?>">
						<?php esc_html_e( 'Stop using Rawmark', 'rawmark' ); ?>
					</a>
				<?php else : ?>
					<em><?php esc_html_e( 'You do not have permission to edit this page\'s code.', 'rawmark' ); ?></em>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}
}
