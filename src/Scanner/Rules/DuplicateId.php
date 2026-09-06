<?php
/**
 * The same id used more than once.
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
 * Flags ids that appear on more than one element.
 *
 * An id is supposed to be unique, and almost everything that points at an
 * element points at it by id: `<label for>`, `aria-labelledby`, `aria-controls`,
 * in-page links, and every script that calls `getElementById`. All of them stop
 * at the first match.
 *
 * So a duplicate is not a validation nicety. The second field with
 * `id="email"` cannot be labelled, because the label reaches the first one. The
 * second accordion panel cannot be controlled, because the button reaches the
 * first one. Nothing looks broken until somebody uses the part that quietly
 * stopped working.
 *
 * Usually caused by a template rendered twice — the same form in a page and in
 * a footer, a widget placed in two sidebars, a block duplicated in the editor.
 *
 * @since 0.18.0
 */
final class DuplicateId implements Rule {

	use RunsOnServer;


	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'duplicate-id';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '4.1.1';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return Severity
	 */
	public function severity(): Severity {
		return Severity::Moderate;
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
		return __( 'The same id is used more than once', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Two or more elements on this page share an id. Labels, ARIA references, in-page links and scripts all resolve an id to the first match, so everything pointing at the later ones silently reaches the wrong element instead — a second form field with the same id cannot be labelled at all. Usually this comes from a template being rendered twice on one page.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return __( 'Labels and ARIA references reach the wrong element, so the later ones are left unnamed.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// Which one should be renamed depends on what emits them.
		return new FixPlan(
			FixKind::Manual,
			FixTarget::Theme,
			__( 'Which copy should be renamed depends on what is emitting them, which is usually a template or a block rendered more than once rather than something typed into your content.', 'wowstudio-accessibility-kit' )
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
		if ( ! $document->is_full_page() ) {
			return array();
		}

		$seen     = array();
		$findings = array();

		foreach ( $document->find( '//*[@id]' ) as $element ) {
			if ( ! $element instanceof DOMElement ) {
				continue;
			}

			$id = trim( $element->getAttribute( 'id' ) );

			if ( '' === $id ) {
				continue;
			}

			if ( ! isset( $seen[ $id ] ) ) {
				$seen[ $id ] = 1;

				continue;
			}

			// One finding per duplicated id, not one per extra copy. A template
			// rendered twice can repeat forty ids, and forty findings saying
			// the same thing about the same cause is a wall, not a report.
			if ( $seen[ $id ] > 1 ) {
				continue;
			}

			++$seen[ $id ];

			$findings[] = new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				sprintf(
					/* translators: %s: the id that appears more than once. */
					__( 'The id "%s" is used on more than one element, so anything pointing at it reaches only the first.', 'wowstudio-accessibility-kit' ),
					$id
				),
				$document->selector_for( $element ),
				$document->context_for( $element )
			);
		}

		return $findings;
	}
}
