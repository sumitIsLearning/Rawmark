<?php
use Rawmark\PostType\Snippet;
use Rawmark\Storage\SnippetLink;

class Test_Snippet_Link extends WP_UnitTestCase {

	public function test_a_new_snippet_is_not_linked(): void {
		( new Snippet() )->register_post_type();
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );

		$this->assertFalse( SnippetLink::is_linked( $id ) );
	}

	public function test_link_then_is_linked(): void {
		( new Snippet() )->register_post_type();
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );

		SnippetLink::link( $id );

		$this->assertTrue( SnippetLink::is_linked( $id ) );
	}

	public function test_unlink_clears_it(): void {
		( new Snippet() )->register_post_type();
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );

		SnippetLink::link( $id );
		SnippetLink::unlink( $id );

		$this->assertFalse( SnippetLink::is_linked( $id ) );
	}

	// Mirrors PageFlag's own post-type guard: a stray meta row on an
	// unrelated post must never be enough to make anything treat it as a
	// linkable snippet.
	public function test_flag_on_a_non_snippet_post_type_is_ignored(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_post_meta( $id, SnippetLink::META_KEY, '1' );

		$this->assertFalse( SnippetLink::is_linked( $id ) );
	}
}
