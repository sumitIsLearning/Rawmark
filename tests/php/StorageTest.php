<?php
/**
 * Storage round-trip tests.
 *
 * These exist because of a real bug: update_metadata() runs wp_unslash()
 * on every value it stores, which strips JSON's \" escaping and leaves
 * invalid JSON on disk. Any page containing a double quote saved fine,
 * reported success, and then reloaded with three empty panes.
 *
 * The lesson encoded here: never assert on what the save call returned.
 * Always re-read from the database.
 *
 * @package Rawmark
 */

use Rawmark\Storage\Source;

class Test_Storage extends WP_UnitTestCase {

	private int $page_id;

	public function set_up(): void {
		parent::set_up();
		$this->page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
			)
		);
	}

	public function test_quoted_html_survives_the_database_round_trip(): void {
		$html = '<section class="hero" data-x="1"><h1>Hello &amp; welcome</h1></section>';
		$css  = '.hero{color:#191919} /* a literal </style> */';
		$js   = 'var s = "</script>"; console.log("ok");';

		Source::save( $this->page_id, $html, $css, $js, array() );

		// Re-read from the database, not from the return value.
		wp_cache_flush();
		$stored = Source::get( $this->page_id );

		$this->assertSame( $html, $stored['html'] );
		$this->assertSame( $css, $stored['css'] );
		$this->assertSame( $js, $stored['js'] );
	}

	public function test_stored_meta_remains_valid_json(): void {
		Source::save( $this->page_id, '<p class="x">q</p>', '', '', array() );

		wp_cache_flush();
		$raw = get_post_meta( $this->page_id, Source::META_KEY, true );

		$this->assertIsString( $raw );
		$this->assertNotNull( json_decode( $raw, true ), 'Stored meta must be decodable JSON.' );
	}

	public function test_oversized_pane_is_rejected_server_side(): void {
		$result = Source::save( $this->page_id, str_repeat( 'a', 1048577 ), '', '', array() );

		$this->assertWPError( $result );
		$this->assertSame( 'html', $result->get_error_data()['pane'] );
	}
}
