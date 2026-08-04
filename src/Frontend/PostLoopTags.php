<?php
/**
 * Expands <!-- rawmark:post_loop --> ... <!-- /rawmark:post_loop --> blocks,
 * repeating the block's own inner HTML once per matching Post and
 * substituting that Post's data via PostDataTags.
 *
 * @package Rawmark
 */

namespace Rawmark\Frontend;

use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PostLoopTags {

	private const PATTERN = "/<!--\\s*rawmark:post_loop((?:\\s+[a-z_]+='[^']*')*)\\s*-->(.*?)<!--\\s*\\/rawmark:post_loop\\s*-->/s";

	private const DEFAULT_COUNT = 5;
	private const MAX_COUNT     = 50;

	public static function resolve( string $html ): string {
		return preg_replace_callback(
			self::PATTERN,
			static function ( array $matches ): string {
				return self::render_loop( $matches[1], $matches[2] );
			},
			$html
		);
	}

	private static function render_loop( string $attr_string, string $template ): string {
		$attrs = self::parse_attributes( $attr_string );

		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => self::clamp_count( $attrs['count'] ?? '' ),
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		);

		if ( '' !== ( $attrs['category'] ?? '' ) ) {
			$args['category_name'] = $attrs['category'];
		}

		if ( '' !== ( $attrs['tag'] ?? '' ) ) {
			$args['tag'] = $attrs['tag'];
		}

		$query  = new WP_Query( $args );
		$output = '';

		foreach ( $query->posts as $post_id ) {
			$output .= PostDataTags::resolve( (int) $post_id, $template );
		}

		return $output;
	}

	/**
	 * @return array<string, string>
	 */
	private static function parse_attributes( string $attr_string ): array {
		preg_match_all( "/([a-z_]+)='([^']*)'/", $attr_string, $matches, PREG_SET_ORDER );

		$attrs = array();

		foreach ( $matches as $match ) {
			$attrs[ $match[1] ] = $match[2];
		}

		return $attrs;
	}

	private static function clamp_count( string $raw ): int {
		$count = '' !== $raw ? (int) $raw : self::DEFAULT_COUNT;
		$count = $count > 0 ? $count : self::DEFAULT_COUNT;

		return min( $count, self::MAX_COUNT );
	}
}
