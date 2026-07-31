<?php
/**
 * Renders the split-pane editor screen and replaces the default post
 * editor for Code Pages.
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

final class EditorScreen implements Hookable {

	public const PAGE_SLUG = 'rawmark-editor';

	private string $hook_suffix = '';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_filter( 'admin_body_class', array( $this, 'add_body_class' ) );
	}

	/**
	 * Marks the editor screen so admin.css can collapse the surrounding
	 * wp-admin chrome (footer, body padding) that would otherwise push the
	 * full-height editor past the bottom of the viewport.
	 */
	public function add_body_class( string $classes ): string {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( self::PAGE_SLUG === $page ) {
			$classes .= ' rawmark-editor-screen';
		}

		return $classes;
	}

	/**
	 * A null parent slug registers a capability-gated admin.php page
	 * without adding a visible menu entry - the standard WordPress core
	 * technique for a page that's only ever reached by direct link.
	 */
	public function register_page(): void {
		$hook_suffix = add_submenu_page(
			null,
			__( 'Edit with Rawmark', 'rawmark' ),
			__( 'Edit with Rawmark', 'rawmark' ),
			Capabilities::CAP,
			self::PAGE_SLUG,
			array( $this, 'render' )
		);

		if ( is_string( $hook_suffix ) ) {
			$this->hook_suffix = $hook_suffix;
			add_action( 'load-' . $hook_suffix, array( $this, 'set_admin_title' ) );
		}
	}

	/**
	 * get_admin_page_title() only searches the top-level $menu when the page
	 * has no parent, and a null parent slug puts this page in $submenu[''].
	 * It is therefore never found, leaving the global $title NULL - which
	 * admin-header.php then hands straight to strip_tags(), emitting a
	 * deprecation notice on every single load under PHP 8.1+. Setting the
	 * title here (load-{hook} runs before admin-header.php) both silences
	 * that and gives the screen a real browser-tab title.
	 */
	public function set_admin_title(): void {
		$GLOBALS['title'] = __( 'Edit with Rawmark', 'rawmark' );
	}

	public function get_hook_suffix(): string {
		return $this->hook_suffix;
	}

	public function render(): void {
		if ( ! current_user_can( Capabilities::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to edit this page\'s code.', 'rawmark' ) );
		}

		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post || ! PageFlag::is_enabled( $post_id ) ) {
			wp_die( esc_html__( 'Rawmark page not found.', 'rawmark' ) );
		}
		?>
		<div id="rawmark-editor-root" class="rawmark-editor-root" data-post-id="<?php echo esc_attr( (string) $post_id ); ?>"></div>
		<?php
	}
}
