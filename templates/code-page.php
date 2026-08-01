<?php
/**
 * Composes and prints the standalone Code Page document. Returned by
 * Rawmark\Frontend\Router via template_include. No theme header/footer,
 * no theme stylesheet, no wrapper markup.
 *
 * @package Rawmark
 */

use Rawmark\Frontend\Escaper;
use Rawmark\Frontend\PostDataTags;
use Rawmark\Frontend\SnippetComposer;
use Rawmark\Storage\PageFlag;
use Rawmark\Storage\PostTemplate;
use Rawmark\Storage\Source;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// get_queried_object(), not the global $post, so this doesn't depend on
// exactly when WordPress populates that global relative to template
// inclusion - Router already validated this is our post type.
$post = get_queried_object();

// A flagged Page/Post renders its own source. An ordinary unflagged Post
// only ever reaches this template via the Post Template fallback (see
// Router::is_rawmark_page()), so its content comes from the designated
// template Snippet instead.
$source_id = PageFlag::is_enabled( $post->ID ) ? $post->ID : PostTemplate::get_id();

$source   = Source::get( $source_id );
$settings = $source['settings'];

$title       = '' !== $settings['seo_title'] ? $settings['seo_title'] : get_the_title( $post );
$description = $settings['seo_description'];

// Merge tags resolve for any Post being rendered here - individually
// flagged or via the template fallback - never for a Page, which has no
// "current post" context to substitute.
if ( 'post' === $post->post_type ) {
	$source['html'] = PostDataTags::resolve( $post->ID, $source['html'] );
	$source['css']  = PostDataTags::resolve( $post->ID, $source['css'] );
	$source['js']   = PostDataTags::resolve( $post->ID, $source['js'] );
}

$composed = SnippetComposer::compose( $post->ID, $source );

$html = $composed['html'];
$css  = Escaper::escape_style( $composed['css'] );
$js   = Escaper::escape_script( $composed['js'] );

$body_class = 'rawmark-page rawmark-page--' . sanitize_html_class( $post->post_name );
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
