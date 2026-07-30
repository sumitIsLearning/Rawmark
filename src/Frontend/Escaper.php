<?php
/**
 * Compose-time </script and </style escaping.
 *
 * @package Rawmark
 */

namespace Rawmark\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Escaper {

	/**
	 * A literal </script inside a JS string/regex/comment terminates the
	 * script block early when inlined. str_ireplace is used instead of a
	 * regex to avoid any ambiguity around backslash handling in
	 * preg_replace's replacement-string syntax.
	 */
	public static function escape_script( string $js ): string {
		return str_ireplace( '</script', '<\/script', $js );
	}

	public static function escape_style( string $css ): string {
		return str_ireplace( '</style', '<\/style', $css );
	}
}
