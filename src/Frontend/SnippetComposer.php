<?php
/**
 * Expands in-body snippet markers and wraps header/footer content at
 * render time.
 *
 * @package Rawmark
 */

namespace Rawmark\Frontend;

use Rawmark\PostType\Snippet;
use Rawmark\Storage\FooterTemplate;
use Rawmark\Storage\HeaderTemplate;
use Rawmark\Storage\Source;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SnippetComposer {

	private const MARKER_PATTERN = "/<!--\\s*rawmark:snippet\\s+id='(\\d+)'\\s*-->/";

	/**
	 * @param array{html: string, css: string, js: string} $source
	 * @return array{html: string, css: string, js: string}
	 */
	public static function compose( int $page_id, array $source, bool $apply_site_defaults = false ): array {
		$css = $source['css'];
		$js  = $source['js'];

		$resolved = array();

		$html = preg_replace_callback(
			self::MARKER_PATTERN,
			static function ( array $matches ) use ( &$resolved, &$css, &$js ) {
				$id = (int) $matches[1];

				if ( ! array_key_exists( $id, $resolved ) ) {
					$resolved[ $id ] = self::resolve( $id );

					if ( null !== $resolved[ $id ] ) {
						if ( '' !== $resolved[ $id ]['css'] ) {
							$css .= "\n" . $resolved[ $id ]['css'];
						}
						if ( '' !== $resolved[ $id ]['js'] ) {
							$js .= "\n" . $resolved[ $id ]['js'];
						}
					}
				}

				return null !== $resolved[ $id ] ? $resolved[ $id ]['html'] : '';
			},
			$source['html']
		);

		$header_id = (int) get_post_meta( $page_id, '_rawmark_header_snippet', true );
		if ( 0 === $header_id && $apply_site_defaults ) {
			$header_id = HeaderTemplate::get_id();
		}

		$footer_id = (int) get_post_meta( $page_id, '_rawmark_footer_snippet', true );
		if ( 0 === $footer_id && $apply_site_defaults ) {
			$footer_id = FooterTemplate::get_id();
		}

		$header = self::resolve( $header_id );
		$footer = self::resolve( $footer_id );

		if ( null !== $header ) {
			$html = $header['html'] . $html;
			$css  = ( '' !== $header['css'] ? $header['css'] . "\n" : '' ) . $css;
			$js   = ( '' !== $header['js'] ? $header['js'] . "\n" : '' ) . $js;
		}

		if ( null !== $footer ) {
			$html .= $footer['html'];
			$css  .= ( '' !== $footer['css'] ? "\n" . $footer['css'] : '' );
			$js   .= ( '' !== $footer['js'] ? "\n" . $footer['js'] : '' );
		}

		return array( 'html' => $html, 'css' => $css, 'js' => $js );
	}

	/**
	 * A marker or header/footer reference pointing at anything other than a
	 * real rawmark_snippet post - deleted, wrong type, never existed - is
	 * treated identically: absent. No error, nothing rendered there.
	 *
	 * @return array{html: string, css: string, js: string}|null
	 */
	private static function resolve( int $snippet_id ): ?array {
		if ( $snippet_id <= 0 || Snippet::SLUG !== get_post_type( $snippet_id ) ) {
			return null;
		}

		return Source::get( $snippet_id );
	}
}
