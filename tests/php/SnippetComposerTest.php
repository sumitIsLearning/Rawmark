<?php
use Rawmark\Frontend\SnippetComposer;
use Rawmark\Storage\FooterTemplate;
use Rawmark\Storage\HeaderTemplate;
use Rawmark\Storage\Source;

class Test_Snippet_Composer extends WP_UnitTestCase {

	private function make_snippet( string $html, string $css = '', string $js = '' ): int {
		$id = self::factory()->post->create( array( 'post_type' => 'rawmark_snippet' ) );
		Source::save( $id, $html, $css, $js, array() );
		return $id;
	}

	public function test_a_single_marker_expands_to_the_snippets_html(): void {
		$snippet = $this->make_snippet( '<nav>the nav</nav>' );
		$page    = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$result = SnippetComposer::compose(
			$page,
			array( 'html' => "<!-- rawmark:snippet id='" . $snippet . "' -->", 'css' => '', 'js' => '' )
		);

		$this->assertSame( '<nav>the nav</nav>', $result['html'] );
	}

	public function test_the_same_snippet_placed_three_times_expands_three_times_but_css_and_js_appear_once(): void {
		$snippet = $this->make_snippet( '<span>x</span>', '.x{color:red}', 'console.log(1);' );
		$page    = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$marker  = "<!-- rawmark:snippet id='" . $snippet . "' -->";

		$result = SnippetComposer::compose(
			$page,
			array( 'html' => $marker . $marker . $marker, 'css' => '', 'js' => '' )
		);

		$this->assertSame( '<span>x</span><span>x</span><span>x</span>', $result['html'] );
		$this->assertSame( 1, substr_count( $result['css'], '.x{color:red}' ) );
		$this->assertSame( 1, substr_count( $result['js'], 'console.log(1);' ) );
	}

	public function test_a_marker_referencing_a_deleted_snippet_expands_to_nothing(): void {
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$result = SnippetComposer::compose(
			$page,
			array( 'html' => "<!-- rawmark:snippet id='999999' -->", 'css' => '', 'js' => '' )
		);

		$this->assertSame( '', $result['html'] );
	}

	public function test_header_and_footer_wrap_the_pages_own_html(): void {
		$header = $this->make_snippet( '<header>H</header>', '.h{}', 'h();' );
		$footer = $this->make_snippet( '<footer>F</footer>', '.f{}', 'f();' );
		$page   = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_post_meta( $page, '_rawmark_header_snippet', $header );
		update_post_meta( $page, '_rawmark_footer_snippet', $footer );

		$result = SnippetComposer::compose( $page, array( 'html' => '<main>M</main>', 'css' => '.m{}', 'js' => 'm();' ) );

		$this->assertSame( '<header>H</header><main>M</main><footer>F</footer>', $result['html'] );
		$this->assertStringContainsString( '.h{}', $result['css'] );
		$this->assertStringContainsString( '.m{}', $result['css'] );
		$this->assertStringContainsString( '.f{}', $result['css'] );
		$this->assertStringContainsString( 'h();', $result['js'] );
		$this->assertStringContainsString( 'm();', $result['js'] );
		$this->assertStringContainsString( 'f();', $result['js'] );
	}

	public function test_no_header_or_footer_set_leaves_the_page_untouched(): void {
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$result = SnippetComposer::compose( $page, array( 'html' => '<main>M</main>', 'css' => '', 'js' => '' ) );

		$this->assertSame( '<main>M</main>', $result['html'] );
	}

	// A regular Page's ID happening to numerically match a real post is not
	// enough - the target of _rawmark_header_snippet must actually BE a
	// rawmark_snippet post, or it's treated exactly like a deleted one.
	public function test_a_header_reference_pointing_at_a_non_snippet_post_is_ignored(): void {
		$not_a_snippet = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$page          = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_post_meta( $page, '_rawmark_header_snippet', $not_a_snippet );

		$result = SnippetComposer::compose( $page, array( 'html' => '<main>M</main>', 'css' => '', 'js' => '' ) );

		$this->assertSame( '<main>M</main>', $result['html'] );
	}

	public function test_global_header_template_used_when_page_has_no_own_header_and_defaults_are_on(): void {
		$header = $this->make_snippet( '<header>Global H</header>' );
		HeaderTemplate::set( $header );
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$result = SnippetComposer::compose(
			$page,
			array( 'html' => '<main>M</main>', 'css' => '', 'js' => '' ),
			true
		);

		$this->assertSame( '<header>Global H</header><main>M</main>', $result['html'] );
	}

	public function test_global_footer_template_used_when_page_has_no_own_footer_and_defaults_are_on(): void {
		$footer = $this->make_snippet( '<footer>Global F</footer>' );
		FooterTemplate::set( $footer );
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$result = SnippetComposer::compose(
			$page,
			array( 'html' => '<main>M</main>', 'css' => '', 'js' => '' ),
			true
		);

		$this->assertSame( '<main>M</main><footer>Global F</footer>', $result['html'] );
	}

	public function test_per_page_header_wins_over_the_global_template(): void {
		$own    = $this->make_snippet( '<header>Own H</header>' );
		$global = $this->make_snippet( '<header>Global H</header>' );
		HeaderTemplate::set( $global );
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_post_meta( $page, '_rawmark_header_snippet', $own );

		$result = SnippetComposer::compose(
			$page,
			array( 'html' => '<main>M</main>', 'css' => '', 'js' => '' ),
			true
		);

		$this->assertSame( '<header>Own H</header><main>M</main>', $result['html'] );
	}

	public function test_global_template_ignored_when_site_defaults_flag_is_false(): void {
		$header = $this->make_snippet( '<header>Global H</header>' );
		HeaderTemplate::set( $header );
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$result = SnippetComposer::compose(
			$page,
			array( 'html' => '<main>M</main>', 'css' => '', 'js' => '' ),
			false
		);

		$this->assertSame( '<main>M</main>', $result['html'] );
	}

	public function test_global_header_template_pointing_at_a_deleted_snippet_resolves_to_nothing(): void {
		$header = $this->make_snippet( '<header>Gone</header>' );
		HeaderTemplate::set( $header );
		wp_delete_post( $header, true );
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$result = SnippetComposer::compose(
			$page,
			array( 'html' => '<main>M</main>', 'css' => '', 'js' => '' ),
			true
		);

		$this->assertSame( '<main>M</main>', $result['html'] );
	}
}
