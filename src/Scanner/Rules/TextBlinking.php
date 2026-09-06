<?php
/**
 * Content that blinks or scrolls on its own.
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
 * Flags `<blink>` and `<marquee>`.
 *
 * Both move without being asked and offer no way to stop, which is exactly what
 * 2.2.2 forbids. Movement in the corner of the eye is difficult to read past
 * for anybody with an attention or reading difficulty, and flashing content is
 * a seizure risk at the wrong rate.
 *
 * Neither element has been in the HTML specification for years and no current
 * browser renders `<blink>` at all — which is why this survives mainly in
 * imported content and very old templates, where nobody has noticed it because
 * on their own machine it does nothing.
 *
 * @since 0.18.0
 */
final class TextBlinking implements Rule {

	use RunsOnServer;


	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'text-blinking';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '2.2.2';
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
		return __( 'Content blinks or scrolls on its own', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'This page uses a blink or marquee element. Both move without being asked and give the reader no way to stop them, which makes surrounding text hard to read for anybody with an attention or reading difficulty, and can be a seizure risk. Neither element is part of HTML any more. Replace it with static text, or with something the reader can pause.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return __( 'Text moves or flashes with no way to stop it, which some readers cannot read past and others cannot safely look at.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// What should replace the movement is an editorial decision.
		return new FixPlan(
			FixKind::Manual,
			FixTarget::Content,
			__( 'Whether the content should simply stop moving, or become something the reader can pause, is an editorial decision. Both are edits where the content lives.', 'wowstudio-accessibility-kit' )
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

		foreach ( $document->find( '//blink|//marquee' ) as $element ) {
			if ( ! $element instanceof DOMElement ) {
				continue;
			}

			$findings[] = new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				sprintf(
					/* translators: %s: the element name, blink or marquee. */
					__( 'This page uses a %s element, which moves on its own and cannot be stopped.', 'wowstudio-accessibility-kit' ),
					strtolower( $element->nodeName )
				),
				$document->selector_for( $element ),
				$document->context_for( $element )
			);
		}

		return $findings;
	}
}
