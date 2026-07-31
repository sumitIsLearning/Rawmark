<?php
use Rawmark\Migration\Migrator;
use Rawmark\Storage\PageFlag;
use Rawmark\Storage\Source;

class Test_Migration extends WP_UnitTestCase {

	public function test_code_page_becomes_a_flagged_page_with_content_intact(): void {
		register_post_type( 'rawmark_code_page', array( 'public' => true ) );

		$html = '<section class="hero" data-x="1"><h1>Hello &amp; welcome</h1></section>';
		$css  = '.hero{color:#191919} /* </style> */';
		$js   = 'var s = "</script>"; console.log("ok");';

		$id = self::factory()->post->create(
			array(
				'post_type'   => 'rawmark_code_page',
				'post_status' => 'publish',
				'post_title'  => 'Home',
				'post_name'   => 'home',
			)
		);
		Source::save( $id, $html, $css, $js, array() );

		delete_option( Migrator::VERSION_OPTION );
		$converted = Migrator::migrate();

		$this->assertSame( 1, $converted );
		$this->assertSame( 'page', get_post_type( $id ) );
		$this->assertTrue( PageFlag::is_enabled( $id ) );
		$this->assertSame( 'home', get_post( $id )->post_name );

		// Re-read from the database, never from a return value.
		wp_cache_flush();
		$stored = Source::get( $id );
		$this->assertSame( $html, $stored['html'] );
		$this->assertSame( $css, $stored['css'] );
		$this->assertSame( $js, $stored['js'] );
	}

	public function test_migration_runs_only_once(): void {
		delete_option( Migrator::VERSION_OPTION );
		Migrator::run_if_needed();
		$first = get_option( Migrator::VERSION_OPTION );

		Migrator::run_if_needed();

		$this->assertSame( $first, get_option( Migrator::VERSION_OPTION ) );
	}

	public function test_no_code_pages_is_a_no_op(): void {
		delete_option( Migrator::VERSION_OPTION );
		$this->assertSame( 0, Migrator::migrate() );
	}

	// The only configuration that can occur in production. Nothing registers
	// rawmark_code_page any more (the post type was deleted in Task 9), so on
	// a real upgrading site Migrator queries a type WordPress has never heard
	// of. Every other test in this class registers it first, which is now an
	// impossible state. This matters because run_if_needed() writes the
	// version option regardless of what migrate() returns: a silently empty
	// query would permanently skip migration and strand every legacy Code
	// Page as an orphaned, invisible row.
	//
	// The row is inserted with $wpdb->insert() rather than wp_insert_post()
	// deliberately - the point is to prove the get_posts() query itself
	// matches on the post_type column without a registry lookup, not to lean
	// on whatever tolerance core's insert path happens to have.
	public function test_migrates_a_legacy_row_when_the_post_type_is_not_registered(): void {
		// WP_UnitTestCase only resets post types when WP_RUN_CORE_TESTS is
		// defined, which it never is for a plugin suite (see
		// abstract-testcase.php:125). A register_post_type() call in any
		// sibling test therefore survives for the rest of the PHPUnit
		// process, so the unregistered state this test exists to cover has
		// to be established explicitly rather than assumed. Without this the
		// test would still pass while quietly exercising the registered path
		// - the exact false negative it is meant to rule out.
		unregister_post_type( 'rawmark_code_page' );
		$this->assertFalse( post_type_exists( 'rawmark_code_page' ) );

		global $wpdb;
		$wpdb->insert(
			$wpdb->posts,
			array(
				'post_type'    => 'rawmark_code_page',
				'post_status'  => 'publish',
				'post_title'   => 'Legacy',
				'post_name'    => 'legacy',
				'post_author'  => 1,
				'post_content' => '',
				'post_excerpt' => '',
			)
		);
		$id = (int) $wpdb->insert_id;
		clean_post_cache( $id );

		delete_option( Migrator::VERSION_OPTION );

		$this->assertSame( 1, Migrator::migrate() );

		// Re-read from the database, never from a return value.
		wp_cache_flush();
		$this->assertSame( 'page', get_post_type( $id ) );
		$this->assertTrue( PageFlag::is_enabled( $id ) );
	}

	// Migrator itself performs no explicit collision check - a Code Page
	// migrating to a slug already held by an existing Page is left entirely
	// to wp_update_post()'s own call to wp_unique_post_slug(), which renames
	// the incoming post (verified: "home" + "home" -> the pre-existing Page
	// keeps "home", the migrated post becomes "home-2"). This locks in that
	// observed core behavior so a WordPress upgrade changing it is caught.
	public function test_colliding_slug_is_renamed_by_core(): void {
		register_post_type( 'rawmark_code_page', array( 'public' => true ) );

		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Home',
				'post_name'   => 'home',
			)
		);

		$code_page_id = self::factory()->post->create(
			array(
				'post_type'   => 'rawmark_code_page',
				'post_status' => 'publish',
				'post_title'  => 'Home',
				'post_name'   => 'home',
			)
		);

		delete_option( Migrator::VERSION_OPTION );
		$converted = Migrator::migrate();

		$this->assertSame( 1, $converted );

		// Re-read from the database, never from a return value.
		wp_cache_flush();
		$this->assertSame( 'page', get_post_type( $code_page_id ) );
		$this->assertSame( 'home', get_post( $page_id )->post_name );
		$this->assertSame( 'home-2', get_post( $code_page_id )->post_name );
	}
}
