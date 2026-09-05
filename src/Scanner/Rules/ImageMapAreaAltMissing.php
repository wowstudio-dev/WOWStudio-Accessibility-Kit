<?php
/**
 * Image-map regions with no text alternative.
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
 * Flags clickable regions of an image map that have no alt text.
 *
 * An `<area>` is a link. It is the only kind of link whose text can *only* come
 * from an attribute — there is nowhere to put visible text inside it — so an
 * area without alt is a link with no name at all, and a screen reader has
 * nothing to announce but the URL.
 *
 * Rare, and worth checking anyway. Image maps survive in exactly the places
 * that get audited: floor plans, seating charts, regional selectors, old
 * imported pages. They are also invisible to the ordinary link checks, because
 * those look at `<a>`.
 *
 * Areas with `nohref` are skipped: those define a non-clickable hole in the
 * map, so there is no link and nothing to name.
 *
 * @since 0.17.0
 */
final class ImageMapAreaAltMissing implements Rule {

	use RunsOnServer;

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'image-map-area-alt-missing';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '1.1.1';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return Severity
	 */
	public function severity(): Severity {
		return Severity::Critical;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return Detection
	 */
	public function detection(): Detection {
		return Detection::Auto;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Image map region has no alt text', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'A clickable region of an image map is a link, and an area element has nowhere to put visible text — its alt attribute is the only name it can have. Without one, a screen reader announces the raw URL or nothing at all. Add an alt attribute naming where the region goes.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return __( 'A screen reader reaches a link it cannot name, and reads out the URL instead of the destination.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// Naming the region means knowing what part of the picture it covers.
		return new FixPlan(
			FixKind::Manual,
			FixTarget::Content,
			__( 'Naming the region means knowing which part of the picture it covers and where it goes, which is a question about the map rather than the markup. Add an alt attribute to each area.', 'wowstudio-accessibility-kit' )
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

		foreach ( $document->find( '//area[@href][not(@nohref)]' ) as $area ) {
			if ( ! $area instanceof DOMElement ) {
				continue;
			}

			if ( '' !== trim( $area->getAttribute( 'alt' ) ) ) {
				continue;
			}

			// aria-label is a valid alternative name for the link, even though
			// alt is the conventional one here.
			if ( '' !== trim( $area->getAttribute( 'aria-label' ) ) ) {
				continue;
			}

			$href = trim( $area->getAttribute( 'href' ) );

			$findings[] = new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				'' === $href
					? __( 'This image map region has no alt text.', 'wowstudio-accessibility-kit' )
					: sprintf(
						/* translators: %s: the URL the region links to. */
						__( 'The image map region linking to %s has no alt text.', 'wowstudio-accessibility-kit' ),
						$href
					),
				$document->selector_for( $area ),
				$document->context_for( $area )
			);
		}

		return $findings;
	}
}
