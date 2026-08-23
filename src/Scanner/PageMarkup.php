<?php
/**
 * Markup handed to the scanner.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * The markup to scan, and where it came from.
 *
 * Where it came from is not a detail. A whole page fetched over loopback can be
 * checked for things like the page language and landmarks; post content alone
 * cannot, because the theme supplies them. Carrying the origin alongside the
 * markup is what lets the UI say which of the two happened instead of quietly
 * reporting reduced coverage as a clean result.
 *
 * @since 0.3.0
 */
final class PageMarkup {

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param string $html          Markup to scan.
	 * @param bool   $from_loopback Whether the whole rendered page was fetched.
	 * @param string $notice        Reader-facing explanation when it was not.
	 */
	public function __construct(
		public readonly string $html,
		public readonly bool $from_loopback,
		public readonly string $notice = ''
	) {}
}
