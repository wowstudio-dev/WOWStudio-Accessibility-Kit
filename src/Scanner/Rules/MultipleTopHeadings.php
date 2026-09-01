<?php
/**
 * More than one top-level heading.
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
 * Flags a page carrying more than one first-level heading.
 *
 * Reported as needing review. HTML5 sectioning permits several h1 elements, and
 * some designs use them deliberately, but in practice a second h1 is usually an
 * accident that leaves the page with two competing titles.
 *
 * @since 0.3.0
 */
final class MultipleTopHeadings implements Rule {

	use RunsOnServer;

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
	 * @since 0.13.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return __( 'The page says it is about two different things at once, which makes its outline unreliable for anyone navigating by it.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.13.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// Which of the two should stop being a top-level heading depends on which one the page is actually about.
		return new FixPlan(
			FixKind::Manual,
			FixTarget::Content,
			__( 'Which of the two should stop being a top-level heading depends on which one the page is actually about.', 'wowstudio-accessibility-kit' )
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
		 * A content-only scan sees the post's headings and not the theme's. If
		 * the theme already printed the title as an h1, the first h1 in the
		 * content is the second on the page — and reporting from the second
		 * onwards means it would otherwise be missed entirely.
		 */
		$from_theme = $document->is_full_page() ? 0 : $document->profile()->top_headings_before_content();
		$seen       = $from_theme;

		foreach ( $document->find( '//h1' ) as $heading ) {
			if ( ! $heading instanceof DOMElement || $document->is_hidden( $heading ) ) {
				continue;
			}

			++$seen;

			if ( $seen < 2 ) {
				continue;
			}

			/*
			 * Whether this finding exists on its own evidence, or only because
			 * the profile said the theme contributes a heading we cannot see.
			 *
			 * The distinction matters more than it looks. A profile is measured
			 * from a sample of one page, and anything that made that page
			 * unrepresentative — a caching plugin, a maintenance screen, a
			 * personalised header — bakes a wrong number in. When that happens
			 * this rule invents a finding about a page whose headings are
			 * perfectly correct, which is the one failure decision F9 said must
			 * never happen: under-reporting is recoverable, inventing is not.
			 *
			 * So a finding the content alone would not have produced is offered
			 * for a person to confirm rather than asserted.
			 */
			$inferred = ( $seen - $from_theme ) < 2;

			$findings[] = new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$inferred ? Detection::Manual : $this->detection(),
				$inferred
					? __( 'Your theme appears to put a top-level heading above your content, which would make this one the second on the page. Worth checking on the page itself — if your theme does not, this is fine as it is.', 'wowstudio-accessibility-kit' )
					: sprintf(
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
