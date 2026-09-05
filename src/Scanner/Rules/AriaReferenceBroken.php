<?php
/**
 * ARIA attributes pointing at ids that do not exist.
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
 * Flags `aria-labelledby` and friends whose target id is not on the page.
 *
 * These attributes work by pointing: `aria-labelledby="billing-heading"` means
 * "my name is whatever that element says". When the target is missing the
 * pointer resolves to nothing, and the failure is silent and total — the
 * element ends up with no accessible name at all, and in most browsers the
 * broken reference also *suppresses* whatever name it would otherwise have had
 * from its own text. Adding a broken `aria-labelledby` can therefore take a
 * working control and leave it anonymous.
 *
 * That is what makes this worth checking rather than shrugging at. It looks
 * like an accessibility improvement in the markup, it validates, and it makes
 * things worse.
 *
 * Only runs against a full page, for the same reason the broken-anchor check
 * does: the target may simply be in the part of the document a fragment scan
 * cannot see.
 *
 * @since 0.17.0
 */
final class AriaReferenceBroken implements Rule {

	use RunsOnServer;

	/**
	 * Attributes whose value is a space-separated list of element ids.
	 *
	 * @since 0.17.0
	 * @var string[]
	 */
	private const ID_REFERENCE_ATTRIBUTES = array(
		'aria-labelledby',
		'aria-describedby',
		'aria-controls',
		'aria-owns',
		'aria-details',
		'aria-errormessage',
		'aria-flowto',
	);

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'aria-reference-broken';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '4.1.2';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return Severity
	 */
	public function severity(): Severity {
		return Severity::Serious;
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
		return __( 'ARIA attribute points at an element that does not exist', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'This element names another element by id, and nothing on the page has that id. The reference resolves to nothing, and in most browsers a broken aria-labelledby also suppresses the name the element would otherwise have had — so the markup looks like an accessibility improvement while leaving the control anonymous. Correct the id, or remove the attribute.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return __( 'The element loses the name it was meant to have, and often the one it already had.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// Only the author knows which element was meant.
		return new FixPlan(
			FixKind::Manual,
			FixTarget::Theme,
			__( 'Which element was meant to be pointed at is something only whoever wrote the markup knows. These attributes are usually emitted by a theme or a plugin template rather than typed into your content.', 'wowstudio-accessibility-kit' )
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

		foreach ( self::ID_REFERENCE_ATTRIBUTES as $attribute ) {
			foreach ( $document->find( sprintf( '//*[@%s]', $attribute ) ) as $element ) {
				if ( ! $element instanceof DOMElement ) {
					continue;
				}

				$missing = $this->missing_targets( $document, $element->getAttribute( $attribute ) );

				if ( array() === $missing ) {
					continue;
				}

				$findings[] = new Finding(
					$this->id(),
					$this->wcag_sc(),
					$this->severity(),
					$this->detection(),
					sprintf(
						/* translators: 1: the ARIA attribute name. 2: the ids it points at that do not exist, comma separated. */
						__( 'This element\'s %1$s points at %2$s, and nothing on the page has that id.', 'wowstudio-accessibility-kit' ),
						$attribute,
						implode( ', ', $missing )
					),
					$document->selector_for( $element ),
					$document->context_for( $element )
				);
			}
		}

		return $findings;
	}

	/**
	 * Returns the ids in a reference list that nothing answers to.
	 *
	 * @since 0.17.0
	 *
	 * @param Document $document Parsed page.
	 * @param string   $value    The attribute value.
	 * @return string[]
	 */
	private function missing_targets( Document $document, string $value ): array {
		$ids = preg_split( '/\s+/', trim( $value ), -1, PREG_SPLIT_NO_EMPTY );

		if ( ! is_array( $ids ) ) {
			return array();
		}

		$missing = array();

		foreach ( $ids as $id ) {
			if ( null === $document->first( sprintf( '//*[@id=%s]', Document::quote( $id ) ) ) ) {
				$missing[] = $id;
			}
		}

		return $missing;
	}
}
