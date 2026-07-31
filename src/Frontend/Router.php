<?php
/**
 * template_include hook and access checks for Code Pages.
 *
 * @package Rawmark
 */

namespace Rawmark\Frontend;

use Rawmark\PostType\CodePage;
use Rawmark\Storage\PageFlag;
use Rawmark\Support\Hookable;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Router implements Hookable {

	public function register(): void {
		add_filter( 'template_include', array( $this, 'maybe_render' ) );
	}

	/**
	 * Returns a template file path - never echoes and exits, which would
	 * skip WordPress's shutdown chain that page-cache plugins depend on.
	 *
	 * This path bypasses the normal template pipeline, so it also bypasses
	 * the access checks that pipeline performs. They are repeated here
	 * explicitly.
	 */
	public function maybe_render( string $template ): string {
		$post = get_queried_object();

		if ( ! $post instanceof WP_Post || ! $this->is_rawmark_page( $post ) ) {
			return $template;
		}

		if ( 'publish' !== $post->post_status && ! current_user_can( 'read_post', $post->ID ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			return get_404_template();
		}

		if ( post_password_required( $post ) ) {
			return $template;
		}

		$this->strip_theme_output();

		return RAWMARK_DIR . '/templates/code-page.php';
	}

	/**
	 * The CPT branch is transitional. Migration (Task 3) converts the last
	 * of those posts into Pages, and Task 9 deletes this branch with the
	 * post type.
	 */
	private function is_rawmark_page( WP_Post $post ): bool {
		if ( is_singular( 'page' ) && PageFlag::is_enabled( $post->ID ) ) {
			return true;
		}

		return is_singular( CodePage::SLUG );
	}

	/**
	 * A Code Page renders no theme markup and no blocks, so the presentational
	 * assets WordPress emits for those are pure noise here - a block theme
	 * alone contributes its own stylesheet plus tens of kilobytes of
	 * block-library and global-styles CSS. Leaving them in would break the
	 * product's central promise of a clean, readable standalone document.
	 *
	 * wp_head()/wp_footer() themselves still run when the page opts in, so
	 * SEO and analytics plugins keep working - this only removes the
	 * theme/block presentation layer, which is what "no theme stylesheet"
	 * means in practice.
	 *
	 * Safe to hook here: template_include fires before the template calls
	 * wp_head(), so neither wp_enqueue_scripts nor the emoji actions have run.
	 */
	private function strip_theme_output(): void {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
		remove_action( 'wp_head', 'wp_resource_hints', 2 );

		// theme.json @font-face blocks - these point at the active theme's
		// font files, which a Code Page never uses.
		remove_action( 'wp_head', 'wp_print_font_faces', 50 );

		// Speculation rules embed the theme's own paths and only describe
		// prefetching for theme-rendered navigation.
		remove_action( 'wp_footer', 'wp_print_speculation_rules' );

		// The template emits its own <title> and viewport meta. Core emits
		// both too - one of two title callbacks depending on whether the
		// active theme is block or classic - which would produce a document
		// with two <title> elements and two viewport tags. Invalid HTML, and
		// search engines pick whichever they like.
		remove_action( 'wp_head', '_wp_render_title_tag', 1 );
		remove_action( 'wp_head', '_block_template_render_title_tag', 1 );
		remove_action( 'wp_head', '_block_template_viewport_meta_tag', 0 );

		// Skip-link markup and styles belong to the block template canvas,
		// which this page does not use.
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_block_template_skip_link' );
		remove_action( 'wp_footer', 'the_block_template_skip_link' );

		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_theme_assets' ), PHP_INT_MAX );
	}

	/**
	 * Runs last on wp_enqueue_scripts so it sees everything others queued.
	 */
	public function dequeue_theme_assets(): void {
		$core_presentation = array(
			'wp-block-library',
			'wp-block-library-theme',
			'global-styles',
			'classic-theme-styles',
			'wp-emoji-styles',
			'wp-img-auto-sizes-contain',
			'core-block-supports',
			'wp-block-template-skip-link',
		);

		foreach ( $core_presentation as $handle ) {
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
		}

		// Anything served out of the active theme, whatever it named its handles.
		$theme_uris = array_unique(
			array(
				get_template_directory_uri(),
				get_stylesheet_directory_uri(),
			)
		);

		foreach ( array( wp_styles(), wp_scripts() ) as $collection ) {
			foreach ( (array) $collection->queue as $handle ) {
				$src = $collection->registered[ $handle ]->src ?? '';

				if ( ! is_string( $src ) || '' === $src ) {
					continue;
				}

				foreach ( $theme_uris as $uri ) {
					if ( false !== strpos( $src, $uri ) ) {
						$collection->dequeue( $handle );
						break;
					}
				}
			}
		}
	}
}
