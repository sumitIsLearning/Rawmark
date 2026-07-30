<?php
/**
 * Wires Hookable services together on plugins_loaded.
 *
 * @package Rawmark
 */

namespace Rawmark;

use Rawmark\Admin\Assets;
use Rawmark\Admin\EditorScreen;
use Rawmark\Frontend\Router;
use Rawmark\PostType\CodePage;
use Rawmark\Rest\PagesController;
use Rawmark\Rest\Routes;
use Rawmark\Security\Capabilities;
use Rawmark\Support\Hookable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	public static function boot(): void {
		$editor_screen = new EditorScreen();

		/** @var Hookable[] $services */
		$services = array(
			new Capabilities(),
			new CodePage(),
			new Routes( new PagesController() ),
			new Router(),
			$editor_screen,
			new Assets( $editor_screen ),
		);

		foreach ( $services as $service ) {
			$service->register();
		}
	}

	/**
	 * Runs on activation. The init hook has already fired for this request
	 * by the time register_activation_hook's callback runs, so the post
	 * type has to be registered by hand before flushing rewrite rules.
	 */
	public static function activate(): void {
		Capabilities::activate();
		( new CodePage() )->register_post_type();
		flush_rewrite_rules();
	}
}
