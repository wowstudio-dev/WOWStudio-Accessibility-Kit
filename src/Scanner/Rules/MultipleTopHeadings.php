<?php
/**
 * More than one top-level heading.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner\Rules;

use DOMElement;
use WOWStudio\AccessibilityKit\Scanner\Detection;
use WOWStudio\AccessibilityKit\Scanner\Document;
use WOWStudio\AccessibilityKit\Scanner\Finding;
use WOWStudio\AccessibilityKit\Scanner\Rule;
use WOWStudio\AccessibilityKit\Scanner\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * Flags a page carrying more than one first-level heading.
 *
 * Reported as needing review. HTML5 sectioning permits several h1 elements, and
 * some designs use them deliberately, but in practice a second h1 is usually an
 * accident that leaves the page with two competing titles.
 *
 * @since 0.3.0
 */
final class MultipleTopHeadings implements Rule {

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'heading-multiple-h1';
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
		return Severity::Minor;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return Detection
	 */
	public function detection(): Detection {
		return Detection::Manual;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Page has more than one top-level heading', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'A page normally has one h1 naming what the page is about. More than one gives it competing titles and muddles the outline people navigate by. Check whether the extra headings should be h2 instead.', 'wowstudio-accessibility-kit' );
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
		$seen     = 0;

		foreach ( $document->find( '//h1' ) as $heading ) {
			if ( ! $heading instanceof DOMElement || $document->is_hidden( $heading ) ) {
				continue;
			}

			++$seen;

			if ( $seen < 2 ) {
				continue;
			}

			$findings[] = new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				sprintf(
					/* translators: %d: position of this top-level heading on the page. */
					__( 'This is top-level heading number %d on the page.', 'wowstudio-accessibility-kit' ),
					$seen
				),
				$document->selector_for( $heading ),
				$document->context_for( $heading )
			);
		}

		return $findings;
	}
}
