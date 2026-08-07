<?php
/**
 * Handles POST /rawmark/v1/preview - renders unsaved editor content through
 * the real composition pipeline templates/code-page.php uses, without
 * persisting anything.
 *
 * @package Rawmark
 */

namespace Rawmark\Rest;

use Rawmark\Frontend\Escaper;
use Rawmark\Frontend\PostDataTags;
use Rawmark\Frontend\PostLoopTags;
use Rawmark\Frontend\SnippetComposer;
use Rawmark\Security\Capabilities;
use Rawmark\Storage\PageFlag;
use Rawmark\Storage\PostTemplateTypes;
use Rawmark\Storage\Source;
use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PreviewController {

	/**
	 * @return true|WP_Error
	 */
	public function check_permission( WP_REST_Request $request ) {
		if ( ! current_user_can( Capabilities::CAP ) ) {
			return new WP_Error(
				'rawmark_forbidden',
				__( 'You do not have permission to do that.', 'rawmark' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$post_id     = (int) $request->get_param( 'postId' );
		$preview_id  = (int) $request->get_param( 'previewPostId' );
		$resolved_id = $preview_id > 0 ? $preview_id : $post_id;

		if ( ! current_user_can( 'edit_post', $resolved_id ) ) {
			return new WP_Error(
				'rawmark_forbidden',
				__( 'You do not have permission to do that.', 'rawmark' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function render_preview( WP_REST_Request $request ) {
		$post_id    = (int) $request->get_param( 'postId' );
		$preview_id = (int) $request->get_param( 'previewPostId' );
		$post       = get_post( $preview_id > 0 ? $preview_id : $post_id );

		if ( ! $post ) {
			return new WP_Error(
				'rawmark_not_found',
				__( 'Post not found.', 'rawmark' ),
				array( 'status' => 404 )
			);
		}

		// Prime the query BEFORE anything renders, not just before
		// wp_head()/wp_footer() (its original job). render_block() seeds
		// every top-level block's postId/postType context from the global
		// $post, and dynamic blocks that resolve "the current product"
		// themselves - SureCart's product-page does
		// 'post__in' => [ $attributes['product_post_id'] ?? get_the_ID() ]
		// and sc_get_product() reads the same global - come back empty when
		// it is null. A REST request has no front-end query of its own, so
		// without this the do_blocks() pass below renders those blocks as
		// empty strings and the preview iframe shows a blank page.
		$this->prime_query_for( $post );

		$settings = Source::get( $post_id )['settings'];

		$html = PostLoopTags::resolve( (string) $request->get_param( 'html' ) );
		$css  = (string) $request->get_param( 'css' );
		$js   = (string) $request->get_param( 'js' );

		if ( PostTemplateTypes::is_eligible( $post->post_type ) ) {
			$html = PostDataTags::resolve( $post->ID, $html );
			$css  = PostDataTags::resolve( $post->ID, $css );
			$js   = PostDataTags::resolve( $post->ID, $js );
		}

		$source = array(
			'html'     => $html,
			'css'      => $css,
			'js'       => $js,
			'settings' => $settings,
		);

		$composed = SnippetComposer::compose( $post->ID, $source, PageFlag::is_enabled( $post->ID ) );

		$body = $settings['enable_blocks'] ? do_blocks( $composed['html'] ) : $composed['html'];
		$body = do_shortcode( $body );

		return new WP_REST_Response(
			array(
				'srcdoc' => $this->build_document(
					$post,
					$body,
					Escaper::escape_style( $composed['css'] ),
					Escaper::escape_script( $composed['js'] ),
					$settings
				),
			),
			200
		);
	}

	/**
	 * A REST request never runs WordPress's normal front-end query
	 * resolution, so two things blocks and plugins rely on are missing
	 * here: the global $post (render_block() seeds postId/postType block
	 * context from it; SureCart's get_the_ID()/sc_get_product() product
	 * resolution reads it directly), and is_singular()-style conditional
	 * tags that a plugin's own wp_enqueue_scripts hook checks (SureCart's
	 * product asset enqueuing, for one) default to false. Priming a real
	 * query against the resolved post's own permalink first - the same
	 * technique WP_UnitTestCase::go_to() uses - restores both, so
	 * do_blocks(), wp_head(), and wp_footer() behave exactly as they
	 * would on a real visit to that post. Safe to mutate
	 * $wp_query/$wp_the_query here: nothing later in a REST request's
	 * lifecycle depends on the query this endpoint was dispatched
	 * through.
	 */
	private function prime_query_for( WP_Post $post ): void {
		global $wp, $wp_query, $wp_the_query, $wp_rewrite;

		$wp_the_query = new WP_Query();
		$wp_query     = $wp_the_query;

		$public_query_vars  = $wp->public_query_vars;
		$private_query_vars = $wp->private_query_vars;

		$wp                     = new WP();
		$wp->public_query_vars  = $public_query_vars;
		$wp->private_query_vars = $private_query_vars;

		$parts = wp_parse_url( get_permalink( $post ) );
		$req   = $parts['path'] ?? '';
		$query = '';

		if ( isset( $parts['query'] ) ) {
			$req  .= '?' . $parts['query'];
			$query = $parts['query'];
		}

		$_SERVER['REQUEST_URI'] = $req;
		unset( $_SERVER['PATH_INFO'] );

		$wp_rewrite->init();
		$wp->main( $query );
	}

	/**
	 * Same document shape templates/code-page.php prints, built as a
	 * string instead of echoed - a REST response needs the whole document
	 * as one JSON-serializable value, not direct HTTP output. The query
	 * prime this relies on (wp_head()/wp_footer() conditional tags,
	 * global $post) already happened in render_preview(), before the
	 * do_blocks() pass - see the comment there for why the order matters.
	 */
	private function build_document( WP_Post $post, string $html, string $css, string $js, array $settings ): string {
		$title       = '' !== $settings['seo_title'] ? $settings['seo_title'] : get_the_title( $post );
		$description = $settings['seo_description'];
		$body_class  = 'rawmark-page rawmark-page--' . sanitize_html_class( $post->post_name );

		ob_start();
		?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $title ); ?></title>
<?php if ( '' !== $description ) : ?>
<meta name="description" content="<?php echo esc_attr( $description ); ?>">
<?php endif; ?>
<?php if ( $settings['use_wp_head'] ) : ?>
<?php wp_head(); ?>
<?php endif; ?>
<?php if ( '' !== $css ) : ?>
<style>
<?php
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted developer code, never sanitized by design. See SECURITY-AND-STANDARDS.md section 2.
echo $css;
// phpcs:enable
?>
</style>
<?php endif; ?>
</head>
<body class="<?php echo esc_attr( $body_class ); ?>">
<?php
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted developer code, never sanitized by design. See SECURITY-AND-STANDARDS.md section 2.
echo $html;
// phpcs:enable
?>
<?php if ( '' !== $js ) : ?>
<script>
<?php
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted developer code, never sanitized by design. See SECURITY-AND-STANDARDS.md section 2.
echo $js;
// phpcs:enable
?>
</script>
<?php endif; ?>
<?php if ( $settings['use_wp_footer'] ) : ?>
<?php wp_footer(); ?>
<?php endif; ?>
</body>
</html>
<?php
		return ob_get_clean();
	}
}
