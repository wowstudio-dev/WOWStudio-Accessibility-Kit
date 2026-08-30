<?php
/**
 * Form controls with no label.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner\Rules;

use DOMElement;
use WOWStudio\AccessibilityKit\Scanner\Detection;
use WOWStudio\AccessibilityKit\Scanner\Document;
use WOWStudio\AccessibilityKit\Scanner\Finding;
use WOWStudio\AccessibilityKit\Scanner\Rule;
use WOWStudio\AccessibilityKit\Scanner\RunsOnServer;
use WOWStudio\AccessibilityKit\Scanner\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * Flags form fields that are not labelled.
 *
 * Placeholder text is not a label: it disappears as soon as someone types, and
 * many screen readers do not announce it. A field with no label leaves the
 * person filling in the form guessing what belongs in it.
 *
 * @since 0.3.0
 */
final class FormControlLabelMissing implements Rule {

	use RunsOnServer;

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'form-control-label-missing';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '1.3.1';
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
		return __( 'Form field has no label', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'This field is not labelled, so someone using a screen reader cannot tell what to type into it. Add a <label for> pointing at the field, or an aria-label. Placeholder text does not count.', 'wowstudio-accessibility-kit' );
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
		$skipped  = array( 'hidden', 'submit', 'button', 'reset', 'image' );

		foreach ( $document->find( '//input | //select | //textarea' ) as $control ) {
			if ( ! $control instanceof DOMElement || $document->is_hidden( $control ) ) {
				continue;
			}

			if ( in_array( strtolower( $control->getAttribute( 'type' ) ), $skipped, true ) ) {
				continue;
			}

			// aria-label, aria-labelledby, or title.
			if ( '' !== $document->accessible_name( $control ) ) {
				continue;
			}

			$id = trim( $control->getAttribute( 'id' ) );

			if ( '' !== $id && null !== $document->first( sprintf( '//label[@for=%s]', Document::quote( $id ) ) ) ) {
				continue;
			}

			// A label wrapping the control is equally valid.
			if ( $document->find( 'ancestor::label', $control )->length > 0 ) {
				continue;
			}

			$findings[] = new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				sprintf(
					/* translators: %s: form field name attribute, or the element name. */
					__( 'The form field "%s" has no label.', 'wowstudio-accessibility-kit' ),
					'' !== trim( $control->getAttribute( 'name' ) ) ? $control->getAttribute( 'name' ) : $control->nodeName
				),
				$document->selector_for( $control ),
				$document->context_for( $control )
			);
		}

		return $findings;
	}
}
