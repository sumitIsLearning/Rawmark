<?php
use Rawmark\Admin\SnippetsScreen;
use Rawmark\PostType\Snippet;
use Rawmark\Storage\SnippetLink;

class Test_Snippets_Screen extends WP_UnitTestCase {

	public function test_render_lists_every_snippet_by_title(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		self::factory()->post->create( array( 'post_type' => Snippet::SLUG, 'post_title' => 'Main nav' ) );

		ob_start();
		( new SnippetsScreen() )->render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Main nav', $html );
	}

	public function test_render_shows_link_action_for_an_unlinked_snippet(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG, 'post_title' => 'Card' ) );

		ob_start();
		( new SnippetsScreen() )->render();
		$html = ob_get_clean();

		$this->assertStringContainsString( '>Link<', $html );
		$this->assertStringNotContainsString( '>Unlink<', $html );
	}

	public function test_render_shows_unlink_action_for_a_linked_snippet(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG, 'post_title' => 'Footer' ) );
		SnippetLink::link( $id );

		ob_start();
		( new SnippetsScreen() )->render();
		$html = ob_get_clean();

		$this->assertStringContainsString( '>Unlink<', $html );
	}

	public function test_render_dies_for_a_user_without_the_capability(): void {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );

		$this->expectException( 'WPDieException' );
		( new SnippetsScreen() )->render();
	}
}
