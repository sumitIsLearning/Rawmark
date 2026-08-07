<?php
use Rawmark\Storage\PostTemplate;
use Rawmark\Storage\PostTemplateTypes;
use Rawmark\Storage\Source;

class Test_Preview_Controller extends WP_UnitTestCase {

	private function admin(): int {
		$id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $id );
		return $id;
	}

	public function tear_down(): void {
		delete_option( PostTemplateTypes::OPTION_KEY );
		parent::tear_down();
	}

	private function dispatch_preview( array $params ) {
		$request = new WP_REST_Request( 'POST', '/rawmark/v1/preview' );
		$request->set_body_params( $params );
		return rest_get_server()->dispatch( $request );
	}

	public function test_expands_a_shortcode_against_the_posted_html(): void {
		$this->admin();
		add_shortcode( 'rawmark_test_shortcode', function () { return '<span>expanded</span>'; } );
		$id = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) );

		$response = $this->dispatch_preview( array(
			'postId' => $id,
			'html'   => 'before [rawmark_test_shortcode] after',
			'css'    => '',
			'js'     => '',
		) );

		remove_shortcode( 'rawmark_test_shortcode' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertStringContainsString( '<span>expanded</span>', $response->get_data()['srcdoc'] );
		$this->assertStringNotContainsString( '[rawmark_test_shortcode]', $response->get_data()['srcdoc'] );
	}

	public function test_resolves_merge_tags_against_preview_post_id_not_post_id(): void {
		$this->admin();
		PostTemplateTypes::set( array( 'post' ) );
		$snippet = self::factory()->post->create( array( 'post_type' => 'rawmark_snippet' ) );
		$real_post = self::factory()->post->create( array(
			'post_type'   => 'post',
			'post_status' => 'publish',
			'post_title'  => 'The Real Post',
		) );

		$response = $this->dispatch_preview( array(
			'postId'        => $snippet,
			'previewPostId' => $real_post,
			'html'          => '<!-- rawmark:post_title -->',
			'css'           => '',
			'js'            => '',
		) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertStringContainsString( 'The Real Post', $response->get_data()['srcdoc'] );
	}

	public function test_does_not_resolve_merge_tags_for_a_non_eligible_post_type(): void {
		$this->admin();
		PostTemplateTypes::set( array() );
		$id = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) );

		$response = $this->dispatch_preview( array(
			'postId' => $id,
			'html'   => '<!-- rawmark:post_title -->',
			'css'    => '',
			'js'     => '',
		) );

		$this->assertStringContainsString( '<!-- rawmark:post_title -->', $response->get_data()['srcdoc'] );
	}

	// WP core's map_meta_cap() returns do_not_allow for edit_post against a
	// post ID that doesn't exist (wp-includes/capabilities.php's `if ( !
	// $post ) { $caps[] = 'do_not_allow'; }` guard) - even for an
	// administrator. check_permission() runs before render_preview() on
	// every real dispatch, so a nonexistent postId is rejected there,
	// as 403, before render_preview()'s own not-found check ever runs.
	// That inner check stays as defense-in-depth for any future internal
	// caller that bypasses the REST permission gate, but the public route
	// can never actually reach it - assert the real, reachable behavior.
	public function test_a_nonexistent_post_id_is_forbidden_not_found(): void {
		$this->admin();

		$response = $this->dispatch_preview( array(
			'postId' => 999999,
			'html'   => '<p>x</p>',
			'css'    => '',
			'js'     => '',
		) );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_requires_the_capability(): void {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );
		$id = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) );

		$response = $this->dispatch_preview( array( 'postId' => $id, 'html' => '<p>x</p>', 'css' => '', 'js' => '' ) );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_requires_edit_access_to_the_resolved_post(): void {
		$reader = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		get_userdata( $reader )->add_cap( 'read_private_pages' );
		wp_set_current_user( $reader );
		$id = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'private' ) );

		$response = $this->dispatch_preview( array( 'postId' => $id, 'html' => '<p>x</p>', 'css' => '', 'js' => '' ) );

		$this->assertSame( 403, $response->get_status() );
	}
}
