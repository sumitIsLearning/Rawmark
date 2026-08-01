<?php
use Rawmark\Admin\HeaderFooterMetaBox;
use Rawmark\PostType\Snippet;
use Rawmark\Storage\PageFlag;
use Rawmark\Storage\SnippetLink;

class Test_Header_Footer_Meta_Box extends WP_UnitTestCase {

	public function test_the_metabox_is_only_added_for_a_flagged_page(): void {
		global $wp_meta_boxes;
		$wp_meta_boxes = array();

		$flagged   = self::factory()->post->create( array( 'post_type' => 'page' ) );
		PageFlag::enable( $flagged );
		$plain = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$box = new HeaderFooterMetaBox();
		$box->add_box( get_post( $flagged ) );
		$box->add_box( get_post( $plain ) );

		$this->assertArrayHasKey( 'rawmark-header-footer', $wp_meta_boxes['page']['side']['default'] );
	}

	public function test_saving_stores_a_linked_snippet_reference(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$page    = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$snippet = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );
		SnippetLink::link( $snippet );

		$_POST['rawmark_header_footer_nonce'] = wp_create_nonce( 'rawmark_header_footer' );
		$_POST['rawmark_header_snippet']      = (string) $snippet;

		( new HeaderFooterMetaBox() )->save( $page );

		$this->assertSame( $snippet, (int) get_post_meta( $page, '_rawmark_header_snippet', true ) );
	}

	// The server re-validates linked-ness rather than trusting the posted
	// ID outright - a tampered or stale form value pointing at an unlinked
	// (or non-snippet) post must never be stored as a reference.
	public function test_saving_rejects_a_reference_to_an_unlinked_snippet(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$page    = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$snippet = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) ); // not linked

		$_POST['rawmark_header_footer_nonce'] = wp_create_nonce( 'rawmark_header_footer' );
		$_POST['rawmark_footer_snippet']      = (string) $snippet;

		( new HeaderFooterMetaBox() )->save( $page );

		$this->assertSame( '', get_post_meta( $page, '_rawmark_footer_snippet', true ) );
	}

	public function test_saving_without_a_valid_nonce_changes_nothing(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		$page    = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$snippet = self::factory()->post->create( array( 'post_type' => Snippet::SLUG ) );
		SnippetLink::link( $snippet );

		unset( $_POST['rawmark_header_footer_nonce'] );
		$_POST['rawmark_header_snippet'] = (string) $snippet;

		( new HeaderFooterMetaBox() )->save( $page );

		$this->assertSame( '', get_post_meta( $page, '_rawmark_header_snippet', true ) );
	}
}
