<?php
/**
 * Registers and grants the rawmark_edit_code capability.
 *
 * @package Rawmark
 */

namespace Rawmark\Security;

use Rawmark\Support\Hookable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Capabilities implements Hookable {

	public const CAP = 'rawmark_edit_code';

	/**
	 * WordPress secretly registers whatever string a post type maps
	 * edit_post/read_post/delete_post to into a global meta-cap redirect
	 * table (see _post_type_meta_capabilities() in wp-includes/post.php).
	 * If that string is the same one used for a bare, no-object
	 * current_user_can( CAP ) check elsewhere (REST, admin menus, ...),
	 * every one of those bare checks gets silently rerouted through
	 * map_meta_cap() with a missing object id and fails - core logs a
	 * "must always check against a specific post" notice and returns
	 * do_not_allow. These three exist purely so the post type's meta caps
	 * never share a string with the plugin-wide gate.
	 */
	public const META_CAP_EDIT_POST   = 'rawmark_meta_edit_post';
	public const META_CAP_READ_POST   = 'rawmark_meta_read_post';
	public const META_CAP_DELETE_POST = 'rawmark_meta_delete_post';

	private const ALL = array( self::CAP, self::META_CAP_EDIT_POST, self::META_CAP_READ_POST, self::META_CAP_DELETE_POST );

	/**
	 * On multisite, none of these are ever added to a role - adding them
	 * to Administrator would hand every site admin on the network exactly
	 * what core withholds by reserving unfiltered_html for Super Admins.
	 * Instead they're granted dynamically, per request, only to super
	 * admins.
	 */
	public function register(): void {
		if ( is_multisite() ) {
			add_filter( 'user_has_cap', array( $this, 'grant_to_super_admins' ), 10, 3 );
		}
	}

	/**
	 * @param array<string, bool> $allcaps
	 * @param string[]            $caps
	 * @param array<int, mixed>   $args
	 * @return array<string, bool>
	 */
	public function grant_to_super_admins( array $allcaps, array $caps, array $args ): array {
		if ( ! isset( $args[1] ) || ! is_super_admin( (int) $args[1] ) ) {
			return $allcaps;
		}

		foreach ( self::ALL as $cap ) {
			if ( in_array( $cap, $caps, true ) ) {
				$allcaps[ $cap ] = true;
			}
		}

		return $allcaps;
	}

	/**
	 * Runs on plugin activation. Single-site only; multisite grants happen
	 * dynamically via register().
	 */
	public static function activate(): void {
		if ( is_multisite() ) {
			return;
		}

		$role = get_role( 'administrator' );

		if ( $role ) {
			foreach ( self::ALL as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Runs on uninstall. Strips every one of these from every role that
	 * holds it, not just Administrator, in case a future role/capability
	 * settings screen granted it elsewhere.
	 */
	public static function uninstall(): void {
		foreach ( array_keys( wp_roles()->role_names ) as $role_slug ) {
			$role = get_role( $role_slug );

			if ( ! $role ) {
				continue;
			}

			foreach ( self::ALL as $cap ) {
				if ( $role->has_cap( $cap ) ) {
					$role->remove_cap( $cap );
				}
			}
		}
	}
}
