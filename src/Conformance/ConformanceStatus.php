<?php
/**
 * Self-assessed conformance status.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Conformance;

defined( 'ABSPATH' ) || exit;

/**
 * What the site owner says about their own site.
 *
 * These are the three states the W3C statement template uses, and an
 * accessibility statement is not much use without one. Every one of them is the
 * site owner's assertion, made under their own name through the attestation,
 * and never the plugin's: nothing in this plugin can verify any of them, and
 * the generated statement says so in its own words.
 *
 * @since 0.7.0
 */
enum ConformanceStatus: string {

	/**
	 * Some parts do not yet meet the target.
	 *
	 * The honest answer for almost every real site, and the default.
	 */
	case Partial = 'partial';

	/**
	 * The owner believes the whole site meets the target.
	 */
	case Full = 'full';

	/**
	 * The target is not met, or has not been assessed.
	 */
	case None = 'none';

	/**
	 * Returns the translated label for a settings screen.
	 *
	 * @since 0.7.0
	 *
	 * @return string
	 */
	public function label(): string {
		return match ( $this ) {
			self::Partial => __( 'Partially conformant — some parts do not yet meet the target', 'wowstudio-accessibility-kit' ),
			self::Full    => __( 'Fully conformant — you believe the whole site meets the target', 'wowstudio-accessibility-kit' ),
			self::None    => __( 'Not conformant — the target is not met, or has not been assessed', 'wowstudio-accessibility-kit' ),
		};
	}

	/**
	 * Returns the sentence used in the statement itself.
	 *
	 * Phrased throughout as what the organisation says, not as fact. The
	 * statement is their assertion; this plugin is only the typewriter.
	 *
	 * @since 0.7.0
	 *
	 * @param string $organisation Organisation name.
	 * @param string $standard     Target standard, already translated.
	 * @return string
	 */
	public function sentence( string $organisation, string $standard ): string {
		switch ( $this ) {
			case self::Full:
				return sprintf(
					/* translators: 1: organisation name, 2: target standard. */
					__( '%1$s considers this website to be fully conformant with %2$s. Fully conformant means, in the words of the standard, that the content fully meets the accessibility standard without any exceptions.', 'wowstudio-accessibility-kit' ),
					$organisation,
					$standard
				);

			case self::None:
				return sprintf(
					/* translators: 1: organisation name, 2: target standard. */
					__( '%1$s considers this website to be non-conformant with %2$s. Non-conformant means that the content does not meet the accessibility standard, or has not yet been assessed against it.', 'wowstudio-accessibility-kit' ),
					$organisation,
					$standard
				);

			default:
				return sprintf(
					/* translators: 1: organisation name, 2: target standard. */
					__( '%1$s considers this website to be partially conformant with %2$s. Partially conformant means that some parts of the content do not yet fully meet the accessibility standard.', 'wowstudio-accessibility-kit' ),
					$organisation,
					$standard
				);
		}
	}
}
