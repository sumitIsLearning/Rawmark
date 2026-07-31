<?php
/**
 * Enables and disables Rawmark mode on a Page.
 *
 * @package Rawmark
 */

namespace Rawmark\Admin;

use Rawmark\Security\Capabilities;
use Rawmark\Storage\PageFlag;
use Rawmark\Support\Hookable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PageModeToggle implements Hookable {

	public const ACTION_ENABLE  = 'rawmark_enable';
	public const ACTION_DISABLE = 'rawmark_disable';

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION_ENABLE, array( $this, 'handle_enable' ) );
		add_action( 'admin_post_' . self::ACTION_DISABLE, array( $this, 'handle_disable' ) );
	}

	/**
	 * Both capabilities are required: the Rawmark gate because this mode
	 * stores unsanitized JavaScript, and edit_post because it is still an
	 * edit of that specific page.
	 */
	public static function user_may_toggle( int $post_id ): bool {
		return current_user_can( Capabilities::CAP ) && current_user_can( 'edit_post', $post_id );
	}

	public static function enable_url( int $post_id ): string {
		return self::build_url( self::ACTION_ENABLE, $post_id );
	}

	public static function disable_url( int $post_id ): string {
		return self::build_url( self::ACTION_DISABLE, $post_id );
	}

	private static function build_url( string $action, int $post_id ): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . $action . '&post=' . $post_id ),
			$action . '_' . $post_id
		);
	}

	public function handle_enable(): void {
		$post_id = $this->authorize( self::ACTION_ENABLE );

		PageFlag::enable( $post_id );

		wp_safe_redirect(
			admin_url( 'admin.php?page=' . EditorScreen::PAGE_SLUG . '&post=' . $post_id )
		);
		exit;
	}

	public function handle_disable(): void {
		$post_id = $this->authorize( self::ACTION_DISABLE );

		// The source meta is deliberately left in place so re-enabling
		// recovers the developer's code.
		PageFlag::disable( $post_id );

		wp_safe_redirect( get_edit_post_link( $post_id, 'redirect' ) );
		exit;
	}

	/**
	 * Verifies the nonce and both capabilities, or dies. Returns the post id.
	 */
	private function authorize( string $action ): int {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

		check_admin_referer( $action . '_' . $post_id );

		if ( ! $post_id || 'page' !== get_post_type( $post_id ) ) {
			wp_die( esc_html__( 'Page not found.', 'rawmark' ), '', array( 'response' => 404 ) );
		}

		if ( ! self::user_may_toggle( $post_id ) ) {
			wp_die(
				esc_html__( 'You do not have permission to do that.', 'rawmark' ),
				'',
				array( 'response' => 403 )
			);
		}

		return $post_id;
	}
}
