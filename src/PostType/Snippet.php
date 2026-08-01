<?php
/**
 * Registers the rawmark_snippet post type.
 *
 * @package Rawmark
 */

namespace Rawmark\PostType;

use Rawmark\Support\Hookable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Snippet implements Hookable {

	public const SLUG = 'rawmark_snippet';

	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	/**
	 * Never public, never queryable, never in REST: a snippet is only ever
	 * an insert source, resolved server-side by SnippetComposer. It has no
	 * URL of its own and nothing outside this plugin should be able to
	 * read or discover it.
	 */
	public function register_post_type(): void {
		register_post_type(
			self::SLUG,
			array(
				'label'               => __( 'Rawmark Snippets', 'rawmark' ),
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'hierarchical'        => false,
				'supports'            => array( 'title' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);
	}
}
