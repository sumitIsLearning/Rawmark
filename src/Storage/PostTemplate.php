<?php
/**
 * Reads and writes which Snippet, if any, is the site's Post Template.
 *
 * @package Rawmark
 */

namespace Rawmark\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PostTemplate {

	public const OPTION_KEY = 'rawmark_post_template_id';

	public static function get_id(): int {
		return TemplateOption::get_id( self::OPTION_KEY );
	}

	public static function set( int $snippet_id ): void {
		TemplateOption::set( self::OPTION_KEY, $snippet_id );
	}

	public static function clear(): void {
		TemplateOption::clear( self::OPTION_KEY );
	}

	/**
	 * The single answer to "does a valid template exist right now?" - see
	 * TemplateOption::is_set() for the fail-safe this delegates to, the
	 * same one that lets Router fail safe to the theme with no extra
	 * guarding anywhere else.
	 */
	public static function is_set(): bool {
		return TemplateOption::is_set( self::OPTION_KEY );
	}
}
