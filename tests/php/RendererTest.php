<?php
/**
 * Frontend renderer: clean standalone output and compose-time escaping.
 *
 * @package Rawmark
 */

use Rawmark\Frontend\Escaper;
use Rawmark\Storage\HeaderTemplate;
use Rawmark\Storage\PageFlag;
use Rawmark\Storage\PostTemplate;
use Rawmark\Storage\PostTemplateTypes;
use Rawmark\Storage\Source;

class Test_Renderer extends WP_UnitTestCase {

	public function test_script_close_sequence_is_escaped(): void {
		$this->assertSame( '<\/script>', Escaper::escape_script( '</script>' ) );
		$this->assertSame( 'var s = "<\/script>";', Escaper::escape_script( 'var s = "</script>";' ) );
	}

	public function test_escaping_is_case_insensitive(): void {
		$this->assertSame( '<\/ScRiPt>', Escaper::escape_script( '</ScRiPt>' ) );
		$this->assertSame( '<\/STYLE>', Escaper::escape_style( '</STYLE>' ) );
	}

	public function test_style_close_sequence_is_escaped(): void {
		$this->assertSame( '<\/style>', Escaper::escape_style( '</style>' ) );
	}

	/**
	 * go_to( get_permalink( $id ) ) cannot be used to test the renderer's
	 * status guard. For a non-public post the permalink is a ?page_id=N URL
	 * that the main query refuses to resolve for an anonymous visitor, so
	 * get_queried_object() returns null and Router::maybe_render() bails at
	 * its very first guard - the `! $post instanceof WP_Post` check - and
	 * the post_status/read_post block below it is never executed. A test
	 * built that way passes whether or not that block exists at all.
	 *
	 * Priming $wp_query directly with an explicit post_status is what makes
	 * the main query hand back a real WP_Post for a non-public post, so the
	 * guard is genuinely reached. Verified by mutation: deleting the
	 * post_status/read_post block from Router::maybe_render() makes both
	 * tests below fail.
	 */
	private function prime_singular_query( int $id, string $status ): void {
		global $wp_query, $wp_the_query;

		$wp_query = new WP_Query(
			array(
				'page_id'     => $id,
				'post_type'   => 'page',
				'post_status' => $status,
			)
		);

		$wp_the_query = $wp_query;

		$this->assertInstanceOf(
			WP_Post::class,
			get_queried_object(),
			'The query must actually resolve the post, or the guard under test is never reached.'
		);
	}

