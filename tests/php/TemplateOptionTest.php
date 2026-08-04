<?php
use Rawmark\PostType\Snippet;
use Rawmark\Storage\TemplateOption;

class Test_Template_Option extends WP_UnitTestCase {

	private const KEY = 'rawmark_test_template_id';

	public function test_nothing_set_by_default(): void {
		$this->assertSame( 0, TemplateOption::get_id( self::KEY ) );
		$this->assertFalse( TemplateOption::is_set( self::KEY ) );
	}

	public function test_set_then_get_id(): void {
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );

		TemplateOption::set( self::KEY, $id );

		$this->assertSame( $id, TemplateOption::get_id( self::KEY ) );
		$this->assertTrue( TemplateOption::is_set( self::KEY ) );
	}

	public function test_setting_a_new_one_replaces_the_old(): void {
		$first  = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );
		$second = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );
		TemplateOption::set( self::KEY, $first );

		TemplateOption::set( self::KEY, $second );

		$this->assertSame( $second, TemplateOption::get_id( self::KEY ) );
	}

	public function test_clear_unsets_it(): void {
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );
		TemplateOption::set( self::KEY, $id );

		TemplateOption::clear( self::KEY );

		$this->assertSame( 0, TemplateOption::get_id( self::KEY ) );
		$this->assertFalse( TemplateOption::is_set( self::KEY ) );
	}

	public function test_is_set_is_false_for_a_deleted_snippet(): void {
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );
		TemplateOption::set( self::KEY, $id );
		wp_delete_post( $id, true );

		$this->assertFalse( TemplateOption::is_set( self::KEY ) );
	}

	public function test_is_set_is_false_for_an_id_pointing_at_a_non_snippet(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		TemplateOption::set( self::KEY, $id );

		$this->assertFalse( TemplateOption::is_set( self::KEY ) );
	}

	public function test_two_option_keys_do_not_interfere(): void {
		$a = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );
		$b = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );

		TemplateOption::set( 'rawmark_test_key_a', $a );
		TemplateOption::set( 'rawmark_test_key_b', $b );

		$this->assertSame( $a, TemplateOption::get_id( 'rawmark_test_key_a' ) );
		$this->assertSame( $b, TemplateOption::get_id( 'rawmark_test_key_b' ) );
	}
}
