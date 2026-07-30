<?php
/**
 * Composes and prints the standalone Code Page document. Returned by
 * Rawmark\Frontend\Router via template_include. No theme header/footer,
 * no theme stylesheet, no wrapper markup.
 *
 * @package Rawmark
 */

use Rawmark\Frontend\Escaper;
use Rawmark\Storage\Source;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// get_queried_object(), not the global $post, so this doesn't depend on
// exactly when WordPress populates that global relative to template
// inclusion - Router already validated this is our post type.
$post = get_queried_object();

$source   = Source::get( $post->ID );
$settings = $source['settings'];

$title       = '' !== $settings['seo_title'] ? $settings['seo_title'] : get_the_title( $post );
$description = $settings['seo_description'];

$html = $source['html'];
$css  = Escaper::escape_style( $source['css'] );
$js   = Escaper::escape_script( $source['js'] );

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
