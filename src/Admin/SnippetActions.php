<?php
/**
 * Link, unlink, and delete actions for a Snippet, reachable from the
 * Snippets list screen.
 *
 * @package Rawmark
 */

namespace Rawmark\Admin;

use Rawmark\PostType\Snippet;
use Rawmark\Security\Capabilities;
use Rawmark\Storage\SnippetLink;
use Rawmark\Storage\SnippetUsage;
use Rawmark\Storage\Source;
use Rawmark\Support\Hookable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SnippetActions implements Hookable {

	public const ACTION_LINK   = 'rawmark_snippet_link';
	public const ACTION_UNLINK = 'rawmark_snippet_unlink';
	public const ACTION_DELETE = 'rawmark_snippet_delete';

	private const MARKER_TEMPLATE = "/<!--\\s*rawmark:snippet\\s+id='%d'\\s*-->/";

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION_LINK, array( $this, 'handle_link' ) );
		add_action( 'admin_post_' . self::ACTION_UNLINK, array( $this, 'handle_unlink' ) );
		add_action( 'admin_post_' . self::ACTION_DELETE, array( $this, 'handle_delete' ) );
	}

	public static function link_url( int $snippet_id ): string {
		return self::build_url( self::ACTION_LINK, $snippet_id );
	}

	public static function unlink_url( int $snippet_id ): string {
		return self::build_url( self::ACTION_UNLINK, $snippet_id );
	}

	public static function delete_url( int $snippet_id ): string {
		return self::build_url( self::ACTION_DELETE, $snippet_id );
	}

	private static function build_url( string $action, int $snippet_id ): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . $action . '&snippet=' . $snippet_id ),
			$action . '_' . $snippet_id
		);
	}

	public function handle_link(): void {
		$id = $this->authorize( self::ACTION_LINK );

		SnippetLink::link( $id );

		wp_safe_redirect( SnippetsScreen::url() );
		exit;
	}

	public function handle_unlink(): void {
		$id = $this->authorize( self::ACTION_UNLINK );

		self::unlink_and_bake( $id );

		wp_safe_redirect( SnippetsScreen::url() );
		exit;
	}

	public function handle_delete(): void {
		$id = $this->authorize( self::ACTION_DELETE );

		wp_delete_post( $id, true );

		wp_safe_redirect( SnippetsScreen::url() );
		exit;
	}

	/**
	 * The unlink transition's actual logic, deliberately kept in its own
	 * exit-free method rather than inlined into handle_unlink(). That
	 * separation is what makes it directly unit-testable: handle_unlink()
	 * ends in wp_safe_redirect() + a bare `exit;`, which nothing in a test
	 * process can safely survive, so tests call this method directly and
	 * never invoke handle_unlink() itself.
	 *
	 * Every current placement's live-resolved content, as of this moment,
	 * gets locked in. In-body markers are replaced with the resolved HTML,
	 * merged into the page's own panes as real static content. Header/footer
	 * placements simply clear - see the design doc section 6 for why those
	 * two cases are handled differently on purpose.
	 */
	public static function unlink_and_bake( int $snippet_id ): void {
		$snippet_source = Source::get( $snippet_id );
		$pattern        = sprintf( self::MARKER_TEMPLATE, $snippet_id );

		foreach ( SnippetUsage::find_placements( $snippet_id ) as $page_id ) {
			if ( $snippet_id === (int) get_post_meta( $page_id, '_rawmark_header_snippet', true ) ) {
				delete_post_meta( $page_id, '_rawmark_header_snippet' );
			}

			if ( $snippet_id === (int) get_post_meta( $page_id, '_rawmark_footer_snippet', true ) ) {
				delete_post_meta( $page_id, '_rawmark_footer_snippet' );
			}

			$page_source = Source::get( $page_id );

			if ( 1 !== preg_match( $pattern, $page_source['html'] ) ) {
				continue;
			}

			$html = preg_replace( $pattern, $snippet_source['html'], $page_source['html'] );
			$css  = $page_source['css'] . ( '' !== $snippet_source['css'] ? "\n" . $snippet_source['css'] : '' );
			$js   = $page_source['js'] . ( '' !== $snippet_source['js'] ? "\n" . $snippet_source['js'] : '' );

			Source::save( $page_id, $html, $css, $js, $page_source['settings'] );
		}

		SnippetLink::unlink( $snippet_id );
	}

	private function authorize( string $action ): int {
		$id = isset( $_GET['snippet'] ) ? absint( $_GET['snippet'] ) : 0;

		check_admin_referer( $action . '_' . $id );

		if ( ! $id || Snippet::SLUG !== get_post_type( $id ) ) {
			wp_die( esc_html__( 'Snippet not found.', 'rawmark' ), '', array( 'response' => 404 ) );
		}

		if ( ! current_user_can( Capabilities::CAP ) ) {
			wp_die(
				esc_html__( 'You do not have permission to do that.', 'rawmark' ),
				'',
				array( 'response' => 403 )
			);
		}

		return $id;
	}
}
