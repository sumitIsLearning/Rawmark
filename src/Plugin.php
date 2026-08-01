<?php
/**
 * Wires Hookable services together on plugins_loaded.
 *
 * @package Rawmark
 */

namespace Rawmark;

use Rawmark\Admin\Assets;
use Rawmark\Admin\EditorLock;
use Rawmark\Admin\EditorScreen;
use Rawmark\Admin\PageListIntegration;
use Rawmark\Admin\PageModeToggle;
use Rawmark\Frontend\Router;
use Rawmark\Migration\Migrator;
use Rawmark\PostType\Snippet;
use Rawmark\Rest\PagesController;
use Rawmark\Rest\Routes;
use Rawmark\Rest\SnippetsController;
use Rawmark\Security\Capabilities;
use Rawmark\Storage\ContentMirror;
use Rawmark\Support\Hookable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	public static function boot(): void {
		// Deferred to init rather than run inline here: at plugins_loaded
		// no plugin's post types, taxonomies, statuses or save-time filters
		// are registered yet, and kses_init_filters() has not run. See
		// Migrator::run_if_needed().
		add_action( 'init', array( Migrator::class, 'run_if_needed' ) );

		$editor_screen = new EditorScreen();

		/** @var Hookable[] $services */
		$services = array(
			new Capabilities(),
			new Routes( new PagesController(), new SnippetsController() ),
			new Router(),
			$editor_screen,
			new Assets( $editor_screen ),
			new PageModeToggle(),
			new EditorLock(),
			new PageListIntegration(),
			new ContentMirror(),
			new Snippet(),
		);

		foreach ( $services as $service ) {
			$service->register();
		}
	}

	public static function activate(): void {
		Capabilities::activate();
		flush_rewrite_rules();
	}
}
