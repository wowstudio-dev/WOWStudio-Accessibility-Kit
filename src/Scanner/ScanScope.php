<?php
/**
 * Scan scope.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * What a scan covered.
 *
 * @since 0.2.0
 */
enum ScanScope: string {

	/**
	 * A single post, page, or other single URL.
	 */
	case Page = 'page';

	/**
	 * Every published item the crawler reached.
	 */
	case Site = 'site';

	/**
	 * Returns the translated label.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function label(): string {
		return match ( $this ) {
			self::Page => __( 'Single page', 'wowstudio-accessibility-kit' ),
			self::Site => __( 'Whole site', 'wowstudio-accessibility-kit' ),
		};
	}
}
