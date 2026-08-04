<?php
use Rawmark\PostType\Snippet;
use Rawmark\Storage\FooterTemplate;

class Test_Footer_Template extends WP_UnitTestCase {

	public function test_nothing_set_by_default(): void {
		$this->assertSame( 0, FooterTemplate::get_id() );
		$this->assertFalse( FooterTemplate::is_set() );
	}

	public function test_set_then_get_id(): void {
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );

		FooterTemplate::set( $id );

		$this->assertSame( $id, FooterTemplate::get_id() );
		$this->assertTrue( FooterTemplate::is_set() );
	}

	public function test_clear_unsets_it(): void {
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );
		FooterTemplate::set( $id );

		FooterTemplate::clear();

		$this->assertSame( 0, FooterTemplate::get_id() );
		$this->assertFalse( FooterTemplate::is_set() );
	}

	public function test_is_set_is_false_for_a_deleted_snippet(): void {
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );
		FooterTemplate::set( $id );
		wp_delete_post( $id, true );

		$this->assertFalse( FooterTemplate::is_set() );
	}

	// Header and Footer Template are stored under different option keys -
	// pinning this so a future edit can't accidentally collapse them onto
	// the same option.
	public function test_header_and_footer_templates_are_independent(): void {
		$header = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );
		$footer = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );

		\Rawmark\Storage\HeaderTemplate::set( $header );
		FooterTemplate::set( $footer );

		$this->assertSame( $header, \Rawmark\Storage\HeaderTemplate::get_id() );
		$this->assertSame( $footer, FooterTemplate::get_id() );
	}
}
