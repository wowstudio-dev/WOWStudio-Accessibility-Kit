<?php
/**
 * Letting a page be zoomed after a theme said it could not.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\SiteFixes\Fixes;

use WOWStudio\AccessibilityKit\SiteFixes\RunsInBrowser;

defined( 'ABSPATH' ) || exit;

/**
 * Removes `user-scalable=no` and raises a `maximum-scale` that is too low.
 *
 * Blocking pinch-zoom takes away the way people with low vision read on a
 * phone. It is nearly always cargo-culted from an era when mobile browsers
 * zoomed erratically on form focus — a problem that has not existed for years —
 * and it now mostly penalises Android users while its author believes it does
 * nothing, because iOS has ignored `user-scalable=no` since iOS 10.
 *
 * WCAG 1.4.4 asks that text scale to 200%, so a `maximum-scale` below 2 fails on
 * the same grounds as switching zoom off altogether. Both are corrected.
 *
 * There is no server-side way to do this. The viewport tag is printed by the
 * theme directly into `wp_head`, and a plugin cannot un-print another
 * callback's output — the only ways to reach it are to know every theme's hook,
 * or to buffer and rewrite the response, which is exactly what this plugin does
 * not do.
 *
 * @since 0.20.0
 */
final class ViewportScalable implements RunsInBrowser {

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'viewport-scalable';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Let people zoom on a phone', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Removes the part of your theme\'s viewport tag that blocks pinch-zoom, or that caps it below twice the normal size. Readers with low vision rely on zooming to read at all, and this takes it away on exactly the devices where it matters most.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return string[]
	 */
	public function rule_ids(): array {
		return array( 'viewport-scaling-disabled' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return string
	 */
	public function caveat(): string {
		return __( 'The correction is made in the browser once the page has loaded, so a reader may briefly get the theme\'s original setting. This is a repair, not a substitute for correcting the viewport tag in your theme — and with JavaScript off it does nothing at all.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return void
	 */
	public function hooks(): void {
		// Carried out in the browser. The manager enqueues the one script that
		// runs every enabled fix of this kind, and tells it this one is on.
	}
}
