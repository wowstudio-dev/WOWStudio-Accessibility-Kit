<?php
/**
 * Issue lifecycle status.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Where an issue sits in the review workflow.
 *
 * @since 0.2.0
 */
enum IssueStatus: string {

	/**
	 * Found and not yet dealt with.
	 */
	case Open = 'open';

	/**
	 * Resolved, either by an applied fix or by an edit.
	 */
	case Fixed = 'fixed';

	/**
	 * Deliberately set aside by a person, with a note explaining why.
	 */
	case Ignored = 'ignored';

	/**
	 * Returns the translated label.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function label(): string {
		return match ( $this ) {
			self::Open    => __( 'Open', 'wowstudio-accessibility-kit' ),
			self::Fixed   => __( 'Fixed', 'wowstudio-accessibility-kit' ),
			self::Ignored => __( 'Ignored', 'wowstudio-accessibility-kit' ),
		};
	}
}