	private function non_public_page( string $status ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => $status,
			)
		);
		PageFlag::enable( $id );
		Source::save( $id, '<h1>secret</h1>', '', '', array() );

		return $id;
	}

	public function test_draft_is_not_rendered_to_logged_out_visitors(): void {
		$id = $this->non_public_page( 'draft' );

		wp_set_current_user( 0 );
		$this->prime_singular_query( $id, 'draft' );

		$template = apply_filters( 'template_include', 'theme-template.php' );

		$this->assertStringNotContainsString( 'code-page.php', $template );

		// set_404() only happens inside the guard, so this is what proves
		// the guard ran rather than the renderer having declined earlier
		// for some unrelated reason.
		$this->assertTrue( is_404() );
	}

	// The guard reads post_status, not "is it a draft", so private pages
	// take the same path. The spec's security requirements name drafts
	// explicitly; this pins the neighbouring case so it cannot regress
	// unnoticed.
	public function test_private_page_is_not_rendered_to_logged_out_visitors(): void {
		$id = $this->non_public_page( 'private' );

		wp_set_current_user( 0 );
		$this->prime_singular_query( $id, 'private' );

		$template = apply_filters( 'template_include', 'theme-template.php' );

		$this->assertStringNotContainsString( 'code-page.php', $template );
		$this->assertTrue( is_404() );
	}

	// The other side of the guard: a user who may read the post still gets
	// the standalone document, so the check rejects on capability rather
	// than on status alone.
	public function test_draft_is_rendered_for_a_user_who_may_read_it(): void {
		$id = $this->non_public_page( 'draft' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->prime_singular_query( $id, 'draft' );

		$this->assertStringContainsString(
			'code-page.php',
			apply_filters( 'template_include', 'theme-template.php' )
		);
	}

	public function test_flagged_post_renders_the_plugin_template(): void {
		$id = self::factory()->post->create(
			array( 'post_type' => 'post', 'post_status' => 'publish' )
		);
		PageFlag::enable( $id );
		Source::save( $id, '<h1>Hi</h1>', '', '', array() );

		$this->go_to( get_permalink( $id ) );

		$this->assertStringContainsString(
			'code-page.php',
			apply_filters( 'template_include', 'theme-template.php' )
		);
	}

	public function test_flagged_page_renders_the_plugin_template(): void {
		$id = self::factory()->post->create(
			array( 'post_type' => 'page', 'post_status' => 'publish' )
		);
		\Rawmark\Storage\PageFlag::enable( $id );
		\Rawmark\Storage\Source::save( $id, '<h1>Hi</h1>', '', '', array() );

		$this->go_to( get_permalink( $id ) );

		// A path, never echo+exit - exiting skips WordPress's shutdown chain,
		// which page-cache plugins depend on.
		$this->assertStringContainsString(
			'code-page.php',
			apply_filters( 'template_include', 'theme-template.php' )
		);
	}

	// The highest blast radius test in this phase: every ordinary page on the
	// site must keep rendering through the theme, untouched.
	public function test_unflagged_page_is_left_to_the_theme(): void {
		$id = self::factory()->post->create(
			array( 'post_type' => 'page', 'post_status' => 'publish' )
		);

		$this->go_to( get_permalink( $id ) );

		$this->assertSame(
			'theme-template.php',
			apply_filters( 'template_include', 'theme-template.php' )
		);
	}

	// The originating requirement of the whole migration off the custom post
	// type: a Rawmark page could not be the site's front page, because
	// page_on_front only accepts a Page. This is the test that says the
	// migration achieved what it was for.
	public function test_a_flagged_page_set_as_the_front_page_renders_standalone(): void {
		$id = self::factory()->post->create(
			array( 'post_type' => 'page', 'post_status' => 'publish' )
		);
		PageFlag::enable( $id );
		Source::save( $id, '<h1>Front</h1>', '', '', array() );

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $id );

		$this->go_to( home_url( '/' ) );

		$this->assertTrue( is_front_page() );
		$this->assertSame( $id, get_queried_object_id() );
		$this->assertStringContainsString(
			'code-page.php',
			apply_filters( 'template_include', 'theme-template.php' )
		);
	}

	// The other half of the originating requirement: a clean /{slug}/
	// permalink, with none of the /code-page/ prefixing the old custom post
	// type's rewrite rules imposed, and no ?p= fallback.
	public function test_a_flagged_page_has_a_clean_permalink(): void {
		$this->set_permalink_structure( '/%postname%/' );

		$id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_name'   => 'my-raw-page',
			)
		);
		PageFlag::enable( $id );

		$permalink = get_permalink( $id );

		$this->assertSame( home_url( '/my-raw-page/' ), $permalink );
		$this->assertStringNotContainsString( '?', $permalink );
		$this->assertStringNotContainsString( 'code-page', $permalink );
	}

	public function test_a_flagged_pages_render_actually_runs_through_the_composer(): void {
		$snippet = self::factory()->post->create( array( 'post_type' => 'rawmark_snippet' ) );
		\Rawmark\Storage\Source::save( $snippet, '<nav>real nav</nav>', '', '', array() );

		$id = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) );
		\Rawmark\Storage\PageFlag::enable( $id );
		\Rawmark\Storage\Source::save( $id, "<!-- rawmark:snippet id='" . $snippet . "' -->", '', '', array() );

		$this->go_to( get_permalink( $id ) );
		$template = apply_filters( 'template_include', 'theme-template.php' );

		ob_start();
		include $template;
		$output = ob_get_clean();

		$this->assertStringContainsString( '<nav>real nav</nav>', $output );
	}

	public function test_a_flagged_pages_render_expands_shortcodes(): void {
		add_shortcode(
			'rawmark_test_shortcode',
			static function (): string {
				return '<span>shortcode output</span>';
			}
		);

		$id = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) );
		PageFlag::enable( $id );
		Source::save( $id, '<p>before</p>[rawmark_test_shortcode]<p>after</p>', '', '', array() );

		$this->go_to( get_permalink( $id ) );
		$template = apply_filters( 'template_include', 'theme-template.php' );

		ob_start();
		include $template;
		$output = ob_get_clean();

		remove_shortcode( 'rawmark_test_shortcode' );

		$this->assertStringContainsString( '<span>shortcode output</span>', $output );
		$this->assertStringNotContainsString( '[rawmark_test_shortcode]', $output );
	}

	public function test_an_unflagged_post_renders_through_the_designated_template(): void {
		$template = self::factory()->post->create( array( 'post_type' => 'rawmark_snippet' ) );
		Source::save( $template, '<!-- rawmark:post_title -->', '', '', array() );
		PostTemplate::set( $template );

		$id = self::factory()->post->create(
			array( 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'My Post' )
		);

		$this->go_to( get_permalink( $id ) );
		$template_path = apply_filters( 'template_include', 'theme-template.php' );

		$this->assertStringContainsString( 'code-page.php', $template_path );

		ob_start();
		include $template_path;
		$output = ob_get_clean();

		$this->assertStringContainsString( 'My Post', $output );
	}

	public function test_an_unflagged_custom_post_type_renders_through_the_template_when_whitelisted(): void {
		register_post_type(
			'rawmark_test_cpt',
			array( 'public' => true, 'publicly_queryable' => true, 'label' => 'Test CPT' )
		);
		PostTemplateTypes::set( array( 'post', 'rawmark_test_cpt' ) );

		$template = self::factory()->post->create( array( 'post_type' => 'rawmark_snippet' ) );
		Source::save( $template, '<!-- rawmark:post_title -->', '', '', array() );
		PostTemplate::set( $template );

		$id = self::factory()->post->create(
			array( 'post_type' => 'rawmark_test_cpt', 'post_status' => 'publish', 'post_title' => 'My Product' )
		);

		$this->go_to( get_permalink( $id ) );
		$template_path = apply_filters( 'template_include', 'theme-template.php' );

		$this->assertStringContainsString( 'code-page.php', $template_path );

		ob_start();
		include $template_path;
		$output = ob_get_clean();

		$this->assertStringContainsString( 'My Product', $output );
	}

	// Default whitelist is ['post'] only (SureCart isn't installed in this
	// test environment, so 'sc_product' never enters the default) - an
	// arbitrary custom post type must NOT get Post Template treatment
	// until a site owner explicitly checks it in Settings. Guards against
	// the whitelist accidentally becoming permissive.
	public function test_an_unflagged_custom_post_type_uses_the_theme_when_not_whitelisted(): void {
		register_post_type(
			'rawmark_test_cpt',
			array( 'public' => true, 'publicly_queryable' => true, 'label' => 'Test CPT' )
		);

		$template = self::factory()->post->create( array( 'post_type' => 'rawmark_snippet' ) );
		Source::save( $template, '<p>template</p>', '', '', array() );
		PostTemplate::set( $template );

		$id = self::factory()->post->create( array( 'post_type' => 'rawmark_test_cpt', 'post_status' => 'publish' ) );

		$this->go_to( get_permalink( $id ) );

		$this->assertSame(
			'theme-template.php',
			apply_filters( 'template_include', 'theme-template.php' )
		);
	}

	// is_singular( array() ) is not "match nothing" - WP_Query treats an
	// empty array as no type restriction at all and matches any singular
	// request. Router must guard this explicitly, or unchecking every box
	// in Settings would make Post Template apply to every unflagged post
	// of every type - the opposite of what emptying the whitelist means.
	public function test_emptying_the_whitelist_falls_back_to_the_theme_even_for_post(): void {
		PostTemplateTypes::set( array() );

		$template = self::factory()->post->create( array( 'post_type' => 'rawmark_snippet' ) );
		Source::save( $template, '<p>template</p>', '', '', array() );
		PostTemplate::set( $template );

		$id = self::factory()->post->create( array( 'post_type' => 'post', 'post_status' => 'publish' ) );

		$this->go_to( get_permalink( $id ) );

		$this->assertSame(
			'theme-template.php',
			apply_filters( 'template_include', 'theme-template.php' )
		);
	}

	public function test_an_unflagged_post_uses_the_theme_when_no_template_is_set(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'post', 'post_status' => 'publish' ) );

		$this->go_to( get_permalink( $id ) );

		$this->assertSame(
			'theme-template.php',
			apply_filters( 'template_include', 'theme-template.php' )
		);
	}

	public function test_a_flagged_post_renders_its_own_source_even_when_a_template_is_set(): void {
		$template = self::factory()->post->create( array( 'post_type' => 'rawmark_snippet' ) );
		Source::save( $template, '<p>template content</p>', '', '', array() );
		PostTemplate::set( $template );

		$id = self::factory()->post->create( array( 'post_type' => 'post', 'post_status' => 'publish' ) );
		PageFlag::enable( $id );
		Source::save( $id, '<p>own content</p>', '', '', array() );

		$this->go_to( get_permalink( $id ) );
		$template_path = apply_filters( 'template_include', 'theme-template.php' );

		ob_start();
		include $template_path;
		$output = ob_get_clean();

		$this->assertStringContainsString( 'own content', $output );
		$this->assertStringNotContainsString( 'template content', $output );
	}

	public function test_a_template_pointing_at_a_deleted_snippet_falls_back_to_the_theme(): void {
		$template = self::factory()->post->create( array( 'post_type' => 'rawmark_snippet' ) );
		PostTemplate::set( $template );
		wp_delete_post( $template, true );

		$id = self::factory()->post->create( array( 'post_type' => 'post', 'post_status' => 'publish' ) );

		$this->go_to( get_permalink( $id ) );

		$this->assertSame(
			'theme-template.php',
			apply_filters( 'template_include', 'theme-template.php' )
		);
	}

	public function test_flagged_page_with_no_own_header_uses_the_global_header_template(): void {
		$header = self::factory()->post->create( array( 'post_type' => 'rawmark_snippet' ) );
		Source::save( $header, '<header>Global Header</header>', '', '', array() );
		HeaderTemplate::set( $header );

		$id = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) );
		PageFlag::enable( $id );
		Source::save( $id, '<main>Body</main>', '', '', array() );

		$this->go_to( get_permalink( $id ) );
		$template = apply_filters( 'template_include', 'theme-template.php' );

		ob_start();
		include $template;
		$output = ob_get_clean();

		$this->assertStringContainsString( '<header>Global Header</header><main>Body</main>', $output );
	}

	// The scope decision from the design doc: an unflagged Post rendered
	// through the Post Template fallback must NOT pick up the global
	// header, even when one is set - only a real flagged Code Page does.
	public function test_post_template_rendered_post_does_not_use_the_global_header_template(): void {
		$header = self::factory()->post->create( array( 'post_type' => 'rawmark_snippet' ) );
		Source::save( $header, '<header>Global Header</header>', '', '', array() );
		HeaderTemplate::set( $header );

		$post_template = self::factory()->post->create( array( 'post_type' => 'rawmark_snippet' ) );
		Source::save( $post_template, '<article>PT body</article>', '', '', array() );
		PostTemplate::set( $post_template );

		$id = self::factory()->post->create( array( 'post_type' => 'post', 'post_status' => 'publish' ) );

		$this->go_to( get_permalink( $id ) );
		$template = apply_filters( 'template_include', 'theme-template.php' );

		ob_start();
		include $template;
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'Global Header', $output );
		$this->assertStringContainsString( 'PT body', $output );
	}

	public function test_flagged_page_with_no_current_post_still_resolves_a_post_loop(): void {
		self::factory()->post->create( array( 'post_title' => 'Looped Post', 'post_status' => 'publish' ) );

		$id = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) );
		PageFlag::enable( $id );
		Source::save(
			$id,
			"<!-- rawmark:post_loop count='1' --><!-- rawmark:post_title --><!-- /rawmark:post_loop -->",
			'',
			'',
			array()
		);

		$this->go_to( get_permalink( $id ) );
		$template = apply_filters( 'template_include', 'theme-template.php' );

		ob_start();
		include $template;
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Looped Post', $output );
	}

	// Pins the ordering decision from the design doc: loop resolution must
	// consume its own tags before the single-post pass runs, or the
	// single-post pass would stomp the loop's tags with the *page's own*
	// post data first.
	public function test_a_tag_outside_the_loop_resolves_to_the_pages_own_post_not_loop_data(): void {
		self::factory()->post->create( array( 'post_title' => 'Looped Post', 'post_status' => 'publish' ) );

		// The flagged Post below is itself a real, published post_type=post -
		// a valid loop match. Backdating it keeps it out of the "latest 1"
		// slot deterministically, so the assertion below can't pass by
		// accident depending on insertion-order tie-breaking.
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_title'  => 'The Real Post',
				'post_date'   => '2020-01-01 00:00:00',
			)
		);
		PageFlag::enable( $id );
		Source::save(
			$id,
			"<!-- rawmark:post_loop count='1' -->Loop: <!-- rawmark:post_title --><!-- /rawmark:post_loop -->" .
			'Outside: <!-- rawmark:post_title -->',
			'',
			'',
			array()
		);

		$this->go_to( get_permalink( $id ) );
		$template = apply_filters( 'template_include', 'theme-template.php' );

		ob_start();
		include $template;
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Loop: Looped Post', $output );
		$this->assertStringContainsString( 'Outside: The Real Post', $output );
	}

	// Fail-safe default: most Rawmark pages are hand-authored HTML with no
	// block comments in them at all, so do_blocks() must stay off unless a
	// page opts in.
	public function test_block_markup_is_inert_by_default(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) );
		PageFlag::enable( $id );
		Source::save( $id, "<!-- wp:paragraph -->\n<p>Hello Block</p>\n<!-- /wp:paragraph -->", '', '', array() );

		$this->go_to( get_permalink( $id ) );
		$template = apply_filters( 'template_include', 'theme-template.php' );

		ob_start();
		include $template;
		$output = ob_get_clean();

		$this->assertStringContainsString( '<!-- wp:paragraph -->', $output );
	}

	public function test_block_markup_renders_when_enable_blocks_is_on(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) );
		PageFlag::enable( $id );
		Source::save(
			$id,
			"<!-- wp:paragraph -->\n<p>Hello Block</p>\n<!-- /wp:paragraph -->",
			'',
			'',
			array( 'enable_blocks' => true )
		);

		$this->go_to( get_permalink( $id ) );
		$template = apply_filters( 'template_include', 'theme-template.php' );

		ob_start();
		include $template;
		$output = ob_get_clean();

		$this->assertStringNotContainsString( '<!-- wp:paragraph -->', $output );
		$this->assertStringContainsString( 'Hello Block', $output );
	}

	// The Router carve-out (#3): stripping wp-block-library/global-styles
	// unconditionally would break structural blocks (core/columns,
	// core/group, ...) the moment a page actually uses them.
	//
	// wp_deregister_style() (already in dequeue_theme_assets(), untouched
	// by this change) has a real side effect that outlives a single test -
	// every earlier test in this file renders a flagged page with
	// enable_blocks off by default, so by the time this test runs,
	// wp-block-library is already gone from the global registry for the
	// rest of the PHPUnit process regardless of what this test does.
	// Re-registering it under test control (rather than relying on
	// whatever WP core's own bootstrap-time registration left behind)
	// means this test verifies Router's own decision, not leftover state
	// from tests that ran before it.
	public function test_block_library_style_is_kept_when_enabled_and_stripped_when_not(): void {
		wp_register_style( 'wp-block-library', false );
		wp_enqueue_style( 'wp-block-library' );

		$with_blocks = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) );
		PageFlag::enable( $with_blocks );
		Source::save( $with_blocks, '<p>Hi</p>', '', '', array( 'enable_blocks' => true ) );

		$this->go_to( get_permalink( $with_blocks ) );
		apply_filters( 'template_include', 'theme-template.php' );
		do_action( 'wp_enqueue_scripts' );

		$this->assertTrue( wp_style_is( 'wp-block-library', 'enqueued' ) );

		wp_register_style( 'wp-block-library', false );
		wp_enqueue_style( 'wp-block-library' );

		$without_blocks = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish' ) );
		PageFlag::enable( $without_blocks );
		Source::save( $without_blocks, '<p>Hi</p>', '', '', array() );

		$this->go_to( get_permalink( $without_blocks ) );
		apply_filters( 'template_include', 'theme-template.php' );
		do_action( 'wp_enqueue_scripts' );

		$this->assertFalse( wp_style_is( 'wp-block-library', 'enqueued' ) );
	}

	public function tear_down(): void {
		// go_to() rebuilds these for most tests, but prime_singular_query()
		// installs a query by hand and the front-page test rewrites two
		// options. Neither is reset by WP_UnitTestCase, so both would leak
		// into whatever runs next in this process.
		update_option( 'show_on_front', 'posts' );
		update_option( 'page_on_front', 0 );
		$this->set_permalink_structure( '' );

		parent::tear_down();
	}
}
