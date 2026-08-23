<?php
/**
 * Issue severity.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * How much a given issue hurts the people it affects.
 *
 * @since 0.2.0
 */
enum Severity: string {

	/**
	 * Blocks a person from completing the task at all.
	 */
	case Critical = 'critical';

	/**
	 * Makes a task substantially harder, with no reasonable workaround.
	 */
	case Serious = 'serious';

	/**
	 * Causes real friction, but a workaround exists.
	 */
	case Moderate = 'moderate';

	/**
	 * Worth fixing, but rarely blocks anyone.
	 */
	case Minor = 'minor';

	/**
	 * Returns the translated label.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function label(): string {
		return match ( $this ) {
			self::Critical => __( 'Critical', 'wowstudio-accessibility-kit' ),
			self::Serious  => __( 'Serious', 'wowstudio-accessibility-kit' ),
			self::Moderate => __( 'Moderate', 'wowstudio-accessibility-kit' ),
			self::Minor    => __( 'Minor', 'wowstudio-accessibility-kit' ),
		};
	}

	/**
	 * Returns the sort weight, highest severity first.
	 *
	 * @since 0.2.0
	 *
	 * @return int
	 */
	public function weight(): int {
		return match ( $this ) {
			self::Critical => 4,
			self::Serious  => 3,
			self::Moderate => 2,
			self::Minor    => 1,
		};
	}
}
