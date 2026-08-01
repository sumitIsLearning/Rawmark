<?php
use Rawmark\PostType\Snippet;
use Rawmark\Storage\PostTemplate;

class Test_Post_Template extends WP_UnitTestCase {

	public function test_nothing_set_by_default(): void {
		$this->assertSame( 0, PostTemplate::get_id() );
		$this->assertFalse( PostTemplate::is_set() );
	}

	public function test_set_then_get_id(): void {
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );

		PostTemplate::set( $id );

		$this->assertSame( $id, PostTemplate::get_id() );
		$this->assertTrue( PostTemplate::is_set() );
	}

	public function test_setting_a_new_one_replaces_the_old(): void {
		$first  = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );
		$second = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );
		PostTemplate::set( $first );

		PostTemplate::set( $second );

		$this->assertSame( $second, PostTemplate::get_id() );
	}

	public function test_clear_unsets_it(): void {
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );
		PostTemplate::set( $id );

		PostTemplate::clear();

		$this->assertSame( 0, PostTemplate::get_id() );
		$this->assertFalse( PostTemplate::is_set() );
	}

	// The fail-safe from the design doc: a stored ID that no longer points
	// at a real Snippet must count as unset, not crash or serve garbage.
	public function test_is_set_is_false_for_a_deleted_snippet(): void {
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );
		PostTemplate::set( $id );
		wp_delete_post( $id, true );

		$this->assertFalse( PostTemplate::is_set() );
	}

	public function test_is_set_is_false_for_an_id_pointing_at_a_non_snippet(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		PostTemplate::set( $id );

		$this->assertFalse( PostTemplate::is_set() );
	}
}
