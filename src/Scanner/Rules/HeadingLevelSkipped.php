<?php
/**
 * Headings that skip a level.
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
 * Flags a heading that jumps more than one level below the previous heading.
 *
 * Screen reader users navigate a page by its heading outline. A jump from a
 * second-level heading straight to a fourth suggests a missing section, and
 * makes the outline misrepresent the structure of the page.
 *
 * @since 0.3.0
 */
final class HeadingLevelSkipped implements Rule {

	use RunsOnServer;

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
	 * @since 0.13.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return __( 'Most screen reader users move around a page by jumping between headings. This one leaves a gap in that path.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.13.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// Renumbering the heading is one possible fix; the other is that a section is genuinely missing and should be written.
		return new FixPlan(
			FixKind::Manual,
			FixTarget::Content,
			__( 'Renumbering the heading is one possible fix; the other is that a section is genuinely missing and should be written. Choosing between those is a judgement about the document, not a correction to it.', 'wowstudio-accessibility-kit' )
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

		/*
		 * On a whole page the first heading has nothing before it, so nothing to
		 * skip from. On a content-only scan there *is* something before it — the
		 * theme's own heading — and starting from zero means the commonest real
		 * skip of all, a post opening at h3 under the theme's h1, goes
		 * unreported. Zero when the theme has not been looked at, which restores
		 * exactly the old behaviour rather than guessing.
		 */
		$seed     = $document->is_full_page() ? 0 : $document->profile()->heading_context();
		$previous = $seed;
		$first    = true;

		foreach ( $document->find( '//h1 | //h2 | //h3 | //h4 | //h5 | //h6' ) as $heading ) {
			if ( ! $heading instanceof DOMElement || $document->is_hidden( $heading ) ) {
				continue;
			}

			$level = (int) substr( $heading->nodeName, 1 );

			if ( 0 !== $previous && $level > $previous + 1 ) {
				/*
				 * On the first heading there is no preceding one in this
				 * content: the level being compared against came from the
				 * profile, which is a measurement of a sample of the theme
				 * rather than of this page. A wrong profile would otherwise
				 * assert a gap that is not there — see the note in
				 * MultipleTopHeadings, and decision F9.
				 */
				$inferred = $first && $seed > 0;

				$findings[] = new Finding(
					$this->id(),
					$this->wcag_sc(),
					$this->severity(),
					$inferred ? Detection::Manual : $this->detection(),
					$inferred
						? sprintf(
							/* translators: 1: heading level the theme appears to establish, 2: this heading level. */
							__( 'Your theme appears to put a level %1$d heading above your content, which would make this level %2$d a jump. Worth checking on the page itself.', 'wowstudio-accessibility-kit' ),
							$previous,
							$level
						)
						: sprintf(
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
			$first    = false;
		}

		return $findings;
	}
}
