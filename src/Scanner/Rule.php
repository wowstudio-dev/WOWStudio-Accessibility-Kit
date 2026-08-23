<?php
/**
 * Rule contract.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * One accessibility check.
 *
 * Rules are deliberately small and independent so that new ones can be added,
 * and existing ones disabled or reweighted, without touching the engine.
 *
 * @since 0.3.0
 */
interface Rule {

	/**
	 * Returns the stable rule identifier.
	 *
	 * Stored on every issue, so it must not change once released.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function id(): string;

	/**
	 * Returns the WCAG success criterion this rule relates to.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string;

	/**
	 * Returns the default severity for findings from this rule.
	 *
	 * @since 0.3.0
	 *
	 * @return Severity
	 */
	public function severity(): Severity;

	/**
	 * Returns whether this rule decides an issue or only flags it for review.
	 *
	 * A rule must report Manual whenever confirming the problem needs human
	 * judgement. Overstating what automation settled is the one thing this
	 * plugin must never do.
	 *
	 * @since 0.3.0
	 *
	 * @return Detection
	 */
	public function detection(): Detection;

	/**
	 * Returns the short human-readable name of the check.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function title(): string;

	/**
	 * Returns an explanation of what fails, who it affects, and how to fix it.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function description(): string;

	/**
	 * Runs the check.
	 *
	 * @since 0.3.0
	 *
	 * @param Document $document Parsed page.
	 * @return Finding[]
	 */
	public function evaluate( Document $document ): array;
}
