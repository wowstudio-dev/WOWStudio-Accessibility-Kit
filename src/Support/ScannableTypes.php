<?php
/**
 * Which content this plugin works on.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Posts and pages, and deliberately nothing else.
 *
 * An allowlist rather than a set of exclusions, because the things worth
 * keeping out have nothing in common except that they are not what somebody
 * means by "a page of my site". A block theme registers template and template
 * part types; the pattern editor registers another; every page builder
 * registers a library of its own — Elementor's is public and labels itself "My
 * Templates", so it appeared in the picker beside Posts and Pages as though it
 * were somewhere a visitor could go. A rule that named those would need a new
 * clause for the next builder anybody installs.
 *
 * The three reasons this is the right boundary, rather than a smaller product:
 *
 * - **A template is not a page.** It has no URL, so there is nothing to render
 *   in the browser pass, and roughly half the checks only mean anything about a
 *   whole rendered document. Reporting a heading-order fault against a fragment
 *   that appears inside twenty different documents says nothing about any of
 *   them.
 * - **A pattern is not a page either**, for the same reason, and worse: the
 *   same pattern is inserted into content that gets scanned properly, so its
 *   faults are already found where they actually occur, attached to a page
 *   somebody can open and fix. Scanning the source as well doubles every
 *   finding.
 * - **A custom post type may be neither.** Some are pages in all but name and
 *   would scan perfectly; others hold rows of settings, or fragments, or
 *   records that never reach a browser. Nothing on the post type object says
 *   which, and a scan that guesses wrong produces a page of findings about
 *   markup that is never rendered.
 *
 * So the safe default is the two types WordPress always registers as documents
 * with URLs of their own, and `wsak_post_types` is how a site that knows better
 * says so. That filter is the
 * whole of the escape hatch, and it is deliberately a filter rather than a
 * setting: choosing this correctly needs knowledge of what a given post type
 * is for, which is knowledge a checkbox cannot ask for.
 *
 * @since 0.29.0
 */
final class ScannableTypes {

	/**
	 * The post types offered without anybody having to ask.
	 *
	 * @since 0.29.0
	 * @var string[]
	 */
	public const DEFAULTS = array( 'page', 'post' );

	/**
	 * Returns the post types this plugin scans and offers fixes for.
	 *
	 * Everything returned is a registered post type. A filter that adds a name
	 * nothing registered would otherwise hand an empty allowlist to a query,
	 * and `post_type` with an unknown value quietly matches nothing — a filter
	 * typo would look exactly like a site with no content.
	 *
	 * @since 0.29.0
	 *
	 * @return string[]
	 */
	public static function names(): array {
		/**
		 * Filters the post types the plugin scans and offers fixes for.
		 *
		 * Narrowing or widening both work. Adding a type means every screen
		 * offers it, the REST routes accept it, and the admin column appears on
		 * its list table — there is no second place to register it.
		 *
		 * @since 0.29.0
		 *
		 * @param string[] $types Post type names.
		 */
		$types = (array) apply_filters( 'wsak_post_types', self::DEFAULTS );

		$names = array();

		foreach ( $types as $type ) {
			$type = (string) $type;

			if ( '' !== $type && post_type_exists( $type ) && ! in_array( $type, $names, true ) ) {
				$names[] = $type;
			}
		}

		return $names;
	}

	/**
	 * Reports whether one post type is in scope.
	 *
	 * @since 0.29.0
	 *
	 * @param string $type Post type name.
	 * @return bool
	 */
	public static function includes( string $type ): bool {
		return in_array( $type, self::names(), true );
	}

	/**
	 * Reports whether one post is in scope.
	 *
	 * @since 0.29.0
	 *
	 * @param int $post_id The post.
	 * @return bool
	 */
	public static function covers( int $post_id ): bool {
		$type = get_post_type( $post_id );

		return false !== $type && self::includes( $type );
	}
}
