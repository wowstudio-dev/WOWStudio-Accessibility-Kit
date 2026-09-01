<?php
/**
 * Images without an alt attribute.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner\Rules;

use DOMElement;
use WOWStudio\AccessibilityKit\Remediation\FixKind;
use WOWStudio\AccessibilityKit\Remediation\FixPlan;
use WOWStudio\AccessibilityKit\Remediation\FixTarget;
use WOWStudio\AccessibilityKit\Scanner\Detection;
use WOWStudio\AccessibilityKit\Scanner\Document;
use WOWStudio\AccessibilityKit\Scanner\Finding;
use WOWStudio\AccessibilityKit\Scanner\Rule;
use WOWStudio\AccessibilityKit\Scanner\RunsOnServer;
use WOWStudio\AccessibilityKit\Scanner\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * Flags images that carry no alt attribute at all.
 *
 * A missing attribute and an empty one mean different things. alt="" is a valid,
 * deliberate statement that the image is decorative and should be skipped. No
 * attribute at all leaves a screen reader to guess, and most will read the file
 * name aloud. Only the second case is reported here, which is why this can be
 * settled automatically.
 *
 * @since 0.3.0
 */
final class ImageAltMissing implements Rule {

	use RunsOnServer;

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'img-alt-missing';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '1.1.1';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return Severity
	 */
	public function severity(): Severity {
		return Severity::Critical;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return Detection
	 */
	public function detection(): Detection {
		return Detection::Auto;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Image has no alt attribute', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Someone using a screen reader gets nothing useful from this image, and often hears the file name read out instead. Add alt text describing what the image conveys, or alt="" if it is purely decorative and should be skipped.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.13.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return __( 'Anyone using a screen reader hears the file name read out instead of what the picture shows.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.13.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// A description of a photograph is not something a rule can compute — it depends on why the image is on the page.
		return new FixPlan(
			FixKind::Generative,
			FixTarget::Media,
			__( 'A description of a photograph is not something a rule can compute — it depends on why the image is on the page. A model can draft one; a person has to agree with it. Written to the media library, so it applies wherever that image is used and survives this plugin being removed.', 'wowstudio-accessibility-kit' )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @param Document $document Parsed page.
	 * @return Finding[]
	 */
	public function evaluate( Document $document ): array {
		$findings = array();

		foreach ( $document->find( '//img[not(@alt)]' ) as $image ) {
			if ( ! $image instanceof DOMElement || $document->is_hidden( $image ) ) {
				continue;
			}

			$source = $image->getAttribute( 'src' );

			$findings[] = new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				'' === $source
					? __( 'This image has no alt attribute.', 'wowstudio-accessibility-kit' )
					: sprintf(
						/* translators: %s: image file name. */
						__( 'The image "%s" has no alt attribute.', 'wowstudio-accessibility-kit' ),
						wp_basename( $source )
					),
				$document->selector_for( $image ),
				$document->context_for( $image )
			);
		}

		return $findings;
	}
}
