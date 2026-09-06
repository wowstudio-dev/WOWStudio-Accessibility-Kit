<?php
/**
 * Positive tabindex values, which reorder the whole page.
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
 * Flags `tabindex` values above zero.
 *
 * A positive tabindex does not nudge one element slightly earlier. It pulls it
 * out of the document order entirely and into a separate queue that the browser
 * visits *before* everything else on the page — so a single `tabindex="1"`
 * somewhere in a form means the first Tab press from the top of the page jumps
 * straight to it, past the skip link, the navigation and everything above.
 *
 * The result is a focus order that no longer matches what the page looks like,
 * which is precisely what 2.4.3 is about. It is nearly always a misunderstanding
 * — somebody wanting an element to be focusable and reaching for a number when
 * `tabindex="0"` was the answer.
 *
 * `tabindex="0"` and `tabindex="-1"` are both correct and common, and neither is
 * reported: zero means "focusable, in the normal order", and minus one means
 * "focusable by script but not by tabbing", which is how dialogs and skip-link
 * targets are meant to work.
 *
 * @since 0.18.0
 */
final class TabindexPositive implements Rule {

	use RunsOnServer;


	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'tabindex-positive';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '2.4.3';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return Severity
	 */
	public function severity(): Severity {
		return Severity::Serious;
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
		return __( 'Positive tabindex changes the focus order', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'This element has a tabindex above zero, which does not adjust its position slightly — it moves the element into a queue the browser visits before everything else on the page. One of these makes the first Tab press jump past your navigation to land here. Use tabindex="0" to make something focusable in its natural place, and let the order of the markup do the rest.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return __( 'Tabbing through the page jumps out of order, past everything the reader can see comes first.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// Almost always tabindex="0" was meant, but only the author can confirm.
		return new FixPlan(
			FixKind::Manual,
			FixTarget::Content,
			__( 'Almost always tabindex="0" was what was wanted — focusable, in the order it appears. Confirming that means knowing why the number was put there.', 'wowstudio-accessibility-kit' )
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

		foreach ( $document->find( '//*[@tabindex]' ) as $element ) {
			if ( ! $element instanceof DOMElement ) {
				continue;
			}

			$value = trim( $element->getAttribute( 'tabindex' ) );

			// Zero and minus one are both correct and both common. Only a
			// positive number rebuilds the page's focus order.
			if ( ! is_numeric( $value ) || (int) $value <= 0 ) {
				continue;
			}

			$findings[] = new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				sprintf(
					/* translators: %d: the tabindex value found. */
					__( 'This element has tabindex="%d", which moves it ahead of everything else in the tab order.', 'wowstudio-accessibility-kit' ),
					(int) $value
				),
				$document->selector_for( $element ),
				$document->context_for( $element )
			);
		}

		return $findings;
	}
}
