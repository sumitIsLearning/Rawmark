<?php
use Rawmark\Frontend\PostLoopTags;

class Test_Post_Loop_Tags extends WP_UnitTestCase {

	private function make_post( string $title, string $excerpt = '' ): int {
		return self::factory()->post->create(
			array(
				'post_title'   => $title,
				'post_excerpt' => $excerpt,
				'post_status'  => 'publish',
			)
		);
	}

	public function test_loop_expands_once_per_matching_post_with_real_data(): void {
		$this->make_post( 'First Post', 'First excerpt' );
		$this->make_post( 'Second Post', 'Second excerpt' );

		$result = PostLoopTags::resolve(
			"<!-- rawmark:post_loop count='5' -->" .
			'<h2><!-- rawmark:post_title --></h2><p><!-- rawmark:post_excerpt --></p>' .
			'<!-- /rawmark:post_loop -->'
		);

		$this->assertStringContainsString( '<h2>First Post</h2><p>First excerpt</p>', $result );
		$this->assertStringContainsString( '<h2>Second Post</h2><p>Second excerpt</p>', $result );
	}

	public function test_loop_with_no_matching_posts_resolves_to_empty(): void {
		$result = PostLoopTags::resolve(
			"<!-- rawmark:post_loop category='does-not-exist' -->" .
			'<h2><!-- rawmark:post_title --></h2>' .
			'<!-- /rawmark:post_loop -->'
		);

		$this->assertSame( '', $result );
	}

	public function test_category_filter_excludes_posts_outside_it(): void {
		$cat_id = wp_create_category( 'News' );
		$in     = $this->make_post( 'In Category' );
		wp_set_post_categories( $in, array( $cat_id ) );
		$this->make_post( 'Outside Category' );

		$result = PostLoopTags::resolve(
			"<!-- rawmark:post_loop category='news' -->" .
			'<h2><!-- rawmark:post_title --></h2>' .
			'<!-- /rawmark:post_loop -->'
		);

		$this->assertStringContainsString( 'In Category', $result );
		$this->assertStringNotContainsString( 'Outside Category', $result );
	}

	public function test_tag_filter_excludes_posts_outside_it(): void {
		$in = $this->make_post( 'Tagged Post' );
		wp_set_post_tags( $in, 'Featured' );
		$this->make_post( 'Untagged Post' );

		$result = PostLoopTags::resolve(
			"<!-- rawmark:post_loop tag='featured' -->" .
			'<h2><!-- rawmark:post_title --></h2>' .
			'<!-- /rawmark:post_loop -->'
		);

		$this->assertStringContainsString( 'Tagged Post', $result );
		$this->assertStringNotContainsString( 'Untagged Post', $result );
	}

	public function test_omitted_count_defaults_to_five(): void {
		for ( $i = 0; $i < 7; $i++ ) {
			$this->make_post( 'Post ' . $i );
		}

		$result = PostLoopTags::resolve(
			'<!-- rawmark:post_loop -->' .
			'<span><!-- rawmark:post_title --></span>' .
			'<!-- /rawmark:post_loop -->'
		);

		$this->assertSame( 5, substr_count( $result, '<span>' ) );
	}

	public function test_count_above_fifty_is_clamped_to_fifty(): void {
		for ( $i = 0; $i < 55; $i++ ) {
			$this->make_post( 'Post ' . $i );
		}

		$result = PostLoopTags::resolve(
			"<!-- rawmark:post_loop count='999' -->" .
			'<span><!-- rawmark:post_title --></span>' .
			'<!-- /rawmark:post_loop -->'
		);

		$this->assertSame( 50, substr_count( $result, '<span>' ) );
	}

	public function test_multiple_independent_loop_blocks_both_resolve(): void {
		$this->make_post( 'Only Post' );

		$result = PostLoopTags::resolve(
			"<!-- rawmark:post_loop count='1' -->A: <!-- rawmark:post_title --><!-- /rawmark:post_loop -->" .
			"<!-- rawmark:post_loop count='1' -->B: <!-- rawmark:post_title --><!-- /rawmark:post_loop -->"
		);

		$this->assertStringContainsString( 'A: Only Post', $result );
		$this->assertStringContainsString( 'B: Only Post', $result );
	}

	public function test_html_with_no_loop_block_is_left_untouched(): void {
		$html = '<h1>Static page, no loop here</h1><!-- rawmark:post_title -->';

		$this->assertSame( $html, PostLoopTags::resolve( $html ) );
	}
}
