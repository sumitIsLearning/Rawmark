<?php
/**
 * One-time conversion of rawmark_code_page posts into Pages.
 *
 * @package Rawmark
 */

namespace Rawmark\Migration;

use Rawmark\Storage\PageFlag;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Migrator {

	public const VERSION_OPTION = 'rawmark_migration_version';

	private const TARGET_VERSION = 2;

	public static function run_if_needed(): void {
		if ( (int) get_option( self::VERSION_OPTION, 0 ) >= self::TARGET_VERSION ) {
			return;
		}

		self::migrate();

		update_option( self::VERSION_OPTION, self::TARGET_VERSION, false );
	}

	/**
	 * Converts every rawmark_code_page post into a Page, preserving its
	 * source meta untouched. Returns the number converted.
	 */
	public static function migrate(): int {
		$posts = get_posts(
			array(
				'post_type'        => 'rawmark_code_page',
				'post_status'      => 'any',
				'numberposts'      => -1,
				'suppress_filters' => false,
			)
		);

		if ( ! $posts ) {
			return 0;
		}

		foreach ( $posts as $post ) {
			// Only post_type is passed - no explicit slug handling here.
			// wp_update_post() re-runs wp_unique_post_slug() as part of
			// updating the post, which is where a genuine collision against
			// an existing Page gets resolved (by renaming the incoming
			// post, e.g. "home" -> "home-2"). That collision handling is
			// entirely WP core's, not this class's; see
			// test_colliding_slug_is_renamed_by_core() for the observed
			// behavior this relies on.
			wp_update_post(
				wp_slash(
					array(
						'ID'        => $post->ID,
						'post_type' => 'page',
					)
				)
			);

			PageFlag::enable( $post->ID );
		}

		flush_rewrite_rules();

		return count( $posts );
	}
}
