<?php
/**
 * Registers the rawmark/v1 REST namespace.
 *
 * @package Rawmark
 */

namespace Rawmark\Rest;

use Rawmark\Support\Hookable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Routes implements Hookable {

	public const REST_NAMESPACE = 'rawmark/v1';

	private PagesController $pages_controller;

	public function __construct( PagesController $pages_controller ) {
		$this->pages_controller = $pages_controller;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/pages/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this->pages_controller, 'get_item' ),
					'permission_callback' => array( $this->pages_controller, 'check_permission' ),
					'args'                => array(
						'id' => array(
							'validate_callback' => static function ( $value ): bool {
								return is_numeric( $value ) && (int) $value > 0;
							},
						),
					),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this->pages_controller, 'update_item' ),
					'permission_callback' => array( $this->pages_controller, 'check_permission' ),
					'args'                => $this->update_args(),
				),
			)
		);
	}

	/**
	 * @return array<string, array<string, callable>>
	 */
	private function update_args(): array {
		return array(
			'id'     => array(
				'validate_callback' => static function ( $value ): bool {
					return is_numeric( $value ) && (int) $value > 0;
				},
			),
			'title'  => array(
				'sanitize_callback' => 'sanitize_text_field',
			),
			'status' => array(
				'validate_callback' => static function ( $value ): bool {
					return in_array( $value, array( 'draft', 'publish', 'private', 'pending' ), true );
				},
			),
			'html'   => array(
				'validate_callback' => static function ( $value ): bool {
					return is_string( $value );
				},
			),
			'css'    => array(
				'validate_callback' => static function ( $value ): bool {
					return is_string( $value );
				},
			),
			'js'     => array(
				'validate_callback' => static function ( $value ): bool {
					return is_string( $value );
				},
			),
		);
	}
}
