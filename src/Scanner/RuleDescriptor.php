<?php
/**
 * What every check can say about itself.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * The metadata half of a check, separated from the ability to run one.
 *
 * This exists because the browser pass has checks that PHP cannot execute. They
 * still have to appear in the coverage panel — the honesty rule is that we list
 * every check and say what it can decide, and a check omitted because it runs
 * somewhere else is exactly the kind of quiet gap that makes a coverage list
 * worthless. They also have to be addressable by ID, so that findings arriving
 * from the browser can be validated against checks we actually recognise.
 *
 * So a descriptor is everything a check knows about itself. `Rule` extends it
 * with the one thing only a server-side check has: the ability to evaluate a
 * parsed document.
 *
 * @since 0.10.0
 */
interface RuleDescriptor {

	/**
	 * Returns the stable rule identifier.
	 *
	 * Stored on every issue, so it must not change once released.
	 *
	 * @since 0.10.0
	 *
	 * @return string
	 */
	public function id(): string;

	/**
	 * Returns the WCAG success criterion this rule relates to.
	 *
	 * @since 0.10.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string;

	/**
	 * Returns the default severity for findings from this rule.
	 *
	 * @since 0.10.0
	 *
	 * @return Severity
	 */
	public function severity(): Severity;

	/**
	 * Returns whether this rule decides an issue or only flags it for review.
	 *
	 * @since 0.10.0
	 *
	 * @return Detection
	 */
	public function detection(): Detection;

	/**
	 * Returns which pass performs this check.
	 *
	 * @since 0.10.0
	 *
	 * @return ScanPass
	 */
	public function pass(): ScanPass;

	/**
	 * Returns the short human-readable name of the check.
	 *
	 * @since 0.10.0
	 *
	 * @return string
	 */
	public function title(): string;

	/**
	 * Returns an explanation of what fails, who it affects, and how to fix it.
	 *
	 * @since 0.10.0
	 *
	 * @return string
	 */
	public function description(): string;
}
