<?php
use Rawmark\Admin\EditorScreen;
use Rawmark\PostType\Snippet;
use Rawmark\Storage\PageFlag;

class Test_Editor_Screen extends WP_UnitTestCase {

	private function admin(): void {
		$id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $id );
	}

	public function test_renders_for_a_flagged_page(): void {
		$this->admin();
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		PageFlag::enable( $id );
		$_GET['post'] = $id;

		ob_start();
		( new EditorScreen() )->render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'data-object-type="page"', $html );
		$this->assertStringContainsString( 'data-post-id="' . $id . '"', $html );
	}

	public function test_renders_for_a_snippet(): void {
		$this->admin();
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );
		$_GET['post'] = $id;

		ob_start();
		( new EditorScreen() )->render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'data-object-type="snippet"', $html );
	}

	public function test_dies_for_an_unflagged_page(): void {
		$this->admin();
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$_GET['post'] = $id;

		$this->expectException( 'WPDieException' );
		( new EditorScreen() )->render();
	}
}
