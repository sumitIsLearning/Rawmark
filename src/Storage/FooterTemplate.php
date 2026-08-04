<?php
/**
 * Reads and writes which Snippet, if any, is the site's Footer Template.
 *
 * @package Rawmark
 */

namespace Rawmark\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FooterTemplate {

	public const OPTION_KEY = 'rawmark_footer_template_id';

	public static function get_id(): int {
		return TemplateOption::get_id( self::OPTION_KEY );
	}

	public static function set( int $snippet_id ): void {
		TemplateOption::set( self::OPTION_KEY, $snippet_id );
	}

	public static function clear(): void {
		TemplateOption::clear( self::OPTION_KEY );
	}

	public static function is_set(): bool {
		return TemplateOption::is_set( self::OPTION_KEY );
	}
}
