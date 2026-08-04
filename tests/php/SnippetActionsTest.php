<?php
use Rawmark\Admin\SnippetActions;
use Rawmark\PostType\Snippet;
use Rawmark\Storage\SnippetLink;
use Rawmark\Storage\Source;

class Test_Snippet_Actions extends WP_UnitTestCase {

	public function test_link_url_carries_a_nonce(): void {
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );

		$url = SnippetActions::link_url( $id );

		$this->assertStringContainsString( '_wpnonce=', $url );
		$this->assertStringContainsString( (string) $id, $url );
	}

	public function test_set_template_url_carries_a_nonce(): void {
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );

		$url = SnippetActions::set_template_url( $id );

		$this->assertStringContainsString( '_wpnonce=', $url );
		$this->assertStringContainsString( (string) $id, $url );
	}

	public function test_set_header_template_url_carries_a_nonce(): void {
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );

		$url = SnippetActions::set_header_template_url( $id );

		$this->assertStringContainsString( '_wpnonce=', $url );
		$this->assertStringContainsString( (string) $id, $url );
	}

	public function test_set_footer_template_url_carries_a_nonce(): void {
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );

		$url = SnippetActions::set_footer_template_url( $id );

		$this->assertStringContainsString( '_wpnonce=', $url );
		$this->assertStringContainsString( (string) $id, $url );
	}

	// handle_set_header_template()/handle_set_footer_template() end in
	// wp_safe_redirect() + a bare `exit;`, same as handle_set_template()
	// above - per this file's established pattern, the success path is not
	// called through do_action() (it would kill the PHPUnit process) and is
	// instead covered end-to-end by the SnippetsScreen badge tests in Task 7.
	// Only the capability-failure path (which wp_die()s, safely) is
	// exercised directly here.

	public function test_a_user_without_the_capability_cannot_set_the_header_template(): void {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );
		( new SnippetActions() )->register();
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );

		$_GET['snippet']      = $id;
		$_REQUEST['_wpnonce'] = wp_create_nonce( SnippetActions::ACTION_SET_HEADER_TEMPLATE . '_' . $id );

		$this->expectException( 'WPDieException' );
		do_action( 'admin_post_' . SnippetActions::ACTION_SET_HEADER_TEMPLATE );
	}

	public function test_a_user_without_the_capability_cannot_set_the_footer_template(): void {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );
		( new SnippetActions() )->register();
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );

		$_GET['snippet']      = $id;
		$_REQUEST['_wpnonce'] = wp_create_nonce( SnippetActions::ACTION_SET_FOOTER_TEMPLATE . '_' . $id );

		$this->expectException( 'WPDieException' );
		do_action( 'admin_post_' . SnippetActions::ACTION_SET_FOOTER_TEMPLATE );
	}

	public function test_a_user_without_the_capability_cannot_set_the_template(): void {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );
		( new SnippetActions() )->register();
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );

		$_GET['snippet']      = $id;
		$_REQUEST['_wpnonce'] = wp_create_nonce( SnippetActions::ACTION_SET_TEMPLATE . '_' . $id );

		$this->expectException( 'WPDieException' );
		do_action( 'admin_post_' . SnippetActions::ACTION_SET_TEMPLATE );
	}

	// handle_link()/handle_unlink()/handle_delete() end in wp_safe_redirect()
	// then a bare `exit;` - matching the established PageModeToggle pattern
	// from Phase 1. That `exit` is real PHP, not wp_die(): the test suite's
	// WPDieException conversion only covers wp_die(), never a raw exit, so a
	// bare exit inside a test would kill the whole PHPUnit process, not just
	// fail one test. That's why the state-changing logic lives in a separate,
	// exit-free method (unlink_and_bake() below) that tests call directly -
	// never through do_action( 'admin_post_...' ) for a success path. Only
	// the *failure* paths (which wp_die(), safely) are exercised through the
	// real action, in test_a_user_without_the_capability_cannot_link below.

	public function test_unlink_and_bake_replaces_an_in_body_marker_and_clears_the_flag(): void {
		$snippet = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );
		Source::save( $snippet, '<footer>F</footer>', '.f{}', 'f();', array() );
		SnippetLink::link( $snippet );

		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		Source::save( $page, "before<!-- rawmark:snippet id='" . $snippet . "' -->after", '.p{}', 'p();', array() );

		SnippetActions::unlink_and_bake( $snippet );

		$this->assertFalse( SnippetLink::is_linked( $snippet ) );

		wp_cache_flush();
		$baked = Source::get( $page );
		$this->assertSame( 'before<footer>F</footer>after', $baked['html'] );
		$this->assertStringContainsString( '.p{}', $baked['css'] );
		$this->assertStringContainsString( '.f{}', $baked['css'] );
		$this->assertStringContainsString( 'p();', $baked['js'] );
		$this->assertStringContainsString( 'f();', $baked['js'] );
	}

	public function test_unlink_and_bake_clears_a_header_reference_instead_of_baking_it(): void {
		$snippet = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );
		Source::save( $snippet, '<header>H</header>', '', '', array() );
		SnippetLink::link( $snippet );

		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_post_meta( $page, '_rawmark_header_snippet', $snippet );
		Source::save( $page, '<main>M</main>', '', '', array() );

		SnippetActions::unlink_and_bake( $snippet );

		$this->assertSame( '', get_post_meta( $page, '_rawmark_header_snippet', true ) );

		wp_cache_flush();
		// Not baked - "None" is the whole point, per the design doc.
		$this->assertSame( '<main>M</main>', Source::get( $page )['html'] );
	}

	public function test_unlink_and_bake_ignores_a_footer_reference_pointing_at_a_different_snippet(): void {
		$snippet = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );
		$other   = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );
		SnippetLink::link( $snippet );

		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_post_meta( $page, '_rawmark_footer_snippet', $other );

		SnippetActions::unlink_and_bake( $snippet );

		$this->assertSame( $other, (int) get_post_meta( $page, '_rawmark_footer_snippet', true ) );
	}

	public function test_create_snippet_creates_a_published_snippet_with_the_given_name(): void {
		$id = SnippetActions::create_snippet( 'My New Snippet' );

		$this->assertSame( Snippet::SLUG, get_post_type( $id ) );
		$this->assertSame( 'publish', get_post_status( $id ) );
		$this->assertSame( 'My New Snippet', get_post( $id )->post_title );
	}

	public function test_create_snippet_starts_with_blank_content(): void {
		$id = SnippetActions::create_snippet( 'Blank One' );

		$source = Source::get( $id );

		$this->assertSame( '', $source['html'] );
		$this->assertSame( '', $source['css'] );
		$this->assertSame( '', $source['js'] );
	}

	public function test_create_snippet_falls_back_to_a_default_name_when_empty(): void {
		$id = SnippetActions::create_snippet( '' );

		$this->assertSame( 'Untitled Snippet', get_post( $id )->post_title );
	}

	public function test_create_snippet_sanitizes_the_name(): void {
		$id = SnippetActions::create_snippet( '<script>alert(1)</script>My Name' );

		$this->assertSame( 'My Name', get_post( $id )->post_title );
	}

	public function test_a_user_without_the_capability_cannot_create_a_snippet(): void {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );
		( new SnippetActions() )->register();

		$_POST['name']        = 'Should Not Exist';
		$_REQUEST['_wpnonce'] = wp_create_nonce( SnippetActions::ACTION_CREATE );

		$this->expectException( 'WPDieException' );
		do_action( 'admin_post_' . SnippetActions::ACTION_CREATE );
	}

	public function test_a_user_without_the_capability_cannot_link(): void {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );
		( new SnippetActions() )->register();
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );

		$_GET['snippet']      = $id;
		$_REQUEST['_wpnonce'] = wp_create_nonce( SnippetActions::ACTION_LINK . '_' . $id );

		$this->expectException( 'WPDieException' );
		do_action( 'admin_post_' . SnippetActions::ACTION_LINK );
	}
}
