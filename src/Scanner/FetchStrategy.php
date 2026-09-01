<?php
/**
 * How a scan gets the markup it reads.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * The ways markup reaches the scanner, and what each one can see.
 *
 * These are not interchangeable and the difference is not a detail: they see
 * different amounts of the page, so a scan's coverage is a property of the
 * strategy that produced it. Keeping them named rather than implied is what
 * lets a badge say "content checked" instead of "checked".
 *
 * @since 0.12.0
 */
enum FetchStrategy: string {

	/**
	 * The whole page, fetched over a loopback request as a visitor would get it.
	 *
	 * Everything the theme contributes is visible: the language attribute, the
	 * title, landmarks, navigation. Costs an HTTP round trip and a second
	 * WordPress render, needs the host to permit loopback, and sees only what a
	 * logged-out visitor sees.
	 *
	 * @since 0.12.0
	 */
	case Loopback = 'loopback';

	/**
	 * The post's own content, rendered in process.
	 *
	 * No HTTP at all, which is why it works on every host, inside cron, and on
	 * drafts and private posts — none of which loopback manages. It sees nothing
	 * the theme adds, so the page-level rules abstain and the coverage notice
	 * says so.
	 *
	 * @since 0.12.0
	 */
	case Content = 'content';

	/**
	 * Returns the translated label.
	 *
	 * @since 0.12.0
	 *
	 * @return string
	 */
	public function label(): string {
		return match ( $this ) {
			self::Loopback => __( 'Whole page', 'wowstudio-accessibility-kit' ),
			self::Content  => __( 'Content only', 'wowstudio-accessibility-kit' ),
		};
	}
}
