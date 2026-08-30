<?php
/**
 * Which pass found an issue.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * The two engines that can produce a finding.
 *
 * The server pass parses fetched HTML with DOMDocument. It is the spine: it
 * needs no browser, so it queues, runs from WP-CLI, and scans a whole site
 * unattended.
 *
 * The browser pass runs in the admin against the rendered page and can read
 * computed style, which is the only way to reach colour contrast and the other
 * criteria that depend on how a page actually painted. It exists only while a
 * browser holds the page open.
 *
 * Findings are tagged with the pass that produced them because the two have
 * genuinely different reliability and availability, and a user deciding how
 * much to trust a clean result is entitled to know which of them ran.
 *
 * @since 0.10.0
 */
enum ScanPass: string {

	/**
	 * Found by server-side HTML analysis.
	 */
	case Server = 'server';

	/**
	 * Found in a real browser, with computed style available.
	 */
	case Browser = 'browser';

	/**
	 * Returns the translated label.
	 *
	 * @since 0.10.0
	 *
	 * @return string
	 */
	public function label(): string {
		return match ( $this ) {
			self::Server  => __( 'Found in the markup', 'wowstudio-accessibility-kit' ),
			self::Browser => __( 'Found in the rendered page', 'wowstudio-accessibility-kit' ),
		};
	}

	/**
	 * Returns a plain-language account of what this pass can see.
	 *
	 * @since 0.10.0
	 *
	 * @return string
	 */
	public function description(): string {
		return match ( $this ) {
			self::Server  => __( 'Read from the page source on your server. Needs no browser, so it can run on a schedule and across your whole site.', 'wowstudio-accessibility-kit' ),
			self::Browser => __( 'Measured in a real browser, where colours, sizes and layout are known. Only runs while you have the dashboard open.', 'wowstudio-accessibility-kit' ),
		};
	}
}
