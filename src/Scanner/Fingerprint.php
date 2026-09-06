<?php
/**
 * What makes one finding the same finding as another.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * A stable identity for a finding, across scans and across pages.
 *
 * Until this existed a finding's identity was its row, which meant it had none:
 * every scan inserted a fresh set and nothing connected the new rows to the old
 * ones. A decision recorded against row 412 was a decision about a row, not
 * about a problem, so the next scan of the same page produced row 900 with the
 * same fault, status open, and the reasoning somebody had written down was
 * silently discarded. That is the bug this class exists to close.
 *
 * Two identities are built from it, and the difference matters:
 *
 * - **Instance** — `(post_id, fingerprint)`. This finding, on this page. What a
 *   per-page dismissal is recorded against.
 * - **Markup** — the fingerprint alone. This same bad markup, anywhere on the
 *   site. What lets one decision cover the social icons a theme prints into
 *   every footer.
 *
 * @since 0.29.0
 */
final class Fingerprint {

	/**
	 * Returns the identity of a finding.
	 *
	 * Keyed on the markup rather than the selector, deliberately. An XPath
	 * shifts the moment somebody adds a paragraph above the element, so a
	 * selector-keyed decision evaporates on the next edit — which is precisely
	 * when a person is most likely to rescan and least likely to notice that
	 * their earlier judgement has quietly vanished. The markup survives being
	 * moved; the path to it does not.
	 *
	 * Normalisation is whitespace collapse and nothing else. The temptation is
	 * to strip attributes so that "similar" elements group together, and it
	 * must be resisted: `<img src="a.jpg" alt="">` and `<img src="b.jpg" alt="">`
	 * are two different images, and merging them would let one decision retire
	 * a finding about a photograph nobody has looked at. Over-grouping here
	 * does not produce a tidier list, it produces a page that says it was
	 * reviewed when it was not.
	 *
	 * The rule id is part of the hash because the same element can fail two
	 * rules for unrelated reasons, and deciding one of them is not deciding the
	 * other.
	 *
	 * @since 0.29.0
	 *
	 * @param string $rule_id Rule that produced the finding.
	 * @param string $context The offending markup, as captured.
	 * @return string Forty hex characters.
	 */
	public static function of( string $rule_id, string $context ): string {
		$markup = trim( (string) preg_replace( '/\s+/u', ' ', $context ) );

		return sha1( $rule_id . "\n" . $markup );
	}
}
