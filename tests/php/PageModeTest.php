<?php
use Rawmark\Admin\PageModeToggle;
use Rawmark\Security\Capabilities;
use Rawmark\Storage\PageFlag;

class Test_Page_Mode extends WP_UnitTestCase {

	public function test_enable_url_carries_a_nonce(): void {
		$id  = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$url = PageModeToggle::enable_url( $id );

		$this->assertStringContainsString( '_wpnonce=', $url );
		$this->assertStringContainsString( (string) $id, $url );
	}

	public function test_a_user_without_the_capability_cannot_enable(): void {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertFalse( PageModeToggle::user_may_toggle( $id ) );
	}

	public function test_an_admin_may_toggle(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertTrue( PageModeToggle::user_may_toggle( $id ) );
	}

	// Disabling must not destroy the code - re-enabling has to bring it back.
	public function test_disabling_preserves_the_source(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		PageFlag::enable( $id );
		\Rawmark\Storage\Source::save( $id, '<h1>keep me</h1>', '', '', array() );

		PageFlag::disable( $id );

		wp_cache_flush();
		$this->assertSame( '<h1>keep me</h1>', \Rawmark\Storage\Source::get( $id )['html'] );
	}
}
