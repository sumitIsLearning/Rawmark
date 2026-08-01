<?php
/**
 * Removes the rawmark_edit_code capability and the Post Template option.
 * Deliberately does NOT delete Code Pages or Snippets - data loss on
 * accidental uninstall is worse than a few orphaned posts. The option only
 * points at a Snippet; deleting the pointer is not deleting content.
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
require_once __DIR__ . '/src/PostType/Snippet.php';
require_once __DIR__ . '/src/Storage/PostTemplate.php';

Rawmark\Security\Capabilities::uninstall();
Rawmark\Storage\PostTemplate::clear();
