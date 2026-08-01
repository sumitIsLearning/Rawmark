<?php
use Rawmark\Frontend\PostDataTags;

class Test_Post_Data_Tags extends WP_UnitTestCase {

	public function test_post_title(): void {
		$id = self::factory()->post->create( array( 'post_title' => 'Hello <b>World</b>' ) );

		$this->assertSame(
			'Hello &lt;b&gt;World&lt;/b&gt;',
			PostDataTags::resolve( $id, '<!-- rawmark:post_title -->' )
		);
	}

	// wpautop is part of WordPress's default the_content filter chain - a
	// plain-text post_content getting wrapped in <p> tags is what proves
	// this ran through real rendering, not a raw echo of stored content.
	public function test_post_content_runs_through_the_content_filters(): void {
		$id = self::factory()->post->create( array( 'post_content' => 'plain paragraph' ) );

		$result = PostDataTags::resolve( $id, '<!-- rawmark:post_content -->' );

		$this->assertStringContainsString( '<p>plain paragraph</p>', $result );
	}

	public function test_post_excerpt(): void {
		$id = self::factory()->post->create( array( 'post_excerpt' => 'A summary' ) );

		$this->assertSame( 'A summary', PostDataTags::resolve( $id, '<!-- rawmark:post_excerpt -->' ) );
	}

	public function test_post_date(): void {
		$id = self::factory()->post->create( array( 'post_date' => '2026-01-15 10:00:00' ) );

		$this->assertSame( get_the_date( '', $id ), PostDataTags::resolve( $id, '<!-- rawmark:post_date -->' ) );
	}

	public function test_featured_image_is_empty_when_none_is_set(): void {
		$id = self::factory()->post->create();

		$this->assertSame( '', PostDataTags::resolve( $id, '<!-- rawmark:featured_image -->' ) );
	}

	public function test_permalink(): void {
		$id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$this->assertSame( get_permalink( $id ), PostDataTags::resolve( $id, '<!-- rawmark:permalink -->' ) );
	}

	public function test_author_name(): void {
		$author_id = self::factory()->user->create( array( 'display_name' => 'Ada Lovelace' ) );
		$id        = self::factory()->post->create( array( 'post_author' => $author_id ) );

		$this->assertSame( 'Ada Lovelace', PostDataTags::resolve( $id, '<!-- rawmark:author_name -->' ) );
	}

	public function test_multiple_tags_in_one_string_all_resolve(): void {
		$id = self::factory()->post->create( array( 'post_title' => 'Title', 'post_excerpt' => 'Excerpt' ) );

		$result = PostDataTags::resolve(
			$id,
			'<h1><!-- rawmark:post_title --></h1><p><!-- rawmark:post_excerpt --></p>'
		);

		$this->assertSame( '<h1>Title</h1><p>Excerpt</p>', $result );
	}

	// The regex only matches the seven known names - anything else stays
	// exactly as typed, on purpose (see the design doc).
	public function test_an_unrecognized_tag_is_left_untouched(): void {
		$id = self::factory()->post->create();

		$result = PostDataTags::resolve( $id, '<!-- rawmark:not_a_real_tag -->' );

		$this->assertSame( '<!-- rawmark:not_a_real_tag -->', $result );
	}
}
