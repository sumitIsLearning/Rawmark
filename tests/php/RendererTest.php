<?php
/**
 * Frontend renderer: clean standalone output and compose-time escaping.
 *
 * @package Rawmark
 */

use Rawmark\Frontend\Escaper;
use Rawmark\PostType\CodePage;
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

	public function test_router_returns_a_template_path_for_published_pages(): void {
		$id = self::factory()->post->create(
			array(
				'post_type'   => CodePage::SLUG,
				'post_status' => 'publish',
			)
		);
		Source::save( $id, '<h1>Hi</h1>', '', '', array() );

		$this->go_to( get_permalink( $id ) );
		$template = apply_filters( 'template_include', 'theme-template.php' );

		// A path, never echo+exit - exiting skips WordPress's shutdown chain,
		// which page-cache plugins depend on.
		$this->assertStringContainsString( 'code-page.php', $template );
	}

	public function test_draft_is_not_rendered_to_logged_out_visitors(): void {
		$id = self::factory()->post->create(
			array(
				'post_type'   => CodePage::SLUG,
				'post_status' => 'draft',
			)
		);
		Source::save( $id, '<h1>secret</h1>', '', '', array() );

		wp_set_current_user( 0 );
		$this->go_to( get_permalink( $id ) );
		$template = apply_filters( 'template_include', 'theme-template.php' );

		$this->assertStringNotContainsString( 'code-page.php', $template );
	}

	public function test_flagged_page_renders_the_plugin_template(): void {
		$id = self::factory()->post->create(
			array( 'post_type' => 'page', 'post_status' => 'publish' )
		);
		\Rawmark\Storage\PageFlag::enable( $id );
		\Rawmark\Storage\Source::save( $id, '<h1>Hi</h1>', '', '', array() );

		$this->go_to( get_permalink( $id ) );

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
}
