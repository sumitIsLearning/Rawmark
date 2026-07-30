<?php
/**
 * Save lifecycle through the REST layer, using the exact payload shapes
 * assets/src/editor/api-client.js sends.
 *
 * Written after a save-breaking regression that a hand-rolled, simpler
 * payload did not reproduce: the client echoed the current status back on
 * every save, and a brand-new page's status is `auto-draft`, which the
 * route deliberately refuses. Every save on a new page failed with
 * "Invalid parameter(s): status" while every test passed.
 *
 * @package Rawmark
 */

use Rawmark\PostType\CodePage;

class Test_Save_Lifecycle extends WP_UnitTestCase {

	private int $admin_id;

	public function set_up(): void {
		parent::set_up();
		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );
	}

	/** Mirrors api-client.js savePage(). */
	private function client_save( int $id, array $payload ): WP_REST_Response {
		$request = new WP_REST_Request( 'PUT', '/rawmark/v1/pages/' . $id );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $payload ) );

		return rest_get_server()->dispatch( $request );
	}

	private function new_auto_draft(): int {
		return self::factory()->post->create(
			array(
				'post_type'   => CodePage::SLUG,
				'post_status' => 'auto-draft',
				'post_title'  => 'Auto Draft',
			)
		);
	}

	public function test_saving_a_new_page_omits_status_and_succeeds(): void {
		$id = $this->new_auto_draft();

		$response = $this->client_save(
			$id,
			array( 'title' => 'My page', 'html' => '<p>hi</p>', 'css' => '', 'js' => '' )
		);

		$this->assertSame( 200, $response->get_status() );
	}

	public function test_first_save_promotes_auto_draft_to_draft(): void {
		$id = $this->new_auto_draft();

		$this->client_save( $id, array( 'title' => 'My page', 'html' => '<p>hi</p>' ) );

		// Auto-drafts are deleted by wp_delete_auto_drafts() after 7 days,
		// so staying an auto-draft after an explicit save loses the work.
		$this->assertSame( 'draft', get_post_status( $id ) );
	}

	public function test_placeholder_title_is_not_surfaced_to_the_editor(): void {
		$id = $this->new_auto_draft();

		$request  = new WP_REST_Request( 'GET', '/rawmark/v1/pages/' . $id );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( '', $response->get_data()['title'] );
	}

	public function test_saving_a_published_page_does_not_unpublish_it(): void {
		$id = self::factory()->post->create(
			array(
				'post_type'   => CodePage::SLUG,
				'post_status' => 'publish',
			)
		);

		$this->client_save( $id, array( 'title' => 'My page', 'html' => '<p>edited</p>' ) );

		$this->assertSame( 'publish', get_post_status( $id ) );
	}

	public function test_auto_draft_is_refused_as_an_input_status(): void {
		$id = $this->new_auto_draft();

		$response = $this->client_save( $id, array( 'status' => 'auto-draft', 'html' => '<p>x</p>' ) );

		// Accepting it would leave the page garbage-collectable.
		$this->assertSame( 400, $response->get_status() );
	}
}
