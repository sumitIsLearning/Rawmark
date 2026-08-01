<?php
use Rawmark\Admin\PageListIntegration;
use Rawmark\Storage\PageFlag;

class Test_Page_List_Integration extends WP_UnitTestCase {

	public function test_row_action_is_added_for_a_post(): void {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		$id = self::factory()->post->create( array( 'post_type' => 'post' ) );

		( new PageListIntegration() )->register();
		$actions = apply_filters( 'post_row_actions', array(), get_post( $id ) );

		$this->assertArrayHasKey( 'rawmark', $actions );
	}

	public function test_column_is_added_to_the_posts_list(): void {
		( new PageListIntegration() )->register();
		$columns = apply_filters( 'manage_posts_columns', array() );

		$this->assertArrayHasKey( 'rawmark', $columns );
	}

	public function test_column_renders_the_built_indicator_for_a_flagged_post(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'post' ) );
		PageFlag::enable( $id );

		( new PageListIntegration() )->register();
		ob_start();
		do_action( 'manage_posts_custom_column', 'rawmark', $id );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'dashicons-editor-code', $html );
	}
}
