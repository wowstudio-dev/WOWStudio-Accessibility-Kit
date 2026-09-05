<?php
/**
 * Labels attached to nothing, and controls wearing two of them.
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
 * Flags `<label for>` that points at nothing, and controls with two labels.
 *
 * Both are the same mistake seen from opposite ends, and both are invisible on
 * screen — the label sits there looking correctly attached, and only somebody
 * relying on the association finds out it is not.
 *
 * A label pointing at a missing id labels nothing. The visible text is still
 * there, so sighted users read it and fill the field in; a screen reader
 * announces the field as "edit, blank", and clicking the label does not focus
 * the field, which is the small affordance that makes checkboxes usable for
 * anybody with imprecise pointing.
 *
 * Two labels for one control is the other half. The specification allows it,
 * but the result is inconsistent across browsers and screen readers — some
 * concatenate, some take the first, some take the last — so the name a user
 * hears depends on their software. It usually happens when a plugin adds a
 * label to a field a theme already labelled.
 *
 * Only runs against a full page: a fragment scan may simply not contain the
 * input the label is pointing at.
 *
 * @since 0.17.0
 */
final class FormLabelOrphaned implements Rule {

	use RunsOnServer;

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'form-label-orphaned';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '1.3.1';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return Severity
	 */
	public function severity(): Severity {
		return Severity::Moderate;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return Detection
	 */
	public function detection(): Detection {
		return Detection::Auto;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Form label is attached to the wrong thing', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'A label here either points at a field that does not exist, or a single field has been given two labels. Both look correct on screen and neither is: an unattached label leaves the field announced as blank and stops clicking the label from focusing it, and two labels produce a name that differs between browsers and screen readers. Point the label\'s for attribute at the field\'s id, and use exactly one.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return __( 'The field is announced without its name, or with a different name depending on the software used.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// Which label is the right one is a question about the form.
		return new FixPlan(
			FixKind::Manual,
			FixTarget::Content,
			__( 'Which field a label belongs to, and which of two labels is the right one, are questions about the form rather than the markup. Forms are usually built by a plugin, so the fix is normally in that plugin\'s form editor.', 'wowstudio-accessibility-kit' )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @param Document $document Parsed page.
	 * @return Finding[]
	 */
	public function evaluate( Document $document ): array {
		if ( ! $document->is_full_page() ) {
			return array();
		}

		$findings = array();
		$seen     = array();

		foreach ( $document->find( '//label[@for]' ) as $label ) {
			if ( ! $label instanceof DOMElement ) {
				continue;
			}

			$for = trim( $label->getAttribute( 'for' ) );

			if ( '' === $for ) {
				continue;
			}

			$target = $document->first( sprintf( '//*[@id=%s]', Document::quote( $for ) ) );
			$text   = trim( $document->text_of( $label ) );

			if ( null === $target ) {
				$findings[] = new Finding(
					$this->id(),
					$this->wcag_sc(),
					$this->severity(),
					$this->detection(),
					'' === $text
						? sprintf(
							/* translators: %s: the id the label points at. */
							__( 'A label points at the id "%s", and no field on this page has it.', 'wowstudio-accessibility-kit' ),
							$for
						)
						: sprintf(
							/* translators: 1: the label text. 2: the id the label points at. */
							__( 'The label "%1$s" points at the id "%2$s", and no field on this page has it.', 'wowstudio-accessibility-kit' ),
							$text,
							$for
						),
					$document->selector_for( $label ),
					$document->context_for( $label )
				);

				continue;
			}

			// Second and subsequent labels for the same control. Reported on
			// the later one, because the first is usually the intended label
			// and the later one is what somebody added.
			if ( isset( $seen[ $for ] ) ) {
				$findings[] = new Finding(
					$this->id(),
					$this->wcag_sc(),
					$this->severity(),
					$this->detection(),
					sprintf(
						/* translators: %s: the id shared by both labels. */
						__( 'The field "%s" has more than one label, so which name a reader hears depends on their browser.', 'wowstudio-accessibility-kit' ),
						$for
					),
					$document->selector_for( $label ),
					$document->context_for( $label )
				);
			}

			$seen[ $for ] = true;
		}

		return $findings;
	}
}
