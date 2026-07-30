<?php
/**
 * Removes the rawmark_edit_code capability. Deliberately does NOT delete
 * Code Pages - data loss on accidental uninstall is worse than a few
 * orphaned posts. No plugin options exist yet in this build.
 *
 * @package Rawmark
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// The main plugin file - and therefore the autoloader - is never loaded in
// the uninstall context, so every dependency is required by hand. Capabilities
// implements Hookable, and PHP resolves that interface at class-declaration
// time, so it has to come first or this file fatals and the capability is
// never actually removed.
require_once __DIR__ . '/src/Support/Hookable.php';
require_once __DIR__ . '/src/Security/Capabilities.php';

Rawmark\Security\Capabilities::uninstall();
