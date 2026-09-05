<?php
/**
 * Alt text long enough to be a paragraph.
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
 * Flags alt text long enough that it should probably be body text instead.
 *
 * Alt text is announced as one unbroken run. There is no pausing it, no
 * skimming it, and in most screen readers no way to re-read a piece of it
 * without starting the whole thing again — so three hundred characters of
 * description is a paragraph somebody has to sit through with none of the
 * controls they would have over a paragraph.
 *
 * Reported as needing review rather than settled automatically, and this
 * distinction matters. Long alt text is not a WCAG failure; 1.1.1 asks for a
 * text alternative that serves the same purpose, and occasionally a genuinely
 * complex image needs a long one. What the length reliably indicates is that
 * the content might belong on the page, in a caption or a nearby paragraph,
 * where everybody can read it and anybody can navigate it. That is a judgement
 * about the content, so it is put to the person who owns it rather than
 * asserted.
 *
 * @since 0.17.0
 */
final class ImageAltTooLong implements Rule {

	use RunsOnServer;

	/**
	 * Where "a description" starts becoming "a paragraph".
	 *
	 * A hundred and fifty characters. There is no threshold in WCAG itself —
	 * the commonly cited 125 comes from screen readers that once truncated
	 * there, which current ones do not. This sits above that so the rule is not
	 * arguing with people who wrote a good two-line description, and well below
	 * the length at which alt text is obviously the wrong container.
	 *
	 * @since 0.17.0
	 * @var int
	 */
	private const LIMIT = 150;

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'img-alt-too-long';
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
		return __( 'Alt text may be too long to listen to', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Alt text is announced in one run, with no way to pause it, skim it or go back a sentence. Anything this long is usually content the page itself should carry — in a caption, or a paragraph beside the image — where everyone can read it and anyone can navigate it. Consider shortening the alt to what the image conveys at a glance and moving the detail onto the page.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return __( 'Someone using a screen reader has to sit through a paragraph they cannot pause, skim or re-read.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// Whether the detail belongs on the page is a question about the content.
		return new FixPlan(
			FixKind::Manual,
			FixTarget::Media,
			__( 'Whether this detail belongs in the alt text or on the page is a question about the content, and sometimes the long version is right. Shorten it in the media library if it is not.', 'wowstudio-accessibility-kit' )
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

		foreach ( $document->find( '//img[@alt]' ) as $image ) {
			if ( ! $image instanceof DOMElement || $document->is_hidden( $image ) ) {
				continue;
			}

			$alt    = trim( $image->getAttribute( 'alt' ) );
			$length = function_exists( 'mb_strlen' ) ? mb_strlen( $alt ) : strlen( $alt );

			if ( $length <= self::LIMIT ) {
				continue;
			}

			$findings[] = new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				sprintf(
					/* translators: 1: how many characters the alt text is. 2: the length at which this rule starts reporting. */
					__( 'This alt text is %1$d characters long; anything over about %2$d is usually better as a caption or a paragraph.', 'wowstudio-accessibility-kit' ),
					$length,
					self::LIMIT
				),
				$document->selector_for( $image ),
				$document->context_for( $image )
			);
		}

		return $findings;
	}
}
