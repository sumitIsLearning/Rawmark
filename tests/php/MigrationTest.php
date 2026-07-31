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
}
