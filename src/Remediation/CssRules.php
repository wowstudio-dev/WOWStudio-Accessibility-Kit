<?php
/**
 * What a stylesheet is allowed to be written for, and what it may say.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Remediation;

defined( 'ABSPATH' ) || exit;

/**
 * The allowlist governing CSS fixes.
 *
 * A CSS fix is written into the site's own stylesheet and applies everywhere
 * the selector reaches. The browser is an untrusted caller — the selector and
 * the declarations arrive over REST from a page we do not control — so nothing
 * here trusts the request to describe itself accurately. Which rule is being
 * answered, which properties that rule may set, and what shape their values may
 * take are all decided on this side.
 *
 * The list is narrow deliberately. A general-purpose "write any CSS" endpoint
 * would be a far larger thing to secure than three known repairs, and it is not
 * what anybody asked for.
 *
 * @since 0.11.0
 */
final class CssRules {

	/**
	 * Findings a stylesheet can answer, and the properties each one may set.
	 *
	 * Mirrored in the interface's own list. A test asserts the two agree,
	 * because a mismatch would either offer fixes the server refuses or accept
	 * rules the review screen never showed anyone.
	 *
	 * @since 0.11.0
	 *
	 * @return array<string, string[]>
	 */
	public static function map(): array {
		return array(
			'colour-contrast'             => array( 'color', 'background-color' ),
			'link-marked-by-colour-alone' => array( 'text-decoration' ),
			'target-too-small'            => array( 'min-width', 'min-height', 'display' ),
		);
	}

	/**
	 * Reports whether a finding can be answered with a style rule.
	 *
	 * @since 0.11.0
	 *
	 * @param string $rule_id Rule the finding names.
	 * @return bool
	 */
	public static function fixable( string $rule_id ): bool {
		return isset( self::map()[ $rule_id ] );
	}

	/**
	 * Returns the properties one rule may set.
	 *
	 * @since 0.11.0
	 *
	 * @param string $rule_id Rule the finding names.
	 * @return string[]
	 */
	public static function properties( string $rule_id ): array {
		return self::map()[ $rule_id ] ?? array();
	}

	/**
	 * Reports whether a selector is safe to write into a stylesheet.
	 *
	 * The check is a shape check, not a parse. Everything that could end the
	 * rule early, start a new one, open a comment, or pull in another resource
	 * is refused outright — a selector containing any of those is not a selector
	 * somebody typed by mistake, it is an attempt to write CSS we did not
	 * review. Ordinary selectors use none of them.
	 *
	 * @since 0.11.0
	 *
	 * @param string $selector Proposed selector.
	 * @return bool
	 */
	public static function selector_is_safe( string $selector ): bool {
		$selector = trim( $selector );

		if ( '' === $selector || strlen( $selector ) > 300 ) {
			return false;
		}

		// Braces and semicolons would close our rule; @ would start an at-rule;
		// the comment openers would swallow the markers that delimit our block.
		// `>` is absent from this list on purpose: it is the child combinator
		// and appears in perfectly ordinary selectors.
		if ( preg_match( '/[{};@<\\\\]|\/\*|\*\/|\burl\s*\(/i', $selector ) ) {
			return false;
		}

		// Control characters, including the newlines that would let a payload
		// masquerade as a separate line of the stylesheet.
		if ( preg_match( '/[\x00-\x1f\x7f]/', $selector ) ) {
			return false;
		}

		// Everything a selector is actually made of. Anything outside this is
		// refused rather than escaped, because escaping is where the mistakes
		// live.
		return 1 === preg_match( '/^[a-zA-Z0-9\s._#\-\[\]="\':(),>+~*|^$]+$/', $selector );
	}
}
