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
	 * The theme, checked through a few representative pages.
	 *
	 * Kept apart from Site because the two answer different questions. A site
	 * scan is "these pages, on this date"; a template scan is "this theme, at
	 * this version" — and its findings belong to the theme rather than to any
	 * post, which is why filing them against four hundred posts was the wrong
	 * shape. See decision F9.
	 *
	 * @since 0.12.0
	 */
	case Template = 'template';

	/**
	 * Returns the translated label.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function label(): string {
		return match ( $this ) {
			self::Page     => __( 'Single page', 'wowstudio-accessibility-kit' ),
			self::Site     => __( 'Whole site', 'wowstudio-accessibility-kit' ),
			self::Template => __( 'Theme', 'wowstudio-accessibility-kit' ),
		};
	}
}
