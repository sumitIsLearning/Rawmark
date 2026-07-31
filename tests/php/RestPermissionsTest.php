<?php
/**
 * Covers the REST permission-check requirement from SECURITY-AND-STANDARDS.md
 * section 12: every route needs an authorized happy-path test and an
 * unauthorized-rejection test, since a missing/no-op permission_callback is
 * the most common real-world REST vulnerability pattern.
 *
 * @package Rawmark
 */

use Rawmark\Storage\PageFlag;

class Test_Rest_Permissions extends WP_UnitTestCase {

	private int $page_id;

	public function set_up(): void {
		parent::set_up();

		$this->page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
			)
		);
		PageFlag::enable( $this->page_id );
	}

	public function test_unauthenticated_put_is_rejected(): void {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'PUT', '/rawmark/v1/pages/' . $this->page_id );
		$request->set_body_params( array( 'html' => '<p>hi</p>' ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	public function test_authorized_put_is_accepted(): void {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$request = new WP_REST_Request( 'PUT', '/rawmark/v1/pages/' . $this->page_id );
		$request->set_body_params( array( 'html' => '<p>hi</p>' ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '<p>hi</p>', $response->get_data()['html'] );
	}

	public function test_an_unflagged_page_is_not_reachable_through_rawmark_routes(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$response = rest_get_server()->dispatch(
			new WP_REST_Request( 'GET', '/rawmark/v1/pages/' . $id )
		);

		$this->assertSame( 404, $response->get_status() );
	}

	// Design spec section 10's leak check, and the one the spec singles out
	// as genuinely new: the retired custom post type set show_in_rest =>
	// false, but Pages are exposed in core REST by definition. Nothing
	// registers _rawmark_source with register_post_meta() and core protects
	// underscore-prefixed keys, so this should hold - but the spec asks for
	// a real test rather than that assumption, because the day someone
	// registers the meta to make it visible to the block editor is the day
	// the whole source becomes world-readable on a published page.
	public function test_the_source_is_absent_from_the_core_pages_endpoint(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		PageFlag::enable( $id );
		\Rawmark\Storage\Source::save( $id, '<h1>sekrit-html</h1>', '.sekrit-css{}', 'var sekritJs = 1;', array() );

		foreach ( array( '/wp/v2/pages/' . $id, '/wp/v2/pages' ) as $route ) {
			$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', $route ) );

			$this->assertSame( 200, $response->get_status(), $route );

			$body = wp_json_encode( $response->get_data() );

			$this->assertStringNotContainsString( \Rawmark\Storage\Source::META_KEY, $body, $route );
			$this->assertStringNotContainsString( 'sekrit-html', $body, $route );
			$this->assertStringNotContainsString( 'sekrit-css', $body, $route );
			$this->assertStringNotContainsString( 'sekritJs', $body, $route );
		}
	}

	// The same check with no user at all - the realistic exposure, since an
	// anonymous GET of /wp/v2/pages is public for published pages.
	public function test_the_source_is_absent_from_the_core_pages_endpoint_for_anonymous_callers(): void {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		PageFlag::enable( $id );
		\Rawmark\Storage\Source::save( $id, '<h1>sekrit-html</h1>', '', '', array() );

		wp_set_current_user( 0 );

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wp/v2/pages/' . $id ) );
		$body     = wp_json_encode( $response->get_data() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertStringNotContainsString( \Rawmark\Storage\Source::META_KEY, $body );
		$this->assertStringNotContainsString( 'sekrit-html', $body );
	}
}
