<?php
/**
 * The Rawmark Settings screen - which post types get Post Template
 * treatment, plus the WooCommerce Shop redirect when WooCommerce is
 * active.
 *
 * @package Rawmark
 */

namespace Rawmark\Admin;

use Rawmark\Security\Capabilities;
use Rawmark\Storage\PostTemplateTypes;
use Rawmark\Storage\ShopRedirect;
use Rawmark\Support\Hookable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SettingsScreen implements Hookable {

	public const PAGE_SLUG = 'rawmark-settings';

	public const ACTION_SAVE_SHOP_REDIRECT       = 'rawmark_save_shop_redirect';
	public const ACTION_SAVE_POST_TEMPLATE_TYPES = 'rawmark_save_post_template_types';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_post_' . self::ACTION_SAVE_SHOP_REDIRECT, array( $this, 'handle_save_shop_redirect' ) );
		add_action( 'admin_post_' . self::ACTION_SAVE_POST_TEMPLATE_TYPES, array( $this, 'handle_save_post_template_types' ) );
	}

	public function register_page(): void {
		add_submenu_page(
			SnippetsScreen::PAGE_SLUG,
			__( 'Settings', 'rawmark' ),
			__( 'Settings', 'rawmark' ),
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
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Rawmark Settings', 'rawmark' ); ?></h1>

			<h2><?php esc_html_e( 'Post Template', 'rawmark' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SAVE_POST_TEMPLATE_TYPES ); ?>">
				<?php wp_nonce_field( self::ACTION_SAVE_POST_TEMPLATE_TYPES ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Applies to', 'rawmark' ); ?></th>
						<td>
							<?php
							$enabled_types = PostTemplateTypes::get();
							foreach ( PostTemplateTypes::selectable_types() as $post_type ) :
								$type_object = get_post_type_object( $post_type );
								$label       = $type_object ? $type_object->labels->name : $post_type;
								?>
								<label style="display:block;margin-bottom:4px;">
									<input
										type="checkbox"
										name="rawmark_post_template_types[]"
										value="<?php echo esc_attr( $post_type ); ?>"
										<?php checked( in_array( $post_type, $enabled_types, true ) ); ?>
									>
									<?php echo esc_html( $label ); ?> <code><?php echo esc_html( $post_type ); ?></code>
								</label>
							<?php endforeach; ?>
							<p class="description">
								<?php esc_html_e( 'An unflagged post of a checked type renders through the designated Post Template Snippet instead of the theme. An individually flagged post always wins over the template.', 'rawmark' ); ?>
							</p>
							<?php if ( count( $enabled_types ) > 1 ) : ?>
								<p class="description">
									<strong><?php esc_html_e( 'One Snippet, shared across every type checked above.', 'rawmark' ); ?></strong>
									<?php esc_html_e( 'There is a single Post Template, not one per type - if you check more than one type here, write that one Snippet so it makes sense for all of them (or only check one type at a time).', 'rawmark' ); ?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save Post Template Types', 'rawmark' ) ); ?>
			</form>

			<?php if ( class_exists( 'WooCommerce' ) ) : ?>
				<h2><?php esc_html_e( 'WooCommerce', 'rawmark' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SAVE_SHOP_REDIRECT ); ?>">
					<?php wp_nonce_field( self::ACTION_SAVE_SHOP_REDIRECT ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="rawmark-shop-redirect-page"><?php esc_html_e( 'Shop redirect', 'rawmark' ); ?></label>
							</th>
							<td>
								<?php
								wp_dropdown_pages(
									array(
										'name'              => 'rawmark_shop_redirect_page_id',
										'id'                => 'rawmark-shop-redirect-page',
										'selected'          => ShopRedirect::get_page_id(),
										'show_option_none'  => __( '— Use the default WooCommerce Shop —', 'rawmark' ),
										'option_none_value' => '0',
									)
								);
								?>
								<p class="description">
									<?php esc_html_e( 'Visitors to the default Shop archive URL are 301-redirected to this Page instead.', 'rawmark' ); ?>
								</p>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Save Shop Redirect', 'rawmark' ) ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	public function handle_save_shop_redirect(): void {
		check_admin_referer( self::ACTION_SAVE_SHOP_REDIRECT );

		if ( ! current_user_can( Capabilities::CAP ) ) {
			wp_die(
				esc_html__( 'You do not have permission to do that.', 'rawmark' ),
				'',
				array( 'response' => 403 )
			);
		}

		self::save_shop_redirect( isset( $_POST['rawmark_shop_redirect_page_id'] ) ? absint( $_POST['rawmark_shop_redirect_page_id'] ) : 0 );

		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * The actual save logic, kept in its own exit-free method - same
	 * reasoning as SnippetActions::unlink_and_bake(): handle_save_shop_redirect()
	 * ends in wp_safe_redirect() + a bare exit, which a test process can't
	 * survive, so tests call this directly.
	 */
	public static function save_shop_redirect( int $page_id ): void {
		if ( $page_id > 0 && 'page' === get_post_type( $page_id ) ) {
			ShopRedirect::set( $page_id );
		} else {
			ShopRedirect::clear();
		}
	}

	public function handle_save_post_template_types(): void {
		check_admin_referer( self::ACTION_SAVE_POST_TEMPLATE_TYPES );

		if ( ! current_user_can( Capabilities::CAP ) ) {
			wp_die(
				esc_html__( 'You do not have permission to do that.', 'rawmark' ),
				'',
				array( 'response' => 403 )
			);
		}

		$posted = isset( $_POST['rawmark_post_template_types'] ) && is_array( $_POST['rawmark_post_template_types'] )
			? wp_unslash( $_POST['rawmark_post_template_types'] )
			: array();

		self::save_post_template_types( array_map( 'sanitize_key', $posted ) );

		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * The actual save logic, kept in its own exit-free method - same
	 * reasoning as save_shop_redirect() above. PostTemplateTypes::set()
	 * already intersects against selectable_types(), so an unchecked/
	 * invalid post type submitted here is silently dropped rather than
	 * stored - this method doesn't need to duplicate that filtering.
	 *
	 * @param string[] $types
	 */
	public static function save_post_template_types( array $types ): void {
		PostTemplateTypes::set( $types );
	}
}
