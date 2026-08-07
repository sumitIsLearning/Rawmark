<?php
use Rawmark\Admin\SettingsScreen;
use Rawmark\Storage\PostTemplateTypes;
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
		delete_option( PostTemplateTypes::OPTION_KEY );
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

	public function test_render_always_shows_the_post_template_section(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		ob_start();
		( new SettingsScreen() )->render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Post Template', $html );
		$this->assertStringContainsString( 'rawmark_post_template_types[]', $html );
		$this->assertStringContainsString( SettingsScreen::ACTION_SAVE_POST_TEMPLATE_TYPES, $html );
		// 'post' is always a selectable checkbox, whether or not any
		// optional integration is installed.
		$this->assertStringContainsString( 'value="post"', $html );
	}

	public function test_render_warns_about_the_shared_template_only_when_multiple_types_are_checked(): void {
		register_post_type( 'rawmark_test_cpt', array( 'public' => true ) );
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		ob_start();
		( new SettingsScreen() )->render();
		$single_type_html = ob_get_clean();

		PostTemplateTypes::set( array( 'post', 'rawmark_test_cpt' ) );

		ob_start();
		( new SettingsScreen() )->render();
		$multi_type_html = ob_get_clean();

		$this->assertStringNotContainsString( 'shared across every type', $single_type_html );
		$this->assertStringContainsString( 'shared across every type', $multi_type_html );
	}

	public function test_render_lists_sc_product_once_it_is_registered(): void {
		register_post_type( 'sc_product', array( 'public' => true, 'label' => 'Products' ) );

		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		ob_start();
		( new SettingsScreen() )->render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'value="sc_product"', $html );
	}

	public function test_save_post_template_types_stores_a_valid_selection(): void {
		register_post_type( 'rawmark_test_cpt', array( 'public' => true ) );

		SettingsScreen::save_post_template_types( array( 'post', 'rawmark_test_cpt' ) );

		$this->assertSame( array( 'post', 'rawmark_test_cpt' ), PostTemplateTypes::get() );
	}

	// Fail-safe against a tampered request naming something that isn't a
	// real, selectable post type - same shape as the Shop redirect's
	// non-Page guard above.
	public function test_save_post_template_types_ignores_a_type_that_is_not_selectable(): void {
		SettingsScreen::save_post_template_types( array( 'post', 'not_a_real_post_type' ) );

		$this->assertSame( array( 'post' ), PostTemplateTypes::get() );
	}

	public function test_a_user_without_the_capability_cannot_save_post_template_types(): void {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );
		( new SettingsScreen() )->register();

		$_REQUEST['_wpnonce'] = wp_create_nonce( SettingsScreen::ACTION_SAVE_POST_TEMPLATE_TYPES );

		$this->expectException( 'WPDieException' );
		do_action( 'admin_post_' . SettingsScreen::ACTION_SAVE_POST_TEMPLATE_TYPES );
	}
}
