<?php
/**
 * How much of a page a scan actually looked at.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

use WOWStudio\AccessibilityKit\Db\Scan;

defined( 'ABSPATH' ) || exit;

/**
 * The badge on a piece of content, and the reason it has two useful states.
 *
 * A bulk run reads post content and nothing else, because the checks that need
 * a rendered page cannot run in a background job. A badge reading "Scanned ✓"
 * after such a run would be a lie by omission: somebody bulk-scans a hundred
 * pages, sees no contrast findings, and reasonably concludes they have none.
 *
 * So the badge says what was looked at rather than that looking happened. See
 * decision F4.
 *
 * @since 0.13.0
 */
enum ScanCoverage: string {

	/**
	 * Nobody has checked this yet.
	 *
	 * @since 0.13.0
	 */
	case Never = 'never';

	/**
	 * The markup pass ran. Colour, size and layout were not looked at.
	 *
	 * @since 0.13.0
	 */
	case Content = 'content';

	/**
	 * Somebody opened this in the inspector, so the browser pass ran too.
	 *
	 * @since 0.13.0
	 */
	case Full = 'full';

	/**
	 * Works out the coverage a stored scan represents.
	 *
	 * @since 0.13.0
	 *
	 * @param Scan|null $scan Most recent finished scan, if any.
	 * @return self
	 */
	public static function of( ?Scan $scan ): self {
		if ( null === $scan || ScanStatus::Complete !== $scan->status ) {
			return self::Never;
		}

		return BrowserPassStatus::Ran === $scan->browser_pass ? self::Full : self::Content;
	}

	/**
	 * Returns the badge text.
	 *
	 * @since 0.13.0
	 *
	 * @return string
	 */
	public function label(): string {
		return match ( $this ) {
			self::Never   => __( 'Not checked', 'wowstudio-accessibility-kit' ),
			self::Content => __( 'Content checked', 'wowstudio-accessibility-kit' ),
			self::Full    => __( 'Fully checked', 'wowstudio-accessibility-kit' ),
		};
	}

	/**
	 * Returns what the badge means, in a sentence.
	 *
	 * @since 0.13.0
	 *
	 * @return string
	 */
	public function blurb(): string {
		return match ( $this ) {
			self::Never   => __( 'Nothing has looked at this page yet.', 'wowstudio-accessibility-kit' ),
			self::Content => __( 'The words and markup of this page were checked. Colour, text size and layout were not — those need the page open in a browser, which a background run cannot do. Open it in the inspector to check the rest.', 'wowstudio-accessibility-kit' ),
			self::Full    => __( 'Both passes ran: the markup, and the page as a browser actually draws it.', 'wowstudio-accessibility-kit' ),
		};
	}

	/**
	 * Whether everything this plugin can check has been checked.
	 *
	 * @since 0.13.0
	 *
	 * @return bool
	 */
	public function is_complete(): bool {
		return self::Full === $this;
	}
}
