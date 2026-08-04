<?php
use Rawmark\PostType\Snippet;
use Rawmark\Storage\HeaderTemplate;

class Test_Header_Template extends WP_UnitTestCase {

	public function test_nothing_set_by_default(): void {
		$this->assertSame( 0, HeaderTemplate::get_id() );
		$this->assertFalse( HeaderTemplate::is_set() );
	}

	public function test_set_then_get_id(): void {
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );

		HeaderTemplate::set( $id );

		$this->assertSame( $id, HeaderTemplate::get_id() );
		$this->assertTrue( HeaderTemplate::is_set() );
	}

	public function test_clear_unsets_it(): void {
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );
		HeaderTemplate::set( $id );

		HeaderTemplate::clear();

		$this->assertSame( 0, HeaderTemplate::get_id() );
		$this->assertFalse( HeaderTemplate::is_set() );
	}

	public function test_is_set_is_false_for_a_deleted_snippet(): void {
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );
		HeaderTemplate::set( $id );
		wp_delete_post( $id, true );

		$this->assertFalse( HeaderTemplate::is_set() );
	}
}
