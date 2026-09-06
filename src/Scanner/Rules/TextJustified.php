<?php
/**
 * Justified text, which opens rivers of white space.
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
 * Flags text justified to both margins.
 *
 * Justifying text stretches the spaces between words so every line ends flush,
 * and the uneven gaps that result form vertical "rivers" of white space down
 * the paragraph. For readers with dyslexia those rivers are actively
 * disorienting — the eye follows them down instead of along the line, and
 * finding the start of the next line becomes work.
 *
 * Only what the markup itself declares is visible from here: an inline
 * text-align, or the old align attribute. Justification applied by a stylesheet
 * is invisible to a server-side scan, so an empty result does not mean the site
 * has none.
 *
 * @since 0.18.0
 */
final class TextJustified implements Rule {

	use RunsOnServer;


	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'text-justified';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '1.4.8';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return Severity
	 */
	public function severity(): Severity {
		return Severity::Minor;
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
		return __( 'Text is justified to both margins', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'This text is set justified, which stretches the spaces between words so each line ends flush. The uneven gaps form rivers of white space running down the paragraph, and for readers with dyslexia those are disorienting enough to make finding the next line difficult. Ranged left is easier to read for everyone and costs nothing.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return __( 'Uneven word spacing forms rivers down the paragraph, which some readers find hard to read past.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// Deterministic in principle, but declared in the content rather than measured off a rendered element.
		return new FixPlan(
			FixKind::Manual,
			FixTarget::Content,
			__( 'Setting this back to ranged left is the whole fix. It is written into the content here rather than into a stylesheet, so it is an edit in the editor.', 'wowstudio-accessibility-kit' )
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

		$expression = '//*[contains(translate(@style, "JUSTIFY", "justify"), "justify")]'
			. '|//*[translate(@align, "JUSTIFY", "justify")="justify"]';

		foreach ( $document->find( $expression ) as $element ) {
			if ( ! $element instanceof DOMElement || $document->is_hidden( $element ) ) {
				continue;
			}

			/*
			 * The style attribute may mention justify for a property that has
			 * nothing to do with text alignment — justify-content on a flex
			 * container, for one, which is both common and harmless.
			 */
			$style = strtolower( str_replace( ' ', '', $element->getAttribute( 'style' ) ) );
			$align = strtolower( trim( $element->getAttribute( 'align' ) ) );

			if ( ! str_contains( $style, 'text-align:justify' ) && 'justify' !== $align ) {
				continue;
			}

			$findings[] = new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				__( 'This text is justified to both margins, which opens uneven gaps between words.', 'wowstudio-accessibility-kit' ),
				$document->selector_for( $element ),
				$document->context_for( $element )
			);
		}

		return $findings;
	}
}
