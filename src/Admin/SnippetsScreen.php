<?php
/**
 * The top-level Rawmark menu and the Snippets list screen.
 *
 * @package Rawmark
 */

namespace Rawmark\Admin;

use Rawmark\PostType\Snippet;
use Rawmark\Security\Capabilities;
use Rawmark\Storage\SnippetLink;
use Rawmark\Storage\SnippetUsage;
use Rawmark\Support\Hookable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SnippetsScreen implements Hookable {

	public const PAGE_SLUG = 'rawmark-snippets';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
	}

	public function register_page(): void {
		add_menu_page(
			__( 'Rawmark', 'rawmark' ),
			__( 'Rawmark', 'rawmark' ),
			Capabilities::CAP,
			self::PAGE_SLUG,
			array( $this, 'render' ),
			'dashicons-editor-code'
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Snippets', 'rawmark' ),
			__( 'Snippets', 'rawmark' ),
			Capabilities::CAP,
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	public static function url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}

	public function render(): void {
		if ( ! current_user_can( Capabilities::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'rawmark' ) );
		}

		$snippets = get_posts(
			array(
				'post_type'      => Snippet::SLUG,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Snippets', 'rawmark' ); ?></h1>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'rawmark' ); ?></th>
						<th><?php esc_html_e( 'Mode', 'rawmark' ); ?></th>
						<th><?php esc_html_e( 'Used on', 'rawmark' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'rawmark' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! $snippets ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'No snippets yet.', 'rawmark' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $snippets as $snippet ) : ?>
					<?php
					$linked = SnippetLink::is_linked( $snippet->ID );
					$count  = SnippetUsage::count_placements( $snippet->ID );
					?>
					<tr>
						<td><?php echo esc_html( $snippet->post_title ); ?></td>
						<td>
							<?php if ( $linked ) : ?>
								<a href="<?php echo esc_url( SnippetActions::unlink_url( $snippet->ID ) ); ?>"><?php esc_html_e( 'Unlink', 'rawmark' ); ?></a>
							<?php else : ?>
								<a href="<?php echo esc_url( SnippetActions::link_url( $snippet->ID ) ); ?>"><?php esc_html_e( 'Link', 'rawmark' ); ?></a>
							<?php endif; ?>
						</td>
						<td>
							<?php
							printf(
								/* translators: %d: number of pages */
								esc_html( _n( '%d page', '%d pages', $count, 'rawmark' ) ),
								(int) $count
							);
							?>
						</td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . EditorScreen::PAGE_SLUG . '&post=' . $snippet->ID ) ); ?>"><?php esc_html_e( 'Edit', 'rawmark' ); ?></a>
							|
							<a
								href="<?php echo esc_url( SnippetActions::delete_url( $snippet->ID ) ); ?>"
								onclick="return confirm('<?php
									echo esc_js(
										sprintf(
											/* translators: %d: number of pages */
											_n( 'This snippet is used on %d page. Delete anyway?', 'This snippet is used on %d pages. Delete anyway?', $count, 'rawmark' ),
											$count
										)
									);
								?>');"
							><?php esc_html_e( 'Delete', 'rawmark' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
