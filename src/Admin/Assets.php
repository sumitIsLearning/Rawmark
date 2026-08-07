<?php
/**
 * Conditional enqueue of the editor bundle, editor screen only.
 *
 * @package Rawmark
 */

namespace Rawmark\Admin;

use Rawmark\Storage\PostTemplateTypes;
use Rawmark\Support\Hookable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Assets implements Hookable {

	private EditorScreen $editor_screen;

	public function __construct( EditorScreen $editor_screen ) {
		$this->editor_screen = $editor_screen;
	}

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
	}

	/**
	 * Compares the passed hook suffix directly rather than
	 * get_current_screen()->id, which is unreliable for an
	 * add_submenu_page( null, ... ) page.
	 */
	public function maybe_enqueue( string $hook_suffix ): void {
		if ( '' === $this->editor_screen->get_hook_suffix() || $hook_suffix !== $this->editor_screen->get_hook_suffix() ) {
			return;
		}

		// wp.media(), used by the editor's "Insert Media" button - the same
		// call core makes on the classic Page/Post edit screen to make the
		// media library modal available.
		wp_enqueue_media();

		wp_enqueue_script(
			'rawmark-editor',
			RAWMARK_URL . 'assets/dist/editor.js',
			array( 'react', 'react-dom' ),
			RAWMARK_VERSION,
			true
		);

		wp_enqueue_style(
			'rawmark-admin',
			RAWMARK_URL . 'assets/dist/admin.css',
			array(),
			RAWMARK_VERSION
		);

		wp_localize_script(
			'rawmark-editor',
			'rawmarkEditor',
			array(
				'restUrl'      => esc_url_raw( rest_url( 'rawmark/v1' ) ),
				'wpRestRoot'   => esc_url_raw( rest_url() ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'postId'       => isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0,
				'homeUrl'      => esc_url_raw( home_url( '/' ) ),
				'snippetsUrl'  => SnippetsScreen::url(),
				'previewTypes' => $this->preview_types(),
			)
		);
	}

	/**
	 * @return array<int, array{type: string, restBase: string, label: string}>
	 */
	public function preview_types(): array {
		$types = array();

		foreach ( PostTemplateTypes::selectable_types() as $post_type ) {
			$object = get_post_type_object( $post_type );

			if ( ! $object ) {
				continue;
			}

			$types[] = array(
				'type'     => $post_type,
				'restBase' => $object->rest_base ? $object->rest_base : $post_type,
				'label'    => $object->labels->name,
			);
		}

		return $types;
	}
}
