<?php
/**
 * Reads and writes the _rawmark_source post meta.
 *
 * @package Rawmark
 */

namespace Rawmark\Storage;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Source {

	public const META_KEY = '_rawmark_source';

	private const SOFT_LIMIT_BYTES         = 262144; // 256KB.
	private const HARD_LIMIT_PANE_BYTES    = 1048576; // 1MB.
	private const HARD_LIMIT_COMBINED_BYTES = 2097152; // 2MB.

	/**
	 * @return array{html: string, css: string, js: string, settings: array<string, mixed>}
	 */
	public static function get( int $post_id ): array {
		$raw     = get_post_meta( $post_id, self::META_KEY, true );
		$decoded = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : null;

		if ( ! is_array( $decoded ) ) {
			$decoded = array();
		}

		return array(
			'html'     => isset( $decoded['html'] ) ? (string) $decoded['html'] : '',
			'css'      => isset( $decoded['css'] ) ? (string) $decoded['css'] : '',
			'js'       => isset( $decoded['js'] ) ? (string) $decoded['js'] : '',
			'settings' => self::normalize_settings( $decoded['settings'] ?? array() ),
		);
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array{html: string, css: string, js: string, settings: array<string, mixed>}|WP_Error
	 */
	public static function save( int $post_id, string $html, string $css, string $js, array $settings ) {
		$limit_check = self::check_limits( $html, $css, $js );

		if ( is_wp_error( $limit_check ) ) {
			return $limit_check;
		}

		$payload = array(
			'html'     => $html,
			'css'      => $css,
			'js'       => $js,
			'settings' => self::normalize_settings( $settings ),
		);

		// wp_slash() is mandatory here, not defensive. update_metadata() runs
		// wp_unslash() on every value it stores, which strips the backslashes
		// out of JSON's \" escaping and leaves syntactically invalid JSON on
		// disk - json_decode() then returns null and the page reloads with
		// three empty panes. Pre-slashing means WordPress's unslash restores
		// exactly what wp_json_encode() produced. Any HTML containing a
		// double quote hits this, so it is effectively every real page.
		update_post_meta( $post_id, self::META_KEY, wp_slash( (string) wp_json_encode( $payload ) ) );

		return $payload;
	}

	/**
	 * @return true|WP_Error
	 */
	private static function check_limits( string $html, string $css, string $js ) {
		/**
		 * Filters the server-side size limits enforced on save.
		 *
		 * @param array{soft: int, hard_pane: int, hard_combined: int} $limits
		 */
		$limits = apply_filters(
			'rawmark_size_limits',
			array(
				'soft'          => self::SOFT_LIMIT_BYTES,
				'hard_pane'     => self::HARD_LIMIT_PANE_BYTES,
				'hard_combined' => self::HARD_LIMIT_COMBINED_BYTES,
			)
		);

		$panes = array(
			'html' => $html,
			'css'  => $css,
			'js'   => $js,
		);
		$total = 0;

		foreach ( $panes as $name => $value ) {
			$bytes  = strlen( $value );
			$total += $bytes;

			if ( $bytes > $limits['hard_pane'] ) {
				return new WP_Error(
					'rawmark_pane_too_large',
					sprintf(
						/* translators: 1: pane name, 2: limit in bytes */
						__( 'The %1$s pane exceeds the %2$s byte limit.', 'rawmark' ),
						$name,
						number_format_i18n( $limits['hard_pane'] )
					),
					array(
						'status' => 400,
						'pane'   => $name,
					)
				);
			}
		}

		if ( $total > $limits['hard_combined'] ) {
			return new WP_Error(
				'rawmark_combined_too_large',
				sprintf(
					/* translators: %s: limit in bytes */
					__( 'The combined size of all panes exceeds the %s byte limit.', 'rawmark' ),
					number_format_i18n( $limits['hard_combined'] )
				),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $settings
	 * @return array<string, mixed>
	 */
	private static function normalize_settings( array $settings ): array {
		return array(
			'seo_title'        => isset( $settings['seo_title'] ) ? (string) $settings['seo_title'] : '',
			'seo_description'  => isset( $settings['seo_description'] ) ? (string) $settings['seo_description'] : '',
			'use_wp_head'      => isset( $settings['use_wp_head'] ) ? (bool) $settings['use_wp_head'] : true,
			'use_wp_footer'    => isset( $settings['use_wp_footer'] ) ? (bool) $settings['use_wp_footer'] : true,
			'external_assets'  => isset( $settings['external_assets'] ) ? (bool) $settings['external_assets'] : false,
		);
	}
}
