<?php
/**
 * Frames without a title.
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
 * Flags iframes with no title attribute.
 *
 * Screen readers list frames so a person can move between them. Without a
 * title, the list reads as a series of unnamed frames.
 *
 * @since 0.3.0
 */
final class IframeTitleMissing implements Rule {

	use RunsOnServer;

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'iframe-title-missing';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '4.1.2';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return Severity
	 */
	public function severity(): Severity {
		return Severity::Serious;
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
		return __( 'Frame has no title', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Screen readers let people jump between frames on a page, but an untitled frame shows up in that list with no name. Add a title attribute describing what the frame contains, such as the name of an embedded video.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.13.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// The title has to describe what is embedded, which means looking at it.
		return new FixPlan(
			FixKind::Generative,
			FixTarget::Content,
			__( 'The title has to describe what is embedded, which means looking at it. Proposed, then reviewed.', 'wowstudio-accessibility-kit' )
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

		foreach ( $document->find( '//iframe | //frame' ) as $frame ) {
			if ( ! $frame instanceof DOMElement || $document->is_hidden( $frame ) ) {
				continue;
			}

			if ( '' !== trim( $frame->getAttribute( 'title' ) ) || '' !== trim( $frame->getAttribute( 'aria-label' ) ) ) {
				continue;
			}

			$findings[] = new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				__( 'This frame has no title, so it is announced without a name.', 'wowstudio-accessibility-kit' ),
				$document->selector_for( $frame ),
				$document->context_for( $frame )
			);
		}

		return $findings;
	}
}
