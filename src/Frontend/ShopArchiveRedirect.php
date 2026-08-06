<?php
/**
 * 301-redirects the default WooCommerce Shop archive to the site owner's
 * chosen replacement Page.
 *
 * @package Rawmark
 */

namespace Rawmark\Frontend;

use Rawmark\Storage\ShopRedirect;
use Rawmark\Support\Hookable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ShopArchiveRedirect implements Hookable {

	public function register(): void {
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ) );
	}

	public function maybe_redirect(): void {
		$target = self::target_url();

		if ( null === $target ) {
			return;
		}

		wp_safe_redirect( $target, 301 );
		exit;
	}

	/**
	 * The actual decision, kept exit-free and separately testable - same
	 * reasoning as SnippetActions::unlink_and_bake(): maybe_redirect() ends
	 * in wp_safe_redirect() + a bare exit, which a test process can't
	 * survive.
	 */
	public static function target_url(): ?string {
		if ( ! function_exists( 'is_shop' ) || ! is_shop() ) {
			return null;
		}

		if ( ! ShopRedirect::is_configured() ) {
			return null;
		}

		$url = get_permalink( ShopRedirect::get_page_id() );

		return false !== $url ? $url : null;
	}
}
