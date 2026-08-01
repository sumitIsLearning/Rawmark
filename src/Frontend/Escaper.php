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
	 * script block early when inlined. Matching is case-insensitive (browsers
	 * close a script block on </ScRiPt just as on </script), but the matched
	 * text's original case is preserved in the output via the capture group -
	 * str_ireplace() would silently normalize it to the needle's case instead.
	 */
	public static function escape_script( string $js ): string {
		return preg_replace( '/<\/(script)/i', '<\\/$1', $js );
	}

	public static function escape_style( string $css ): string {
		return preg_replace( '/<\/(style)/i', '<\\/$1', $css );
	}
}
