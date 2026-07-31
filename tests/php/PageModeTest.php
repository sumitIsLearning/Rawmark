<?php
use Rawmark\Admin\PageModeToggle;
use Rawmark\Security\Capabilities;
use Rawmark\Storage\PageFlag;

class Test_Page_Mode extends WP_UnitTestCase {

	public function test_enable_url_carries_a_nonce(): void {
		$id  = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$url = PageModeToggle::enable_url( $id );

		$this->assertStringContainsString( '_wpnonce=', $url );
		$this->assertStringContainsString( (string) $id, $url );
	}

	public function test_a_user_without_the_capability_cannot_enable(): void {
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor );
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertFalse( PageModeToggle::user_may_toggle( $id ) );
	}

	public function test_an_admin_may_toggle(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertTrue( PageModeToggle::user_may_toggle( $id ) );
	}

	// Disabling must not destroy the code - re-enabling has to bring it back.
	public function test_disabling_preserves_the_source(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		PageFlag::enable( $id );
		\Rawmark\Storage\Source::save( $id, '<h1>keep me</h1>', '', '', array() );

		PageFlag::disable( $id );

		wp_cache_flush();
		$this->assertSame( '<h1>keep me</h1>', \Rawmark\Storage\Source::get( $id )['html'] );
	}

	public function test_block_editor_is_disabled_for_a_flagged_page(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		PageFlag::enable( $id );

		( new \Rawmark\Admin\EditorLock() )->register();

		$this->assertFalse( use_block_editor_for_post( get_post( $id ) ) );
	}

	public function test_block_editor_still_works_for_a_normal_page(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		( new \Rawmark\Admin\EditorLock() )->register();

		$this->assertTrue( use_block_editor_for_post( get_post( $id ) ) );
	}

	// remove_post_type_support() mutates global state that isn't reset
	// between tests, so this test's own tearDown() restores it - otherwise
	// it would silently poison every test that runs after it in this
	// process.
	public function test_render_panel_removes_editor_support_for_a_flagged_page(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		PageFlag::enable( $id );

		( new \Rawmark\Admin\EditorLock() )->register();
		do_action( 'edit_form_after_title', get_post( $id ) );

		$this->assertFalse( post_type_supports( 'page', 'editor' ) );
	}

	public function test_render_panel_is_a_no_op_for_an_unflagged_page(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		( new \Rawmark\Admin\EditorLock() )->register();
		do_action( 'edit_form_after_title', get_post( $id ) );

		$this->assertTrue( post_type_supports( 'page', 'editor' ) );
	}

	// Design spec section 10, "Saving from the classic screen does not alter
	// _rawmark_source" - the desync-prevention requirement. The classic
	// screen submits the whole post row, so this is that write shape: a
	// title change carrying a post_content the screen believes is current.
	// _rawmark_source lives in separate meta and must come through
	// byte-identical.
	public function test_a_classic_screen_save_does_not_alter_the_source(): void {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft',
			)
		);
		PageFlag::enable( $id );

		$html = '<section class="hero" data-x="1"><h1>Hello &amp; welcome</h1></section>';
		$css  = '.hero{color:#191919} /* </style> */';
		$js   = 'var s = "</script>"; console.log("ok");';
		\Rawmark\Storage\Source::save( $id, $html, $css, $js, array() );

		// Re-read from the database, never from a return value.
		wp_cache_flush();
		$before = get_post_meta( $id, \Rawmark\Storage\Source::META_KEY, true );

		wp_update_post(
			array(
				'ID'           => $id,
				'post_title'   => 'Renamed from the classic screen',
				'post_content' => '<p>whatever the classic editor had in its box</p>',
			)
		);

		wp_cache_flush();
		$after = get_post_meta( $id, \Rawmark\Storage\Source::META_KEY, true );

		$this->assertSame( $before, $after );

		$stored = \Rawmark\Storage\Source::get( $id );
		$this->assertSame( $html, $stored['html'] );
		$this->assertSame( $css, $stored['css'] );
		$this->assertSame( $js, $stored['js'] );

		// The title change must still land - the guard protects the mirror,
		// it does not make the row read-only.
		$this->assertSame( 'Renamed from the classic screen', get_post( $id )->post_title );
	}

	protected function tearDown(): void {
		// Restore in case a test above removed it - render_panel() mutates
		// this global registration, and WP_UnitTestCase does not reset it
		// automatically between tests.
		add_post_type_support( 'page', 'editor' );

		parent::tearDown();
	}
}
