<?php
/**
 * Registers the rawmark_code_page post type.
 *
 * @package Rawmark
 */

namespace Rawmark\PostType;

use Rawmark\Security\Capabilities;
use Rawmark\Support\Hookable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CodePage implements Hookable {

	public const SLUG = 'rawmark_code_page';

	public function register(): void {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'pre_get_posts', array( $this, 'exclude_from_feeds' ) );
	}

	/**
	 * Code Pages hold raw HTML, CSS, and JS. Letting that into a feed would
	 * at best produce invalid XML and at worst inject markup into whatever
	 * renders the feed, so the post type is kept out of every feed query.
	 * The rewrite rules already omit feed endpoints; this covers a feed
	 * query built any other way.
	 *
	 * @param \WP_Query $query Query about to run.
	 */
	public function exclude_from_feeds( $query ): void {
		if ( ! $query->is_feed() ) {
			return;
		}

		$types = (array) $query->get( 'post_type' );

		if ( ! in_array( self::SLUG, $types, true ) ) {
			return;
		}

		$remaining = array_values( array_diff( $types, array( self::SLUG ) ) );

		$query->set( 'post_type', empty( $remaining ) ? array( 'post' ) : $remaining );
	}

	/**
	 * Every primitive capability maps to the single rawmark_edit_code
	 * capability. edit_post/read_post/delete_post get their own distinct
	 * strings instead (Capabilities::META_CAP_*) - see the comment on that
	 * class for why reusing rawmark_edit_code there breaks every bare
	 * current_user_can( 'rawmark_edit_code' ) check elsewhere.
	 */
	public function register_post_type(): void {
		register_post_type(
			self::SLUG,
			array(
				'labels'              => array(
					'name'          => __( 'Code Pages', 'rawmark' ),
					'singular_name' => __( 'Code Page', 'rawmark' ),
					'add_new_item'  => __( 'Add New Code Page', 'rawmark' ),
					'edit_item'     => __( 'Edit Code Page', 'rawmark' ),
					'all_items'     => __( 'Code Pages', 'rawmark' ),
					'search_items'  => __( 'Search Code Pages', 'rawmark' ),
					'not_found'     => __( 'No Code Pages found.', 'rawmark' ),
				),
				'public'              => true,
				'publicly_queryable'  => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => true,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'hierarchical'        => false,
				'menu_icon'           => 'dashicons-editor-code',
				'supports'            => array( 'title' ),
				'rewrite'             => array(
					'slug'       => 'code-page',
					'with_front' => false,
					'feeds'      => false,
				),
				'map_meta_cap'        => true,
				'capabilities'        => array(
					'edit_post'              => Capabilities::META_CAP_EDIT_POST,
					'read_post'              => Capabilities::META_CAP_READ_POST,
					'delete_post'            => Capabilities::META_CAP_DELETE_POST,
					'edit_posts'             => Capabilities::CAP,
					'edit_others_posts'      => Capabilities::CAP,
					'publish_posts'          => Capabilities::CAP,
					'read_private_posts'     => Capabilities::CAP,
					'delete_posts'           => Capabilities::CAP,
					'delete_private_posts'   => Capabilities::CAP,
					'delete_published_posts' => Capabilities::CAP,
					'delete_others_posts'    => Capabilities::CAP,
					'edit_private_posts'     => Capabilities::CAP,
					'edit_published_posts'   => Capabilities::CAP,
					'create_posts'           => Capabilities::CAP,
				),
			)
		);
	}
}
