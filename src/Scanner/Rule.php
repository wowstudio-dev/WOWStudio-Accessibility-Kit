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
 * A Rule is specifically a check the *server pass* can run: it is handed a
 * parsed document and returns findings. Checks that need a rendered page cannot
 * satisfy that contract and are described by BrowserRule instead. Both are
 * RuleDescriptors, so both appear in the coverage panel.
 *
 * A rule must report Detection::Manual whenever confirming the problem needs
 * human judgement. Overstating what automation settled is the one thing this
 * plugin must never do.
 *
 * @since 0.3.0
 */
interface Rule extends RuleDescriptor {

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
