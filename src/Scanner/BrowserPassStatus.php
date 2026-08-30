<?php
/**
 * What happened to the browser pass on a given scan.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * Whether the render-dependent checks actually ran.
 *
 * This exists to make one failure impossible: reporting a page as clean when
 * the checks that would have found the problem never ran. The browser pass can
 * fail for ordinary, blameless reasons — the site refuses to be framed, the
 * scan came from cron with no browser attached — and in every one of those
 * cases the honest answer is "we did not look", not "we found nothing".
 *
 * So the outcome is stored per scan, and every surface that shows a result has
 * to be able to say which of these it was.
 *
 * @since 0.10.0
 */
enum BrowserPassStatus: string {

	/**
	 * The pass ran and its findings are included.
	 */
	case Ran = 'ran';

	/**
	 * The pass was attempted and could not complete.
	 */
	case Blocked = 'blocked';

	/**
	 * The pass was never attempted, because no browser was involved.
	 */
	case Skipped = 'skipped';

	/**
	 * Reports whether the render-dependent checks were actually performed.
	 *
	 * The single question every honesty surface asks. Only one case answers yes,
	 * and it is deliberately awkward to get that answer by accident.
	 *
	 * @since 0.10.0
	 *
	 * @return bool
	 */
	public function covered(): bool {
		return self::Ran === $this;
	}

	/**
	 * Returns the translated label.
	 *
	 * @since 0.10.0
	 *
	 * @return string
	 */
	public function label(): string {
		return match ( $this ) {
			self::Ran     => __( 'Colour and layout checks ran', 'wowstudio-accessibility-kit' ),
			self::Blocked => __( 'Colour and layout checks could not run', 'wowstudio-accessibility-kit' ),
			self::Skipped => __( 'Colour and layout checks were not attempted', 'wowstudio-accessibility-kit' ),
		};
	}

	/**
	 * Returns what this means for the result the user is looking at.
	 *
	 * @since 0.10.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return match ( $this ) {
			self::Ran     => __( 'Contrast, text size and layout were measured in a real browser on this scan.', 'wowstudio-accessibility-kit' ),
			self::Blocked => __( 'This scan could not open your page in a browser, so nothing about colour, text size or layout was checked. Those problems may be present and unreported.', 'wowstudio-accessibility-kit' ),
			self::Skipped => __( 'This scan ran on the server without a browser, so colour, text size and layout were not checked. Re-run it from the dashboard to include them.', 'wowstudio-accessibility-kit' ),
		};
	}
}
