<?php
use Rawmark\Storage\PageFlag;

class Test_Page_Flag extends WP_UnitTestCase {

	public function test_a_plain_page_is_not_enabled(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->assertFalse( PageFlag::is_enabled( $id ) );
	}

	public function test_enable_then_is_enabled(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		PageFlag::enable( $id );
		$this->assertTrue( PageFlag::is_enabled( $id ) );
	}

	public function test_disable_clears_it(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		PageFlag::enable( $id );
		PageFlag::disable( $id );
		$this->assertFalse( PageFlag::is_enabled( $id ) );
	}

	// A flag on a post type that isn't eligible must never count. Without this
	// guard, a stray meta row on any post could make the renderer hijack it.
	public function test_flag_on_an_ineligible_post_type_is_ignored(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'attachment' ) );
		update_post_meta( $id, PageFlag::META_KEY, '1' );
		$this->assertFalse( PageFlag::is_enabled( $id ) );
	}

	public function test_a_flagged_post_is_enabled(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'post' ) );
		PageFlag::enable( $id );
		$this->assertTrue( PageFlag::is_enabled( $id ) );
	}
}
