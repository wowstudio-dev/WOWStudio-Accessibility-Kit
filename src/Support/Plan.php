<?php
/**
 * Which tier this site is on, asked in one place.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Support;

defined( 'ABSPATH' ) || exit;

/**
 * The one place that answers whether a site may use the paid features.
 *
 * Every gate in the plugin comes through here rather than calling Freemius
 * directly. Scattering `wsak_fs()->can_use_premium_code()` through the code
 * would mean each call site handling the case where Freemius is absent — a
 * plugin loaded without its SDK, a unit test, a site mid-deactivation — and the
 * one that forgets is a fatal error on somebody's dashboard.
 *
 * It also makes the boundary a thing that can be read. "What is paid for" is a
 * product decision, and product decisions should be visible somewhere other
 * than scattered through conditionals.
 *
 * The line, from decision F1: **the free tier fixes a page, the paid tier fixes
 * a site and keeps it fixed.** Everything about one page is free, including the
 * inspector and the editor panel. What is paid for is doing it to many pages at
 * once, and having that stay done.
 *
 * @since 0.14.0
 */
final class Plan {

	/**
	 * Reports whether the paid features are available.
	 *
	 * @since 0.14.0
	 *
	 * @return bool
	 */
	public function is_pro(): bool {
		$pro = function_exists( 'wsak_can_use_premium_code' ) && wsak_can_use_premium_code();

		/**
		 * Filters whether the paid features are available.
		 *
		 * Exists so a site can be tested on either side of the line without a
		 * licence, and so this plugin's own tests can exercise both. It cannot
		 * unlock anything that is not present: the paid code is stripped from
		 * the free build rather than merely switched off.
		 *
		 * @since 0.14.0
		 *
		 * @param bool $pro Whether the paid features are available.
		 */
		return (bool) apply_filters( 'wsak_is_pro', $pro );
	}

	/**
	 * Returns the refusal shown when a paid feature is asked for.
	 *
	 * Written once so the wording is the same everywhere, and written to say
	 * what the free tier still does rather than only what it does not. Somebody
	 * meeting this sentence has just pressed a button; telling them what they
	 * can do instead is more use than telling them to buy something.
	 *
	 * @since 0.14.0
	 *
	 * @param string $feature What was asked for.
	 * @return \WP_Error
	 */
	public function refuse( string $feature ): \WP_Error {
		return new \WP_Error(
			'wsak_requires_pro',
			sprintf(
				/* translators: %s: the feature that was asked for. */
				__( '%s is part of the paid version. Checking and fixing one page at a time — including the inspector, the editor panel and your theme — stays free and is not limited.', 'wowstudio-accessibility-kit' ),
				$feature
			),
			array( 'status' => 402 )
		);
	}

	/**
	 * Describes the tier for the interface.
	 *
	 * @since 0.14.0
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'pro'  => $this->is_pro(),
			// Named so the interface can say what a locked control would do,
			// rather than hiding it and leaving somebody to wonder whether the
			// feature exists.
			'paid' => array(
				'bulkScan'    => __( 'Checking many pages at once', 'wowstudio-accessibility-kit' ),
				'bulkAltText' => __( 'Describing many images at once', 'wowstudio-accessibility-kit' ),
				'uncappedAi'  => __( 'Unlimited AI suggestions', 'wowstudio-accessibility-kit' ),
			),
		);
	}
}
