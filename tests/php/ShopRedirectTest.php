<?php
use Rawmark\Storage\ShopRedirect;

class Test_Shop_Redirect extends WP_UnitTestCase {

	public function test_nothing_set_by_default(): void {
		$this->assertSame( 0, ShopRedirect::get_page_id() );
		$this->assertFalse( ShopRedirect::is_configured() );
	}

	public function test_set_then_get_page_id(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		ShopRedirect::set( $id );

		$this->assertSame( $id, ShopRedirect::get_page_id() );
		$this->assertTrue( ShopRedirect::is_configured() );
	}

	public function test_setting_a_new_one_replaces_the_old(): void {
		$first  = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$second = self::factory()->post->create( array( 'post_type' => 'page' ) );
		ShopRedirect::set( $first );

		ShopRedirect::set( $second );

		$this->assertSame( $second, ShopRedirect::get_page_id() );
	}

	public function test_clear_unsets_it(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		ShopRedirect::set( $id );

		ShopRedirect::clear();

		$this->assertSame( 0, ShopRedirect::get_page_id() );
		$this->assertFalse( ShopRedirect::is_configured() );
	}

	// The fail-safe: a stored ID that no longer points at a real Page must
	// count as unconfigured, not crash or redirect to a dead permalink.
	public function test_is_configured_is_false_for_a_deleted_page(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		ShopRedirect::set( $id );
		wp_delete_post( $id, true );

		$this->assertFalse( ShopRedirect::is_configured() );
	}

	public function test_is_configured_is_false_for_an_id_pointing_at_a_non_page(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'post' ) );
		ShopRedirect::set( $id );

		$this->assertFalse( ShopRedirect::is_configured() );
	}
}
