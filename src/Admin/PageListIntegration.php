<?php
/**
 * "Edit with Rawmark" row action and the Pages list column.
 *
 * @package Rawmark
 */

namespace Rawmark\Admin;

use Rawmark\Storage\PageFlag;
use Rawmark\Support\Hookable;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PageListIntegration implements Hookable {

	private const COLUMN_ID = 'rawmark';

	public function register(): void {
		add_filter( 'page_row_actions', array( $this, 'add_row_action' ), 10, 2 );
		add_filter( 'manage_pages_columns', array( $this, 'add_column' ) );
		add_action( 'manage_pages_custom_column', array( $this, 'render_column' ), 10, 2 );
	}

	/**
	 * @param array<string, string> $actions
	 * @return array<string, string>
	 */
	public function add_row_action( array $actions, WP_Post $post ): array {
		if ( ! PageModeToggle::user_may_toggle( $post->ID ) ) {
			return $actions;
		}

		if ( PageFlag::is_enabled( $post->ID ) ) {
			$actions['rawmark'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=' . EditorScreen::PAGE_SLUG . '&post=' . $post->ID ) ),
				esc_html__( 'Edit with Rawmark', 'rawmark' )
			);
		} else {
			$actions['rawmark'] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( PageModeToggle::enable_url( $post->ID ) ),
				esc_html__( 'Edit with Rawmark', 'rawmark' )
			);
		}

		return $actions;
	}

	/**
	 * @param array<string, string> $columns
	 * @return array<string, string>
	 */
	public function add_column( array $columns ): array {
		$columns[ self::COLUMN_ID ] = __( 'Rawmark', 'rawmark' );

		return $columns;
	}

	public function render_column( string $column, int $post_id ): void {
		if ( self::COLUMN_ID !== $column ) {
			return;
		}

		if ( PageFlag::is_enabled( $post_id ) ) {
			echo '<span class="dashicons dashicons-editor-code" title="';
			echo esc_attr__( 'Built with Rawmark', 'rawmark' );
			echo '"></span>';
		}
	}
}
