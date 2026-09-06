<?php
/**
 * Image buttons with no text alternative.
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
 * Flags `<input type="image">` with no alt text.
 *
 * This is a submit button that happens to be drawn as a picture, and like an
 * image-map area it has nowhere to put visible text — `alt` is the only name it
 * can have. Without one, a screen reader announces "button" and stops, or reads
 * out the image file name, and the reader has to guess whether pressing it
 * searches, submits, or deletes something.
 *
 * It is worse than an undescribed decorative image for an obvious reason: this
 * one *does* something.
 *
 * @since 0.18.0
 */
final class InputImageAltMissing implements Rule {

	use RunsOnServer;


	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'input-image-alt-missing';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '1.1.1';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return Severity
	 */
	public function severity(): Severity {
		return Severity::Critical;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return Detection
	 */
	public function detection(): Detection {
		return Detection::Auto;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Image button has no alt text', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'This is a submit button drawn as an image, and it has no alt attribute. There is nowhere else to put its name, so a screen reader announces it as an unnamed button or reads out the file name. Anybody who cannot see the picture has to press it to find out what it does. Add an alt attribute saying what the button does — "Search", not "magnifying glass".', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return __( 'A button that submits something is announced without a name, so its purpose can only be discovered by pressing it.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// What the button does is not something the markup reveals.
		return new FixPlan(
			FixKind::Manual,
			FixTarget::Content,
			__( 'The alt should say what the button does rather than what the picture is — "Search" rather than "magnifying glass". Only you know which.', 'wowstudio-accessibility-kit' )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @param Document $document Parsed page.
	 * @return Finding[]
	 */
	public function evaluate( Document $document ): array {
		$findings = array();

		foreach ( $document->find( '//input[@type="image"]' ) as $input ) {
			if ( ! $input instanceof DOMElement || $document->is_hidden( $input ) ) {
				continue;
			}

			// accessible_name() covers aria-label and aria-labelledby, both of
			// which name the button perfectly well even though alt is the
			// conventional way to do it here.
			if ( '' !== trim( $document->accessible_name( $input ) ) ) {
				continue;
			}

			if ( '' !== trim( $input->getAttribute( 'alt' ) ) ) {
				continue;
			}

			$findings[] = new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				__( 'This image button has no alt text, so it is announced without a name.', 'wowstudio-accessibility-kit' ),
				$document->selector_for( $input ),
				$document->context_for( $input )
			);
		}

		return $findings;
	}
}
