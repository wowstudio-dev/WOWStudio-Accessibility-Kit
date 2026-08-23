<?php
/**
 * Headings that skip a level.
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
 * Flags a heading that jumps more than one level below the previous heading.
 *
 * Screen reader users navigate a page by its heading outline. A jump from a
 * second-level heading straight to a fourth suggests a missing section, and
 * makes the outline misrepresent the structure of the page.
 *
 * @since 0.3.0
 */
final class HeadingLevelSkipped implements Rule {

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'heading-level-skipped';
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
		return Severity::Moderate;
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
		return __( 'Heading level is skipped', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'People using a screen reader navigate by moving through headings, so the levels need to descend one step at a time. Jumping a level makes the page outline look like a section is missing. Change the heading level, or add the missing intermediate heading.', 'wowstudio-accessibility-kit' );
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
		$previous = 0;

		foreach ( $document->find( '//h1 | //h2 | //h3 | //h4 | //h5 | //h6' ) as $heading ) {
			if ( ! $heading instanceof DOMElement || $document->is_hidden( $heading ) ) {
				continue;
			}

			$level = (int) substr( $heading->nodeName, 1 );

			if ( 0 !== $previous && $level > $previous + 1 ) {
				$findings[] = new Finding(
					$this->id(),
					$this->wcag_sc(),
					$this->severity(),
					$this->detection(),
					sprintf(
						/* translators: 1: previous heading level, 2: this heading level. */
						__( 'This heading jumps from level %1$d to level %2$d.', 'wowstudio-accessibility-kit' ),
						$previous,
						$level
					),
					$document->selector_for( $heading ),
					$document->context_for( $heading )
				);
			}

			$previous = $level;
		}

		return $findings;
	}
}
