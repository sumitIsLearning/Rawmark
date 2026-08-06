<?php
use Rawmark\Frontend\ShopArchiveRedirect;
use Rawmark\Storage\ShopRedirect;

/**
 * WooCommerce is not installed in this test environment, so is_shop() does
 * not exist. This stub stands in for it, toggled per test via the global
 * below - the same fail-safe the real function_exists() guard in
 * ShopArchiveRedirect::target_url() is there to handle when WooCommerce is
 * genuinely absent.
 */
function is_shop(): bool {
	return $GLOBALS['rawmark_test_is_shop'] ?? false;
}

class Test_Shop_Archive_Redirect extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		$GLOBALS['rawmark_test_is_shop'] = false;
	}

	public function tear_down(): void {
		unset( $GLOBALS['rawmark_test_is_shop'] );
		ShopRedirect::clear();
		parent::tear_down();
	}

	public function test_no_redirect_when_not_on_the_shop_archive(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		ShopRedirect::set( $id );

		$GLOBALS['rawmark_test_is_shop'] = false;

		$this->assertNull( ShopArchiveRedirect::target_url() );
	}

	public function test_no_redirect_when_on_the_shop_archive_but_nothing_configured(): void {
		$GLOBALS['rawmark_test_is_shop'] = true;

		$this->assertNull( ShopArchiveRedirect::target_url() );
	}

	public function test_redirects_to_the_configured_pages_permalink(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		ShopRedirect::set( $id );
		$GLOBALS['rawmark_test_is_shop'] = true;

		$this->assertSame( get_permalink( $id ), ShopArchiveRedirect::target_url() );
	}

	// The fail-safe from ShopRedirect::is_configured(): a stored ID pointing
	// at a deleted page must not redirect anywhere.
	public function test_no_redirect_when_the_configured_page_was_deleted(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		ShopRedirect::set( $id );
		wp_delete_post( $id, true );
		$GLOBALS['rawmark_test_is_shop'] = true;

		$this->assertNull( ShopArchiveRedirect::target_url() );
	}

	// maybe_redirect() itself is safe to invoke through the real hook only
	// on the no-op path - the redirect path ends in wp_safe_redirect() + a
	// bare exit, which a test process can't survive, same reasoning as
	// SnippetActions::unlink_and_bake(). target_url() above covers that path.
	public function test_the_hooked_method_does_nothing_off_the_shop_archive(): void {
		$GLOBALS['rawmark_test_is_shop'] = false;

		$this->expectNotToPerformAssertions();

		( new ShopArchiveRedirect() )->maybe_redirect();
	}
}
