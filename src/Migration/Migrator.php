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

	/**
	 * Conversions that failed during the most recent migrate() call. Reset
	 * at the top of every migrate(), so it always describes that one run.
	 */
	private static int $failed = 0;

	public static function failed_count(): int {
		return self::$failed;
	}

	/**
	 * Hooked to `init`, not `plugins_loaded`. Migration issues real
	 * wp_update_post() writes, and at plugins_loaded none of the site is
	 * assembled yet: kses_init_filters() has not run, no other plugin has
	 * registered its post types, taxonomies or save-time filters, and no
	 * custom post statuses exist. Converting rows in that half-built state
	 * means these writes are seen by a different set of filters than every
	 * other write the site will ever make. Running at `init` costs nothing
	 * - this is a pre-completion-only path - and makes the conversion
	 * behave like any ordinary post update.
	 */
	public static function run_if_needed(): void {
		if ( (int) get_option( self::VERSION_OPTION, 0 ) >= self::TARGET_VERSION ) {
			return;
		}

		self::migrate();

		// Only declare migration finished when every legacy row actually
		// converted. Bumping the version past a failure would strand that
		// row permanently: nothing registers rawmark_code_page any more, so
		// an unconverted post is invisible in wp-admin, absent from every
		// query, and has no post type left to be restored into - silent,
		// irreversible loss of the user's page.
		//
		// Leaving the option unset means the next request retries, and
		// retries only the stragglers: rows that did convert are now
		// post_type 'page' and no longer match the query below. That is the
		// same property that makes a partially-completed run resumable. The
		// cost of a permanently failing row is one repeated query and one
		// repeated error_log line per request - loud, bounded, and
		// self-healing the moment the underlying cause is fixed. A strictly
		// better failure mode than losing pages quietly.
		if ( 0 === self::$failed ) {
			update_option( self::VERSION_OPTION, self::TARGET_VERSION, false );
		}
	}

	/**
	 * Converts every rawmark_code_page post into a Page, preserving its
	 * source meta untouched. Returns the number successfully converted.
	 */
	public static function migrate(): int {
		self::$failed = 0;

		$posts = get_posts(
			array(
				'post_type' => 'rawmark_code_page',
				// Not 'any'. WP_Query expands 'any' to every status except
				// those registered with exclude_from_search => true, which
				// silently drops 'trash' and 'auto-draft'. Those rows would
				// be skipped, the version option bumped, and - because the
				// post type no longer exists anywhere in this codebase - a
				// trashed Code Page could never be restored again. Listing
				// statuses explicitly is the difference between "not
				// migrated yet" and "unrecoverable".
				'post_status'      => array_keys( get_post_stati() ),
				'numberposts'      => -1,
				'suppress_filters' => false,
			)
		);

		if ( ! $posts ) {
			return 0;
		}

		$converted = 0;

		foreach ( $posts as $post ) {
			// Only post_type is passed - no explicit slug handling here.
			// wp_update_post() re-runs wp_unique_post_slug() as part of
			// updating the post, which is where a genuine collision against
			// an existing Page gets resolved (by renaming the incoming
			// post, e.g. "home" -> "home-2"). That collision handling is
			// entirely WP core's, not this class's; see
			// test_colliding_slug_is_renamed_by_core() for the observed
			// behavior this relies on.
			$result = wp_update_post(
				wp_slash(
					array(
						'ID'        => $post->ID,
						'post_type' => 'page',
					)
				),
				true
			);

			// A conversion can genuinely fail - another plugin's
			// wp_insert_post_data filter rejecting it, an invalid parent, a
			// database error. Flagging a post that is still a
			// rawmark_code_page would be worse than useless:
			// PageFlag::is_enabled() tests post_type and would refuse to
			// honour the meta, leaving a permanently invisible orphan that
			// looks migrated to any later reader.
			if ( is_wp_error( $result ) || ! $result ) {
				++self::$failed;

				error_log(
					sprintf(
						'Rawmark migration: failed to convert post %d to a page: %s',
						$post->ID,
						is_wp_error( $result ) ? $result->get_error_message() : 'unknown error'
					)
				);

				continue;
			}

			PageFlag::enable( $post->ID );
			++$converted;
		}

		// No flush_rewrite_rules() here, deliberately. Rewrite rules are
		// derived entirely from *registered post types and taxonomies* at
		// init; individual post rows contribute nothing to them. Converting
		// rows from one type to another therefore cannot invalidate a
		// single rule, and the target - 'page' - already has the correct
		// rules, which core registers unconditionally.
		//
		// The flush that used to live here was actively harmful: this class
		// ran on plugins_loaded, before init, so no plugin's post types or
		// taxonomies were registered yet. Flushing there persisted a
		// truncated rule set into the rewrite_rules option - dropping
		// categories, tags, post formats and every third-party CPT - and
		// broke those permalinks site-wide until something else flushed.
		// Plugin::activate() already flushes at the one point where that is
		// genuinely the right thing to do.
		return $converted;
	}
}
