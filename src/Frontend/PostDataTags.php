<?php
/**
 * Expands <!-- rawmark:post_xxx --> markers with the current Post's real
 * data at render time.
 *
 * @package Rawmark
 */

namespace Rawmark\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PostDataTags {

	private const PATTERN = "/<!--\\s*rawmark:(post_title|post_content|post_excerpt|post_date|featured_image|permalink|author_name)\\s*-->/";

	public static function resolve( int $post_id, string $html ): string {
		return preg_replace_callback(
			self::PATTERN,
			static function ( array $matches ) use ( $post_id ): string {
				return self::value( $matches[1], $post_id );
			},
			$html
		);
	}

	private static function value( string $tag, int $post_id ): string {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return '';
		}

		switch ( $tag ) {
			case 'post_title':
				return esc_html( get_the_title( $post_id ) );
			case 'post_content':
				return apply_filters( 'the_content', $post->post_content );
			case 'post_excerpt':
				return esc_html( get_the_excerpt( $post_id ) );
			case 'post_date':
				return esc_html( get_the_date( '', $post_id ) );
			case 'featured_image':
				return get_the_post_thumbnail( $post_id );
			case 'permalink':
				return esc_url( get_permalink( $post_id ) );
			case 'author_name':
				return esc_html( get_the_author_meta( 'display_name', $post->post_author ) );
			default:
				return '';
		}
	}
}
