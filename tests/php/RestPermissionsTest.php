<?php
/**
 * Covers the REST permission-check requirement from SECURITY-AND-STANDARDS.md
 * section 12: every route needs an authorized happy-path test and an
 * unauthorized-rejection test, since a missing/no-op permission_callback is
 * the most common real-world REST vulnerability pattern.
 *
 * @package Rawmark
 */

use Rawmark\PostType\CodePage;

class Test_Rest_Permissions extends WP_UnitTestCase {

	private int $page_id;

	public function set_up(): void {
		parent::set_up();

		$this->page_id = self::factory()->post->create(
			array(
				'post_type'   => CodePage::SLUG,
				'post_status' => 'draft',
			)
		);
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
}
