<?php
use Rawmark\Admin\SnippetsScreen;
use Rawmark\PostType\Snippet;
use Rawmark\Storage\FooterTemplate;
use Rawmark\Storage\HeaderTemplate;
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

	// The Snippets screen was the only place a snippet's ID could be found
	// at all - the marker syntax needs it typed by hand and nothing else
	// surfaced it - so it must actually render, not just exist in a link URL.
	public function test_render_shows_the_snippet_id(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG, 'post_title' => 'Main nav' ) );

		ob_start();
		( new SnippetsScreen() )->render();
		$html = ob_get_clean();

		$this->assertStringContainsString( '#' . $id, $html );
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

	public function test_render_shows_set_as_template_action_by_default(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		self::factory()->post->create( array( 'post_type' => Snippet::SLUG, 'post_title' => 'Layout' ) );

		ob_start();
		( new SnippetsScreen() )->render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Set as Post Template', $html );
		$this->assertStringNotContainsString( 'Unset Template', $html );
	}

	public function test_render_shows_unset_action_and_badge_for_the_current_template(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG, 'post_title' => 'Layout' ) );
		\Rawmark\Storage\PostTemplate::set( $id );

		ob_start();
		( new SnippetsScreen() )->render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Unset Template', $html );
		$this->assertStringContainsString( 'dashicons-star-filled', $html );
	}

	public function test_render_shows_set_as_header_and_footer_template_actions_by_default(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		self::factory()->post->create( array( 'post_type' => Snippet::SLUG, 'post_title' => 'Layout' ) );

		ob_start();
		( new SnippetsScreen() )->render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Set as Header Template', $html );
		$this->assertStringContainsString( 'Set as Footer Template', $html );
		$this->assertStringNotContainsString( 'Unset Header Template', $html );
		$this->assertStringNotContainsString( 'Unset Footer Template', $html );
	}

	public function test_render_shows_unset_action_and_badge_for_the_current_header_template(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG, 'post_title' => 'Nav' ) );
		HeaderTemplate::set( $id );

		ob_start();
		( new SnippetsScreen() )->render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Unset Header Template', $html );
		$this->assertStringContainsString( 'dashicons-arrow-up-alt2', $html );
	}

	public function test_render_shows_unset_action_and_badge_for_the_current_footer_template(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG, 'post_title' => 'Bottom' ) );
		FooterTemplate::set( $id );

		ob_start();
		( new SnippetsScreen() )->render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Unset Footer Template', $html );
		$this->assertStringContainsString( 'dashicons-arrow-down-alt2', $html );
	}

	// All three badges are independent - a snippet acting as Post Template
	// and Header Template at once must show both, not just one.
	public function test_a_snippet_can_show_multiple_badges_at_once(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG, 'post_title' => 'Everything' ) );
		\Rawmark\Storage\PostTemplate::set( $id );
		HeaderTemplate::set( $id );
		FooterTemplate::set( $id );

		ob_start();
		( new SnippetsScreen() )->render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'dashicons-star-filled', $html );
		$this->assertStringContainsString( 'dashicons-arrow-up-alt2', $html );
		$this->assertStringContainsString( 'dashicons-arrow-down-alt2', $html );
	}

	public function test_render_dies_for_a_user_without_the_capability(): void {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );

		$this->expectException( 'WPDieException' );
		( new SnippetsScreen() )->render();
	}
}
