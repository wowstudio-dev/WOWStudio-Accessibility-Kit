<?php
/**
 * A viewport tag that stops the page being zoomed.
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
 * Flags a viewport meta tag that blocks or caps pinch-zoom.
 *
 * `user-scalable=no` and a low `maximum-scale` both do the same thing: they take
 * away the reader's ability to make the text bigger. For anybody with low
 * vision that is not a preference being overridden, it is the page becoming
 * unreadable on the device they actually own.
 *
 * It is almost always cargo-culted. The attribute dates from an era when mobile
 * browsers zoomed erratically on form focus; that stopped being true years ago,
 * and modern iOS ignores `user-scalable=no` outright — which means the tag now
 * mostly penalises Android users while its author believes it does nothing.
 *
 * 1.4.4 asks for text to scale to 200%, so a `maximum-scale` below 2 fails on
 * the same grounds even when scaling is not switched off completely.
 *
 * @since 0.18.0
 */
final class ViewportScalingDisabled implements Rule {

	use RunsOnServer;


	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'viewport-scaling-disabled';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '1.4.4';
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
		return __( 'The page cannot be zoomed', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'This page\'s viewport tag stops pinch-zoom, or caps it below twice the normal size. Readers with low vision rely on zooming to read at all, and this takes that away on exactly the devices where it matters most. Remove user-scalable=no and any maximum-scale below 2 — the browser behaviour these were once working around no longer exists.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return __( 'Readers who need larger text cannot enlarge the page on a phone or tablet.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// The viewport tag is printed by the theme, not by your content.
		return new FixPlan(
			FixKind::Manual,
			FixTarget::Theme,
			__( 'The viewport tag is printed in the theme\'s head, so this is a one-line change there rather than anything in your content: drop user-scalable=no and any maximum-scale below 2.', 'wowstudio-accessibility-kit' )
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

		$findings = array();

		foreach ( $document->find( '//meta[@name="viewport"]' ) as $meta ) {
			if ( ! $meta instanceof DOMElement ) {
				continue;
			}

			$content = strtolower( str_replace( ' ', '', $meta->getAttribute( 'content' ) ) );
			$reasons = array();

			if ( str_contains( $content, 'user-scalable=no' ) || str_contains( $content, 'user-scalable=0' ) ) {
				$reasons[] = __( 'zooming is switched off', 'wowstudio-accessibility-kit' );
			}

			if ( 1 === preg_match( '/maximum-scale=([0-9.]+)/', $content, $matches ) && (float) $matches[1] < 2.0 ) {
				$reasons[] = sprintf(
					/* translators: %s: the maximum-scale value found. */
					__( 'zooming stops at %s times', 'wowstudio-accessibility-kit' ),
					$matches[1]
				);
			}

			if ( array() === $reasons ) {
				continue;
			}

			$findings[] = new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				sprintf(
					/* translators: %s: what the viewport tag does, already phrased. */
					__( 'The viewport tag means %s, so the page cannot be enlarged to twice its size.', 'wowstudio-accessibility-kit' ),
					implode( __( ' and ', 'wowstudio-accessibility-kit' ), $reasons )
				),
				$document->selector_for( $meta ),
				$document->context_for( $meta )
			);
		}

		return $findings;
	}
}
