<?php
/**
 * Finds and counts every Page that places a given snippet, as a header,
 * footer, or in-body marker.
 *
 * @package Rawmark
 */

namespace Rawmark\Storage;

use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SnippetUsage {

	/**
	 * Computed fresh on every call, never cached or stored - a placement
	 * reference is the only source of truth, so this can never drift out
	 * of sync with what actually renders.
	 *
	 * @return int[]
	 */
	public static function find_placements( int $snippet_id ): array {
		$query = new WP_Query(
			array(
				'post_type'      => 'page',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'   => '_rawmark_header_snippet',
						'value' => $snippet_id,
					),
					array(
						'key'   => '_rawmark_footer_snippet',
						'value' => $snippet_id,
					),
					array(
						'key'     => Source::META_KEY,
						'value'   => self::marker_needle( $snippet_id ),
						'compare' => 'LIKE',
					),
				),
			)
		);

		return array_map( 'intval', $query->posts );
	}

	public static function count_placements( int $snippet_id ): int {
		return count( self::find_placements( $snippet_id ) );
	}

	/**
	 * The single-quoted closing `'` right after the digits is what stops a
	 * shorter ID's needle matching inside a longer one - `id='1'` cannot
	 * appear as a substring of `id='11'`, because the character immediately
	 * following the `1` differs.
	 */
	public static function marker_needle( int $snippet_id ): string {
		return "rawmark:snippet id='" . $snippet_id . "'";
	}
}
