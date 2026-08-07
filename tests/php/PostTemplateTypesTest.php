<?php
use Rawmark\Storage\PostTemplateTypes;

class Test_Post_Template_Types extends WP_UnitTestCase {

	public function test_default_is_post_only_when_sc_product_is_not_registered(): void {
		$this->assertSame( array( 'post' ), PostTemplateTypes::get() );
		$this->assertTrue( PostTemplateTypes::is_eligible( 'post' ) );
		$this->assertFalse( PostTemplateTypes::is_eligible( 'page' ) );
	}

	// Mirrors the WooCommerce-detection pattern SettingsScreen already uses
	// for the Shop redirect (class_exists check), just against the post
	// type SureCart actually registers instead of a plugin class - the
	// thing this feature depends on.
	public function test_default_includes_sc_product_once_it_is_registered(): void {
		register_post_type( 'sc_product', array( 'public' => true ) );

		$this->assertSame( array( 'post', 'sc_product' ), PostTemplateTypes::get() );
		$this->assertTrue( PostTemplateTypes::is_eligible( 'sc_product' ) );

		_unregister_post_type( 'sc_product' );
	}

	public function test_set_then_get(): void {
		register_post_type( 'rawmark_test_cpt', array( 'public' => true ) );

		PostTemplateTypes::set( array( 'post', 'rawmark_test_cpt' ) );

		$this->assertSame( array( 'post', 'rawmark_test_cpt' ), PostTemplateTypes::get() );
		$this->assertTrue( PostTemplateTypes::is_eligible( 'rawmark_test_cpt' ) );
	}

	public function test_set_can_empty_it(): void {
		PostTemplateTypes::set( array( 'post' ) );

		PostTemplateTypes::set( array() );

		$this->assertSame( array(), PostTemplateTypes::get() );
		$this->assertFalse( PostTemplateTypes::is_eligible( 'post' ) );
	}

	// Defense in depth: a stored/submitted type that isn't a real,
	// selectable post type is silently dropped rather than trusted -
	// covers both a stale option (the type was later unregistered) and a
	// tampered request naming something that was never real.
	public function test_set_ignores_a_type_that_is_not_selectable(): void {
		PostTemplateTypes::set( array( 'post', 'not_a_real_post_type' ) );

		$this->assertSame( array( 'post' ), PostTemplateTypes::get() );
	}

	// Page is deliberately excluded - it already has its own per-item flag
	// (PageFlag::ELIGIBLE_TYPES) and code-page.php never resolves post-data
	// merge tags for one. Letting it into this whitelist would blur that
	// distinction for no requested benefit.
	public function test_page_is_never_selectable(): void {
		$this->assertNotContains( 'page', PostTemplateTypes::selectable_types() );

		PostTemplateTypes::set( array( 'post', 'page' ) );

		$this->assertSame( array( 'post' ), PostTemplateTypes::get() );
		$this->assertFalse( PostTemplateTypes::is_eligible( 'page' ) );
	}

	// Rawmark's own internal Snippet type and media attachments are never
	// public/selectable candidates either.
	public function test_snippet_and_attachment_are_never_selectable(): void {
		$selectable = PostTemplateTypes::selectable_types();

		$this->assertNotContains( 'rawmark_snippet', $selectable );
		$this->assertNotContains( 'attachment', $selectable );
	}
}
