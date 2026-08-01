<?php
use Rawmark\PostType\Snippet;

class Test_Snippet_Post_Type extends WP_UnitTestCase {

	public function test_registers_rawmark_snippet(): void {
		( new Snippet() )->register();
		( new Snippet() )->register_post_type();

		$this->assertTrue( post_type_exists( Snippet::SLUG ) );
	}

	public function test_snippet_post_type_is_not_public(): void {
		( new Snippet() )->register_post_type();

		$object = get_post_type_object( Snippet::SLUG );

		$this->assertFalse( $object->public );
		$this->assertFalse( $object->publicly_queryable );
		$this->assertFalse( $object->show_in_rest );
		$this->assertFalse( $object->show_in_menu );
	}

	public function test_a_snippet_post_can_be_created_and_holds_a_title(): void {
		( new Snippet() )->register_post_type();

		$id = self::factory()->post->create(
			array(
				'post_type'  => Snippet::SLUG,
				'post_title' => 'Main nav',
			)
		);

		$this->assertSame( 'Main nav', get_post( $id )->post_title );
	}
}
