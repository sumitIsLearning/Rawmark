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

	// 'post_status' => 'any' expands to every status *except* those
	// registered exclude_from_search => true, which silently drops 'trash'
	// and 'auto-draft'. Those rows were skipped, the version option bumped,
	// and - since nothing registers rawmark_code_page any more - a trashed
	// Code Page could never be restored: the post type it belonged to no
	// longer exists anywhere in the codebase.
	//
	// @dataProvider is deliberately not used: each status needs its own
	// clean assertion of both the type change and the flag.
	public function test_a_trashed_legacy_page_is_migrated_not_skipped(): void {
		register_post_type( 'rawmark_code_page', array( 'public' => true ) );

		$id = self::factory()->post->create(
			array(
				'post_type'   => 'rawmark_code_page',
				'post_status' => 'trash',
				'post_title'  => 'Trashed',
			)
		);

		delete_option( Migrator::VERSION_OPTION );

		$this->assertSame( 1, Migrator::migrate() );

		// Re-read from the database, never from a return value.
		wp_cache_flush();
		$this->assertSame( 'page', get_post_type( $id ) );
		$this->assertTrue( PageFlag::is_enabled( $id ) );

		// Still in the trash, so it is restorable - into a post type that
		// now exists, which is the entire point.
		$this->assertSame( 'trash', get_post_status( $id ) );
	}

	public function test_an_auto_draft_legacy_page_is_migrated_not_skipped(): void {
		register_post_type( 'rawmark_code_page', array( 'public' => true ) );

		$id = self::factory()->post->create(
			array(
				'post_type'   => 'rawmark_code_page',
				'post_status' => 'auto-draft',
				'post_title'  => 'Unsaved',
			)
		);

		delete_option( Migrator::VERSION_OPTION );

		$this->assertSame( 1, Migrator::migrate() );

		wp_cache_flush();
		$this->assertSame( 'page', get_post_type( $id ) );
	}

	// A conversion can fail for reasons outside this plugin - another
	// plugin's filter rejecting the write, a bad parent, a database error.
	// Flagging such a post would leave a permanently invisible orphan:
	// PageFlag::is_enabled() checks post_type, so the meta is never
	// honoured, but a later reader sees a _rawmark_enabled row and assumes
	// it migrated.
	public function test_a_failed_conversion_is_not_flagged_or_counted(): void {
		register_post_type( 'rawmark_code_page', array( 'public' => true ) );

		$id = self::factory()->post->create(
			array(
				'post_type'   => 'rawmark_code_page',
				'post_status' => 'publish',
				'post_title'  => 'Doomed',
			)
		);

		delete_option( Migrator::VERSION_OPTION );

		// Makes wp_insert_post() bail with WP_Error( 'empty_content' ) for
		// every write, which is the shape any rejecting filter produces.
		add_filter( 'wp_insert_post_empty_content', '__return_true' );
		$converted = Migrator::migrate();
		remove_filter( 'wp_insert_post_empty_content', '__return_true' );

		$this->assertSame( 0, $converted );
		$this->assertSame( 1, Migrator::failed_count() );

		wp_cache_flush();
		$this->assertSame( 'rawmark_code_page', get_post_type( $id ) );
		$this->assertSame( '', get_post_meta( $id, PageFlag::META_KEY, true ) );
	}

	// Bumping the version option past a failure would strand that row
	// forever - migration would never look at it again. Leaving the option
	// unset costs a repeated query per request until the cause is fixed,
	// which is loud and recoverable rather than silent and permanent.
	public function test_the_version_option_is_not_bumped_when_a_conversion_fails(): void {
		register_post_type( 'rawmark_code_page', array( 'public' => true ) );

		self::factory()->post->create(
			array(
				'post_type'   => 'rawmark_code_page',
				'post_status' => 'publish',
				'post_title'  => 'Doomed',
			)
		);

		delete_option( Migrator::VERSION_OPTION );

		add_filter( 'wp_insert_post_empty_content', '__return_true' );
		Migrator::run_if_needed();
		remove_filter( 'wp_insert_post_empty_content', '__return_true' );

		$this->assertFalse( get_option( Migrator::VERSION_OPTION ) );

		// And the retry succeeds once the cause is gone.
		Migrator::run_if_needed();
		$this->assertSame( 2, (int) get_option( Migrator::VERSION_OPTION ) );
	}

	// Rewrite rules are derived from registered post types and taxonomies at
	// init - post rows contribute nothing. Migration used to flush them from
	// plugins_loaded, before init, persisting a rule set with no categories,
	// no tags and no third-party CPTs and breaking those permalinks
	// site-wide. Nothing in migrate() may touch them.
	public function test_migration_does_not_flush_rewrite_rules(): void {
		register_post_type( 'rawmark_code_page', array( 'public' => true ) );

		self::factory()->post->create(
			array(
				'post_type'   => 'rawmark_code_page',
				'post_status' => 'publish',
			)
		);

		$sentinel = array( 'rawmark-sentinel/?$' => 'index.php?rawmark=1' );
		update_option( 'rewrite_rules', $sentinel );

		delete_option( Migrator::VERSION_OPTION );
		Migrator::migrate();

		$this->assertSame( $sentinel, get_option( 'rewrite_rules' ) );
	}

	// WP_UnitTestCase only resets post types when WP_RUN_CORE_TESTS is
	// defined, which a plugin suite never defines (see
	// abstract-testcase.php). Without this, the register_post_type() calls
	// above leak into every test class that runs after this one for the rest
	// of the PHPUnit process.
	public function tear_down(): void {
		if ( post_type_exists( 'rawmark_code_page' ) ) {
			unregister_post_type( 'rawmark_code_page' );
		}

		parent::tear_down();
	}
}
