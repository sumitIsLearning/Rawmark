<?php
use Rawmark\Storage\SnippetUsage;
use Rawmark\Storage\Source;

class Test_Snippet_Usage extends WP_UnitTestCase {

	public function test_a_page_referencing_a_snippet_as_header_is_found(): void {
		$page_id    = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$snippet_id = self::factory()->post->create( array( 'post_type' => 'rawmark_snippet' ) );
		update_post_meta( $page_id, '_rawmark_header_snippet', $snippet_id );

		$this->assertSame( array( $page_id ), SnippetUsage::find_placements( $snippet_id ) );
	}

	public function test_a_page_referencing_a_snippet_as_footer_is_found(): void {
		$page_id    = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$snippet_id = self::factory()->post->create( array( 'post_type' => 'rawmark_snippet' ) );
		update_post_meta( $page_id, '_rawmark_footer_snippet', $snippet_id );

		$this->assertSame( array( $page_id ), SnippetUsage::find_placements( $snippet_id ) );
	}

	// The load-bearing case: the marker lives inside the JSON-encoded
	// _rawmark_source, written through the real Source::save() path, not
	// hand-constructed - this is what actually proves the LIKE-query needle
	// matches what's really on disk, single-quoted marker and all.
	public function test_a_page_with_an_in_body_marker_is_found(): void {
		$page_id    = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$snippet_id = self::factory()->post->create( array( 'post_type' => 'rawmark_snippet' ) );
		Source::save( $page_id, "<div>before</div>\n<!-- rawmark:snippet id='" . $snippet_id . "' -->\n<div>after</div>", '', '', array() );

		$this->assertSame( array( $page_id ), SnippetUsage::find_placements( $snippet_id ) );
	}

	public function test_an_unrelated_page_is_not_found(): void {
		$page_id    = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$snippet_id = self::factory()->post->create( array( 'post_type' => 'rawmark_snippet' ) );
		Source::save( $page_id, '<div>nothing to do with any snippet</div>', '', '', array() );

		$this->assertSame( array(), SnippetUsage::find_placements( $snippet_id ) );
	}

	// A page referencing a DIFFERENT snippet ID must not false-positive -
	// this is what proves the needle is specific to one ID, not a loose
	// substring match that would catch id='1' when searching for id='11'.
	public function test_a_page_referencing_a_different_snippet_id_is_not_found(): void {
		$page_id     = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$snippet_a   = self::factory()->post->create( array( 'post_type' => 'rawmark_snippet' ) );
		$snippet_b   = self::factory()->post->create( array( 'post_type' => 'rawmark_snippet' ) );
		Source::save( $page_id, "<!-- rawmark:snippet id='" . $snippet_a . "' -->", '', '', array() );

		$this->assertSame( array(), SnippetUsage::find_placements( $snippet_b ) );
	}

	public function test_count_placements_matches_the_number_found(): void {
		$snippet_id = self::factory()->post->create( array( 'post_type' => 'rawmark_snippet' ) );
		$page_a     = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$page_b     = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_post_meta( $page_a, '_rawmark_header_snippet', $snippet_id );
		update_post_meta( $page_b, '_rawmark_footer_snippet', $snippet_id );

		$this->assertSame( 2, SnippetUsage::count_placements( $snippet_id ) );
	}
}
