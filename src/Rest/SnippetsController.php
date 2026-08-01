<?php
/**
 * Handles GET/PUT /rawmark/v1/snippets/{id} and POST /rawmark/v1/snippets.
 *
 * @package Rawmark
 */

namespace Rawmark\Rest;

use Rawmark\PostType\Snippet;
use Rawmark\Security\Capabilities;
use Rawmark\Storage\SnippetLink;
use Rawmark\Storage\Source;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SnippetsController {

	/**
	 * @return true|WP_Error
	 */
	public function check_permission( WP_REST_Request $request ) {
		if ( ! current_user_can( Capabilities::CAP ) ) {
			return new WP_Error(
				'rawmark_forbidden',
				__( 'You do not have permission to do that.', 'rawmark' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$id = (int) $request->get_param( 'id' );

		if ( $id ) {
			if ( Snippet::SLUG !== get_post_type( $id ) ) {
				return new WP_Error(
					'rawmark_not_found',
					__( 'Snippet not found.', 'rawmark' ),
					array( 'status' => 404 )
				);
			}

			return true;
		}

		// The collection route (create): the page being copied from must be
		// one this user can actually read.
		$source_page_id = (int) $request->get_param( 'source_page_id' );

		if ( ! current_user_can( 'read_post', $source_page_id ) ) {
			return new WP_Error(
				'rawmark_forbidden',
				__( 'You do not have permission to do that.', 'rawmark' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * The collection GET's own permission check - deliberately separate from
	 * check_permission() above, which branches on an `id` or a
	 * `source_page_id` param that a plain list request carries neither of.
	 *
	 * @return true|WP_Error
	 */
	public function check_list_permission() {
		if ( ! current_user_can( Capabilities::CAP ) ) {
			return new WP_Error(
				'rawmark_forbidden',
				__( 'You do not have permission to do that.', 'rawmark' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Every snippet, with its linked state - the editor's "Insert Snippet"
	 * picker uses the full list, the header/footer pickers filter this down
	 * to `linked: true` client-side rather than needing a second endpoint.
	 */
	public function list_items(): WP_REST_Response {
		$snippets = get_posts(
			array(
				'post_type'      => Snippet::SLUG,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$data = array_map(
			static function ( $post ) {
				return array(
					'id'     => $post->ID,
					'title'  => $post->post_title,
					'linked' => SnippetLink::is_linked( $post->ID ),
				);
			},
			$snippets
		);

		return new WP_REST_Response( $data, 200 );
	}

	public function get_item( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$post   = get_post( $id );
		$source = Source::get( $id );

		return new WP_REST_Response(
			array(
				'id'    => $id,
				'title' => $post->post_title,
				'html'  => $source['html'],
				'css'   => $source['css'],
				'js'    => $source['js'],
			),
			200
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( WP_REST_Request $request ) {
		$id      = (int) $request->get_param( 'id' );
		$current = Source::get( $id );
		$html    = $request->has_param( 'html' ) ? (string) $request->get_param( 'html' ) : $current['html'];
		$css     = $request->has_param( 'css' ) ? (string) $request->get_param( 'css' ) : $current['css'];
		$js      = $request->has_param( 'js' ) ? (string) $request->get_param( 'js' ) : $current['js'];

		$saved = Source::save( $id, $html, $css, $js, $current['settings'] );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return new WP_REST_Response(
			array(
				'id'   => $id,
				'html' => $saved['html'],
				'css'  => $saved['css'],
				'js'   => $saved['js'],
			),
			200
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( WP_REST_Request $request ) {
		$source_page_id = (int) $request->get_param( 'source_page_id' );
		$name           = (string) $request->get_param( 'name' );
		$source         = Source::get( $source_page_id );

		$snippet_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => Snippet::SLUG,
					'post_title'  => $name,
					'post_status' => 'publish',
				)
			),
			true
		);

		if ( is_wp_error( $snippet_id ) ) {
			return $snippet_id;
		}

		$saved = Source::save( $snippet_id, $source['html'], $source['css'], $source['js'], $source['settings'] );

		if ( is_wp_error( $saved ) ) {
			wp_delete_post( $snippet_id, true );
			return $saved;
		}

		return new WP_REST_Response(
			array(
				'id'   => $snippet_id,
				'html' => $saved['html'],
				'css'  => $saved['css'],
				'js'   => $saved['js'],
			),
			201
		);
	}
}
