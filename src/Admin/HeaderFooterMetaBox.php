<?php
/**
 * Per-page Header/Footer snippet selection, shown as a metabox on the
 * classic Page edit screen for flagged Rawmark pages.
 *
 * @package Rawmark
 */

namespace Rawmark\Admin;

use Rawmark\PostType\Snippet;
use Rawmark\Storage\PageFlag;
use Rawmark\Storage\SnippetLink;
use Rawmark\Support\Hookable;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HeaderFooterMetaBox implements Hookable {

	private const NONCE_ACTION = 'rawmark_header_footer';
	private const NONCE_NAME   = 'rawmark_header_footer_nonce';

	public function register(): void {
		add_action( 'add_meta_boxes_page', array( $this, 'add_box' ) );
		add_action( 'save_post_page', array( $this, 'save' ) );
	}

	/**
	 * Only for a flagged page. On an ordinary Page this content is never
	 * rendered by anything, so offering the fields there would just be
	 * confusing.
	 */
	public function add_box( WP_Post $post ): void {
		if ( ! PageFlag::is_enabled( $post->ID ) ) {
			return;
		}

		add_meta_box(
			'rawmark-header-footer',
			__( 'Rawmark Header / Footer', 'rawmark' ),
			array( $this, 'render' ),
			'page',
			'side',
			'default'
		);
	}

	public function render( WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$linked    = get_posts(
			array(
				'post_type'      => Snippet::SLUG,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_key'       => SnippetLink::META_KEY,
				'meta_value'     => '1',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$header_id = (int) get_post_meta( $post->ID, '_rawmark_header_snippet', true );
		$footer_id = (int) get_post_meta( $post->ID, '_rawmark_footer_snippet', true );
		?>
		<p>
			<label for="rawmark-header-snippet"><?php esc_html_e( 'Header', 'rawmark' ); ?></label>
			<select name="rawmark_header_snippet" id="rawmark-header-snippet" style="width:100%;">
				<option value="0"><?php esc_html_e( 'None', 'rawmark' ); ?></option>
				<?php foreach ( $linked as $snippet ) : ?>
					<option value="<?php echo esc_attr( (string) $snippet->ID ); ?>" <?php selected( $header_id, $snippet->ID ); ?>>
						<?php echo esc_html( $snippet->post_title ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="rawmark-footer-snippet"><?php esc_html_e( 'Footer', 'rawmark' ); ?></label>
			<select name="rawmark_footer_snippet" id="rawmark-footer-snippet" style="width:100%;">
				<option value="0"><?php esc_html_e( 'None', 'rawmark' ); ?></option>
				<?php foreach ( $linked as $snippet ) : ?>
					<option value="<?php echo esc_attr( (string) $snippet->ID ); ?>" <?php selected( $footer_id, $snippet->ID ); ?>>
						<?php echo esc_html( $snippet->post_title ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	public function save( int $post_id ): void {
		if (
			! isset( $_POST[ self::NONCE_NAME ] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION )
		) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$this->save_reference(
			$post_id,
			'_rawmark_header_snippet',
			isset( $_POST['rawmark_header_snippet'] ) ? absint( $_POST['rawmark_header_snippet'] ) : 0
		);
		$this->save_reference(
			$post_id,
			'_rawmark_footer_snippet',
			isset( $_POST['rawmark_footer_snippet'] ) ? absint( $_POST['rawmark_footer_snippet'] ) : 0
		);
	}

	/**
	 * Re-validates that the posted ID is genuinely a linked snippet before
	 * storing it - never trusts a client-supplied ID as the sole gate.
	 */
	private function save_reference( int $post_id, string $meta_key, int $snippet_id ): void {
		if ( $snippet_id > 0 && SnippetLink::is_linked( $snippet_id ) ) {
			update_post_meta( $post_id, $meta_key, $snippet_id );
		} else {
			delete_post_meta( $post_id, $meta_key );
		}
	}
}
