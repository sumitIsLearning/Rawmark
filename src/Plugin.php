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
use Rawmark\Rest\PagesController;
use Rawmark\Rest\Routes;
use Rawmark\Security\Capabilities;
use Rawmark\Support\Hookable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	public static function boot(): void {
		Migrator::run_if_needed();

		$editor_screen = new EditorScreen();

		/** @var Hookable[] $services */
		$services = array(
			new Capabilities(),
			new Routes( new PagesController() ),
			new Router(),
			$editor_screen,
			new Assets( $editor_screen ),
			new PageModeToggle(),
			new EditorLock(),
			new PageListIntegration(),
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
