<?php
/**
 * Reads and writes which post types the Post Template fallback applies to.
 *
 * @package Rawmark
 */

namespace Rawmark\Storage;

use Rawmark\PostType\Snippet;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PostTemplateTypes {

	public const OPTION_KEY = 'rawmark_post_template_types';

	/**
	 * @return string[]
	 */
	public static function get(): array {
		$stored = get_option( self::OPTION_KEY, null );

		if ( ! is_array( $stored ) ) {
			return self::default_types();
		}

		return array_values( array_intersect( $stored, self::selectable_types() ) );
	}

	/**
	 * @param string[] $types
	 */
	public static function set( array $types ): void {
		update_option( self::OPTION_KEY, array_values( array_intersect( $types, self::selectable_types() ) ) );
	}

	public static function is_eligible( string $post_type ): bool {
		return in_array( $post_type, self::get(), true );
	}

	/**
	 * Every post type a site owner could reasonably pick from for the
	 * settings screen: public, not Rawmark's own internal Snippet type
	 * (never public anyway, excluded defensively), not attachments - media
	 * has no Post Template use case and its own permalink oddities - and
	 * not 'page'. Post Template is specifically the "one shared layout for
	 * a whole type, because flagging every item isn't practical" fallback;
	 * a Page already has its own per-item flag (PageFlag::ELIGIBLE_TYPES)
	 * and code-page.php deliberately never resolves post-data merge tags
	 * for one. Letting 'page' into this list would blur that distinction
	 * for no requested benefit.
	 *
	 * @return string[]
	 */
	public static function selectable_types(): array {
		return array_values(
			array_diff(
				get_post_types( array( 'public' => true ) ),
				array( Snippet::SLUG, 'attachment', 'page' )
			)
		);
	}

	/**
	 * First-install default, used until a site owner ever saves the
	 * settings screen: 'post' so nothing regresses for an existing site,
	 * plus 'sc_product' the moment SureCart has registered it - same
	 * plugin-detection intent as SettingsScreen's WooCommerce check,
	 * just against the post type directly rather than a plugin class,
	 * since that's the thing this feature actually depends on.
	 *
	 * @return string[]
	 */
	private static function default_types(): array {
		$defaults = array( 'post' );

		if ( post_type_exists( 'sc_product' ) ) {
			$defaults[] = 'sc_product';
		}

		return array_values( array_intersect( $defaults, self::selectable_types() ) );
	}
}
