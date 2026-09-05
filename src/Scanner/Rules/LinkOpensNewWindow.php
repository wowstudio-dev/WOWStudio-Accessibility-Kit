<?php
/**
 * Links that open a new tab without saying so.
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
 * Flags `target="_blank"` links whose name does not mention the new tab.
 *
 * Opening a new tab takes the back button away. For a sighted mouse user that
 * is a mild annoyance they can see coming and recover from; for somebody using
 * a screen reader it is a context change with no announcement, and the usual
 * recovery — press Back — silently does nothing, because there is nothing
 * behind them any more.
 *
 * The fix is famously cheap: say so in the link. The check therefore passes any
 * link whose accessible name or title already contains a phrase to that effect,
 * in whichever of those the author put it.
 *
 * Reported as needing review rather than settled. WCAG has no criterion that a
 * bare `target="_blank"` fails outright — 3.2.5 is a AAA criterion about
 * changes on request, and the relevant AA reading is contested. This is
 * long-standing, near-universal practice rather than a rule, and it is stated
 * that way instead of being dressed up as a violation.
 *
 * @since 0.17.0
 */
final class LinkOpensNewWindow implements Rule {

	use RunsOnServer;

	/**
	 * Phrases that count as having said so.
	 *
	 * Lowercased substrings, matched against the accessible name and the title.
	 * Deliberately generous: the point is to avoid nagging somebody who already
	 * handled this, however they worded it.
	 *
	 * @since 0.17.0
	 * @var string[]
	 */
	private const WARNINGS = array(
		'new window',
		'new tab',
		'opens in',
		'opens a',
		'external link',
		'opens externally',
	);

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'link-opens-new-window';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '3.2.5';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return Severity
	 */
	public function severity(): Severity {
		return Severity::Minor;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return Detection
	 */
	public function detection(): Detection {
		return Detection::Manual;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Link opens a new tab without saying so', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'This link opens in a new tab, and nothing in its text or title says it will. A new tab removes the back button, which is a quiet problem for a sighted user and a confusing one for somebody who cannot see that a tab opened. Either say so in the link text — "(opens in a new tab)" — or let the link open normally and leave the choice to the reader.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return __( 'The reader lands somewhere new with no announcement, and pressing Back does nothing.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// Whether this link should open a new tab at all is an editorial call.
		return new FixPlan(
			FixKind::Manual,
			FixTarget::Content,
			__( 'Two possible answers, and choosing between them is an editorial call: add the warning to the link text, or stop opening a new tab. Both are edits to the link in your content.', 'wowstudio-accessibility-kit' )
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
		$findings = array();

		foreach ( $document->find( '//a[@href][@target]' ) as $link ) {
			if ( ! $link instanceof DOMElement || $document->is_hidden( $link ) ) {
				continue;
			}

			$target = strtolower( trim( $link->getAttribute( 'target' ) ) );

			/*
			 * A named target reuses one window rather than spawning a new one
			 * each time, and is usually a frame. Only _blank reliably means
			 * "somewhere the back button cannot reach".
			 */
			if ( '_blank' !== $target ) {
				continue;
			}

			$haystack = strtolower(
				$document->accessible_name( $link ) . ' ' . $link->getAttribute( 'title' )
			);

			foreach ( self::WARNINGS as $warning ) {
				if ( str_contains( $haystack, $warning ) ) {
					continue 2;
				}
			}

			$name = trim( $document->accessible_name( $link ) );

			$findings[] = new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				'' === $name
					? __( 'This link opens in a new tab and does not say so.', 'wowstudio-accessibility-kit' )
					: sprintf(
						/* translators: %s: the link text. */
						__( 'The link "%s" opens in a new tab and does not say so.', 'wowstudio-accessibility-kit' ),
						$name
					),
				$document->selector_for( $link ),
				$document->context_for( $link )
			);
		}

		return $findings;
	}
}
