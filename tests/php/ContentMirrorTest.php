<?php
use Rawmark\Storage\ContentMirror;

class Test_Content_Mirror extends WP_UnitTestCase {

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
}
