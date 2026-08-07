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
	private SnippetsController $snippets_controller;
	private PreviewController $preview_controller;

	public function __construct( PagesController $pages_controller, SnippetsController $snippets_controller, PreviewController $preview_controller ) {
		$this->pages_controller    = $pages_controller;
		$this->snippets_controller = $snippets_controller;
		$this->preview_controller  = $preview_controller;
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
					'args'                => $this->page_update_args(),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/snippets',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this->snippets_controller, 'list_items' ),
					'permission_callback' => array( $this->snippets_controller, 'check_list_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this->snippets_controller, 'create_item' ),
					'permission_callback' => array( $this->snippets_controller, 'check_permission' ),
					'args'                => array(
						'source_page_id' => array(
							'required'          => true,
							'validate_callback' => static function ( $value ): bool {
								return is_numeric( $value ) && (int) $value > 0;
							},
						),
						'name'            => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/snippets/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this->snippets_controller, 'get_item' ),
					'permission_callback' => array( $this->snippets_controller, 'check_permission' ),
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
					'callback'            => array( $this->snippets_controller, 'update_item' ),
					'permission_callback' => array( $this->snippets_controller, 'check_permission' ),
					'args'                => array(
						'id'   => array(
							'validate_callback' => static function ( $value ): bool {
								return is_numeric( $value ) && (int) $value > 0;
							},
						),
						'html' => array(
							'validate_callback' => static function ( $value ): bool {
								return is_string( $value );
							},
						),
						'css'  => array(
							'validate_callback' => static function ( $value ): bool {
								return is_string( $value );
							},
						),
						'js'   => array(
							'validate_callback' => static function ( $value ): bool {
								return is_string( $value );
							},
						),
						'previewPostId' => array(
							'validate_callback' => static function ( $value ): bool {
								return is_numeric( $value ) && (int) $value >= 0;
							},
						),
						'settings' => array(
							'validate_callback' => static function ( $value ): bool {
								return is_array( $value );
							},
						),
					),
				),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this->preview_controller, 'render_preview' ),
				'permission_callback' => array( $this->preview_controller, 'check_permission' ),
				'args'                => array(
					'postId'        => array(
						'required'          => true,
						'validate_callback' => static function ( $value ): bool {
							return is_numeric( $value ) && (int) $value > 0;
						},
					),
					'previewPostId' => array(
						'validate_callback' => static function ( $value ): bool {
							return is_numeric( $value ) && (int) $value >= 0;
						},
					),
					'html'          => array(
						'validate_callback' => static function ( $value ): bool {
							return is_string( $value );
						},
					),
					'css'           => array(
						'validate_callback' => static function ( $value ): bool {
							return is_string( $value );
						},
					),
					'js'            => array(
						'validate_callback' => static function ( $value ): bool {
							return is_string( $value );
						},
					),
				),
			)
		);
	}

	/**
	 * @return array<string, array<string, callable>>
	 */
	private function page_update_args(): array {
		return array(
			'id'              => array(
				'validate_callback' => static function ( $value ): bool {
					return is_numeric( $value ) && (int) $value > 0;
				},
			),
			'title'           => array(
				'sanitize_callback' => 'sanitize_text_field',
			),
			'status'          => array(
				'validate_callback' => static function ( $value ): bool {
					return in_array( $value, array( 'draft', 'publish', 'private', 'pending' ), true );
				},
			),
			'html'            => array(
				'validate_callback' => static function ( $value ): bool {
					return is_string( $value );
				},
			),
			'css'             => array(
				'validate_callback' => static function ( $value ): bool {
					return is_string( $value );
				},
			),
			'js'              => array(
				'validate_callback' => static function ( $value ): bool {
					return is_string( $value );
				},
			),
			'headerSnippetId' => array(
				'validate_callback' => static function ( $value ): bool {
					return is_numeric( $value ) && (int) $value >= 0;
				},
			),
			'footerSnippetId' => array(
				'validate_callback' => static function ( $value ): bool {
					return is_numeric( $value ) && (int) $value >= 0;
				},
			),
			'settings'        => array(
				'validate_callback' => static function ( $value ): bool {
					return is_array( $value );
				},
			),
		);
	}
}
