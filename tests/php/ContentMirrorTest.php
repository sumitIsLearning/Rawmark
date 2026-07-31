<?php
use Rawmark\Storage\ContentMirror;
use Rawmark\Storage\PageFlag;

class Test_Content_Mirror extends WP_UnitTestCase {

	private function flagged_page_with_mirror( string $html, string $status = 'draft' ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => $status,
			)
		);
		PageFlag::enable( $id );
		ContentMirror::write( $id, $html );

		return $id;
	}

	public function test_mirror_keeps_safe_markup(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		ContentMirror::write( $id, '<h1>Hello</h1><p>World</p>' );

		wp_cache_flush();
		$content = get_post( $id )->post_content;
		$this->assertStringContainsString( '<h1>Hello</h1>', $content );
		$this->assertStringContainsString( '<p>World</p>', $content );
	}

	// wp_kses_post() strips script and style. That is the point: the mirror is
	// what a theme renders when Rawmark is switched off, and it must not be a
	// second, unguarded execution path for the JS pane.
	public function test_mirror_strips_scripts(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		ContentMirror::write( $id, '<p>ok</p><script>alert(1)</script>' );

		wp_cache_flush();
		$content = get_post( $id )->post_content;
		$this->assertStringNotContainsString( '<script', $content );
		$this->assertStringContainsString( '<p>ok</p>', $content );
	}

	// The regression this guard exists for. wp-admin/post.php enqueues the
	// autosave script before edit_form_after_title fires, so removing
	// `editor` support happens too late to stop it. Autosave submits
	// `content: $('#content').val() || ''` - an empty string, not an omitted
	// key - and _wp_translate_postdata() treats isset('') as present, so
	// wp_autosave() reaches edit_post() with post_content => ''. On a draft
	// that blanked the mirror about fifteen seconds after the author touched
	// the title. This is that exact write shape.
	public function test_an_autosave_shaped_write_does_not_blank_the_mirror(): void {
		$id = $this->flagged_page_with_mirror( '<h1>Hello</h1><p>World</p>' );

		wp_update_post( array( 'ID' => $id, 'post_content' => '' ) );

		// Re-read from the database, never from a return value.
		wp_cache_flush();
		$content = get_post( $id )->post_content;
		$this->assertStringContainsString( '<h1>Hello</h1>', $content );
		$this->assertStringContainsString( '<p>World</p>', $content );
	}

	// Same guard, the other write path the design spec had accepted as a
	// residual gap: core's own /wp/v2/pages/{id} handler ends in
	// wp_update_post() with whatever content the caller sent.
	public function test_a_third_party_write_cannot_replace_the_mirror(): void {
		$id = $this->flagged_page_with_mirror( '<h1>Hello</h1>' );

		wp_update_post( array( 'ID' => $id, 'post_content' => '<p>clobbered</p>' ) );

		wp_cache_flush();
		$content = get_post( $id )->post_content;
		$this->assertStringContainsString( '<h1>Hello</h1>', $content );
		$this->assertStringNotContainsString( 'clobbered', $content );
	}

	// The guard must not turn the mirror read-only - ContentMirror::write()
	// is the one writer that has to get through.
	public function test_content_mirror_write_still_updates_a_flagged_page(): void {
		$id = $this->flagged_page_with_mirror( '<p>first</p>' );

		ContentMirror::write( $id, '<p>second</p>' );

		wp_cache_flush();
		$content = get_post( $id )->post_content;
		$this->assertStringContainsString( '<p>second</p>', $content );
		$this->assertStringNotContainsString( '<p>first</p>', $content );
	}

	// Blast radius: the guard keys off PageFlag, so every ordinary Page on
	// the site must stay completely writable.
	public function test_an_unflagged_page_content_is_untouched_by_the_guard(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		wp_update_post( array( 'ID' => $id, 'post_content' => '<p>theme content</p>' ) );

		wp_cache_flush();
		$this->assertSame( '<p>theme content</p>', get_post( $id )->post_content );
	}

	// Backslashes in the mirror survive a guarded save. The filter runs on
	// slashed data and core unslashes straight after it, so handing back a
	// raw WP_Post property would strip one level of escaping on every
	// unrelated save until the content decayed.
	public function test_the_guard_does_not_eat_backslashes(): void {
		$id = $this->flagged_page_with_mirror( '<p>a \\ b \\\\ c</p>' );

		wp_cache_flush();
		$before = get_post( $id )->post_content;

		wp_update_post( array( 'ID' => $id, 'post_content' => '' ) );
		wp_update_post( array( 'ID' => $id, 'post_content' => '' ) );

		wp_cache_flush();
		$this->assertSame( $before, get_post( $id )->post_content );
	}
}
