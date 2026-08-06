<?php
use Rawmark\Admin\SettingsScreen;
use Rawmark\Storage\ShopRedirect;

/**
 * WooCommerce is not installed in this test environment. A class cannot be
 * declared conditionally from inside a test method (PHP disallows nesting a
 * class inside another class's body), so this plain function - itself
 * outside any class - does it instead.
 */
function rawmark_test_define_woocommerce_stub(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		class WooCommerce {}
	}
}

class Test_Settings_Screen extends WP_UnitTestCase {

	public function tear_down(): void {
		ShopRedirect::clear();
		parent::tear_down();
	}

	public function test_render_dies_for_a_user_without_the_capability(): void {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );

		$this->expectException( 'WPDieException' );
		( new SettingsScreen() )->render();
	}

	// WooCommerce is not installed in this test environment, so
	// class_exists( 'WooCommerce' ) is genuinely false here - this is the
	// real "WooCommerce inactive" case, not a stub.
	public function test_render_hides_the_woocommerce_section_when_woocommerce_is_not_active(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		ob_start();
		( new SettingsScreen() )->render();
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'Shop redirect', $html );
	}

	public function test_render_shows_the_woocommerce_section_when_woocommerce_is_active(): void {
		// Defined only once this test runs - after the "not active" test
		// above, which depends on class_exists( 'WooCommerce' ) still being
		// genuinely false. PHPUnit runs test methods in declaration order
		// by default.
		rawmark_test_define_woocommerce_stub();

		// wp_dropdown_pages() renders no <select> at all when zero Pages
		// exist site-wide - the element itself is inside `if ( ! empty(
		// $pages ) )` in core. At least one real Page must exist for the
		// picker to render.
		self::factory()->post->create( array( 'post_type' => 'page' ) );

		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		ob_start();
		( new SettingsScreen() )->render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Shop redirect', $html );
		$this->assertStringContainsString( 'rawmark_shop_redirect_page_id', $html );
		$this->assertStringContainsString( SettingsScreen::ACTION_SAVE_SHOP_REDIRECT, $html );
		$this->assertStringContainsString( '_wpnonce', $html );
	}

	public function test_save_shop_redirect_stores_a_valid_page(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		SettingsScreen::save_shop_redirect( $id );

		$this->assertSame( $id, ShopRedirect::get_page_id() );
	}

	public function test_save_shop_redirect_zero_clears_it(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		ShopRedirect::set( $id );

		SettingsScreen::save_shop_redirect( 0 );

		$this->assertSame( 0, ShopRedirect::get_page_id() );
	}

	// Fail-safe against a tampered request naming a post that isn't a Page.
	public function test_save_shop_redirect_ignores_a_non_page_id(): void {
		$post_id = self::factory()->post->create( array( 'post_type' => 'post' ) );

		SettingsScreen::save_shop_redirect( $post_id );

		$this->assertSame( 0, ShopRedirect::get_page_id() );
	}

	public function test_a_user_without_the_capability_cannot_save_the_shop_redirect(): void {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );
		( new SettingsScreen() )->register();

		$_REQUEST['_wpnonce'] = wp_create_nonce( SettingsScreen::ACTION_SAVE_SHOP_REDIRECT );

		$this->expectException( 'WPDieException' );
		do_action( 'admin_post_' . SettingsScreen::ACTION_SAVE_SHOP_REDIRECT );
	}
}
