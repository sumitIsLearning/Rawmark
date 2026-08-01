<?php
use Rawmark\PostType\Snippet;
use Rawmark\Storage\SnippetLink;
use Rawmark\Storage\Source;

class Test_Snippets_Rest extends WP_UnitTestCase {

	private function admin(): int {
		$id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $id );
		return $id;
	}

	public function test_creating_a_snippet_copies_the_source_pages_current_source(): void {
		$this->admin();
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		Source::save( $page, '<h1>Hi</h1>', '.x{}', 'f();', array() );

		$request = new WP_REST_Request( 'POST', '/rawmark/v1/snippets' );
		$request->set_body_params( array( 'source_page_id' => $page, 'name' => 'Header' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 201, $response->get_status() );
		$id = $response->get_data()['id'];

		wp_cache_flush();
		$stored = Source::get( $id );
		$this->assertSame( '<h1>Hi</h1>', $stored['html'] );
		$this->assertSame( '.x{}', $stored['css'] );
		$this->assertSame( 'f();', $stored['js'] );
		$this->assertSame( Snippet::SLUG, get_post_type( $id ) );
		$this->assertSame( 'Header', get_post( $id )->post_title );
	}

	public function test_creating_a_snippet_from_a_page_you_cannot_read_is_forbidden(): void {
		$this->admin();
		$page = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'private' ) );

		$editor = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $editor );

		$request = new WP_REST_Request( 'POST', '/rawmark/v1/snippets' );
		$request->set_body_params( array( 'source_page_id' => $page, 'name' => 'Nope' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_get_item_returns_current_source(): void {
		$this->admin();
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );
		Source::save( $id, '<p>x</p>', '', '', array() );

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/rawmark/v1/snippets/' . $id ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '<p>x</p>', $response->get_data()['html'] );
	}

	public function test_get_item_404s_for_a_non_snippet_id(): void {
		$this->admin();
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/rawmark/v1/snippets/' . $page ) );

		$this->assertSame( 404, $response->get_status() );
	}

	public function test_update_item_saves_and_re_reads_from_the_database(): void {
		$this->admin();
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );
		Source::save( $id, 'old', '', '', array() );

		$request = new WP_REST_Request( 'PUT', '/rawmark/v1/snippets/' . $id );
		$request->set_body_params( array( 'html' => 'new' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );

		wp_cache_flush();
		$this->assertSame( 'new', Source::get( $id )['html'] );
	}

	public function test_list_items_returns_every_snippet_with_its_linked_state(): void {
		$this->admin();
		$linked = self::factory()->post->create( array( 'post_type' => Snippet::SLUG, 'post_title' => 'Header' ) );
		SnippetLink::link( $linked );
		$unlinked = self::factory()->post->create( array( 'post_type' => Snippet::SLUG, 'post_title' => 'Draft idea' ) );

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/rawmark/v1/snippets' ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );

		$by_id = array();
		foreach ( $data as $row ) {
			$by_id[ $row['id'] ] = $row;
		}

		$this->assertTrue( $by_id[ $linked ]['linked'] );
		$this->assertFalse( $by_id[ $unlinked ]['linked'] );
		$this->assertSame( 'Header', $by_id[ $linked ]['title'] );
	}

	public function test_list_items_is_forbidden_without_the_capability(): void {
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/rawmark/v1/snippets' ) );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_an_unauthenticated_update_is_rejected(): void {
		$id = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );

		$request = new WP_REST_Request( 'PUT', '/rawmark/v1/snippets/' . $id );
		$request->set_body_params( array( 'html' => 'x' ) );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}
}
