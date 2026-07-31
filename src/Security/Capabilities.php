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

	private const ALL = array( self::CAP );

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
