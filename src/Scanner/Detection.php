<?php
/**
 * How an issue was detected.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Whether a machine found this issue or a person still has to look.
 *
 * This is the honesty tag. Automated testing covers only part of WCAG, and the
 * UI is required to keep the two apart everywhere it shows findings, so the
 * distinction is modelled as a type rather than left as a loose string.
 *
 * @since 0.2.0
 */
enum Detection: string {

	/**
	 * The scanner determined this without human judgement.
	 */
	case Auto = 'auto';

	/**
	 * The scanner can only flag this for a person to assess.
	 */
	case Manual = 'manual';

	/**
	 * Returns the translated label.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function label(): string {
		return match ( $this ) {
			self::Auto   => __( 'Auto-detected', 'wowstudio-accessibility-kit' ),
			self::Manual => __( 'Needs manual review', 'wowstudio-accessibility-kit' ),
		};
	}
}
