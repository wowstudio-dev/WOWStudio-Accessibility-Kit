<?php
/**
 * Fix lifecycle status.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Remediation;

defined( 'ABSPATH' ) || exit;

/**
 * Whether an override is in force.
 *
 * @since 0.6.0
 */
enum FixStatus: string {

	/**
	 * In force, rewriting the page as it renders.
	 */
	case Applied = 'applied';

	/**
	 * Undone. Kept as a record rather than deleted, so the history of what was
	 * changed and unchanged survives.
	 */
	case Reverted = 'reverted';

	/**
	 * Returns the translated label.
	 *
	 * @since 0.6.0
	 *
	 * @return string
	 */
	public function label(): string {
		return match ( $this ) {
			self::Applied  => __( 'Applied', 'wowstudio-accessibility-kit' ),
			self::Reverted => __( 'Undone', 'wowstudio-accessibility-kit' ),
		};
	}
}
