<?php
/**
 * Handles GET and PUT /rawmark/v1/pages/{id}.
 *
 * @package Rawmark
 */

namespace Rawmark\Rest;

use Rawmark\Security\Capabilities;
use Rawmark\Storage\ContentMirror;
use Rawmark\Storage\PageFlag;
use Rawmark\Storage\Source;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PagesController {

	/**
	 * Shared permission_callback for both routes. Three gates, in order:
	 * the plugin-wide capability (never __return_true), confirmation that
	 * the id is actually a Code Page rather than any arbitrary post, and
	 * the per-post meta capability for the specific object being touched.
	 *
	 * That last check is redundant while rawmark_edit_code is granted to
	 * administrators only - it stops being redundant the moment a site
	 * grants the capability to a lower role, which the roadmap plans for.
	 * Cheap now, load-bearing later.
	 *
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

		if ( ! PageFlag::is_enabled( $id ) ) {
			return new WP_Error(
				'rawmark_not_found',
				__( 'Rawmark page not found.', 'rawmark' ),
				array( 'status' => 404 )
			);
		}

		$meta_cap = 'GET' === $request->get_method() ? 'read_post' : 'edit_post';

		if ( ! current_user_can( $meta_cap, $id ) ) {
			return new WP_Error(
				'rawmark_forbidden',
				__( 'You do not have permission to do that.', 'rawmark' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	public function get_item( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$post   = get_post( $id );
		$source = Source::get( $id );

		return new WP_REST_Response(
			array(
				'id'       => $id,
				'title'    => $this->display_title( $post ),
				'status'   => $post->post_status,
				'html'     => $source['html'],
				'css'      => $source['css'],
				'js'       => $source['js'],
				'settings' => $source['settings'],
			),
			200
		);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( WP_REST_Request $request ) {
		$id     = (int) $request->get_param( 'id' );
		$post   = get_post( $id );
		$update = array( 'ID' => $id );

		if ( null !== $request->get_param( 'title' ) ) {
			$update['post_title'] = $request->get_param( 'title' );
		}

		if ( null !== $request->get_param( 'status' ) ) {
			$update['post_status'] = $request->get_param( 'status' );
		} elseif ( 'auto-draft' === $post->post_status ) {
			// wp_delete_auto_drafts() removes auto-drafts older than seven
			// days on a daily cron. A page the developer has actually saved
			// must become a real draft or their work silently disappears.
			$update['post_status'] = 'draft';
		}

		// A brand-new page still carries WordPress's placeholder "Auto Draft"
		// title. Promoting it with that title intact would publish a page
		// literally called Auto Draft, so blank it unless the user typed one.
		if ( 'auto-draft' === $post->post_status && ! isset( $update['post_title'] ) ) {
			$update['post_title'] = '';
		}

		if ( count( $update ) > 1 ) {
			$result = wp_update_post( wp_slash( $update ), true );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		$current = Source::get( $id );
		$html    = $request->has_param( 'html' ) ? (string) $request->get_param( 'html' ) : $current['html'];
		$css     = $request->has_param( 'css' ) ? (string) $request->get_param( 'css' ) : $current['css'];
		$js      = $request->has_param( 'js' ) ? (string) $request->get_param( 'js' ) : $current['js'];

		$saved = Source::save( $id, $html, $css, $js, $current['settings'] );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		ContentMirror::write( $id, $saved['html'] );

		$post = get_post( $id );

		return new WP_REST_Response(
			array(
				'id'        => $id,
				'title'     => $this->display_title( $post ),
				'status'    => $post->post_status,
				'permalink' => get_permalink( $post ),
				'html'      => $saved['html'],
				'css'       => $saved['css'],
				'js'        => $saved['js'],
				'settings'  => $saved['settings'],
			),
			200
		);
	}

	/**
	 * WordPress gives every freshly created auto-draft the literal title
	 * "Auto Draft". Surfacing that in the editor field would make the user
	 * delete it by hand, so it reads as empty and the placeholder shows.
	 *
	 * @param \WP_Post $post Post to read the title from.
	 */
	private function display_title( $post ): string {
		return 'auto-draft' === $post->post_status ? '' : $post->post_title;
	}
}
