<?php
/**
 * Underlined text that is not a link.
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
 * Flags the `<u>` element used on text that is not a link.
 *
 * On the web, underlined text means a link. That convention is decades old and
 * readers act on it without thinking, so underlining something else produces a
 * small, repeated failure: people try to click it, nothing happens, and they
 * are left unsure whether the page is broken or they missed.
 *
 * Reported as needing review rather than settled, because the element does have
 * legitimate uses that HTML specifically names — marking a misspelling, or a
 * proper name in some Chinese text — and a check cannot tell those from
 * emphasis applied by habit. What it can do is ask.
 *
 * @since 0.18.0
 */
final class UnderlineNotALink implements Rule {

	use RunsOnServer;


	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'underline-not-a-link';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '1.3.1';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return Severity
	 */
	public function severity(): Severity {
		return Severity::Minor;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return Detection
	 */
	public function detection(): Detection {
		return Detection::Manual;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Text is underlined but is not a link', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'This text is underlined and is not a link. Underlining means "link" to most readers, so they will try to click it and find that nothing happens. If the intent was emphasis, em or strong says so to a screen reader as well as to the eye, which an underline does not. There are real uses for the u element — marking a misspelling, for instance — so this is worth a look rather than an automatic change.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return __( 'Readers try to click text that is not a link, and cannot tell whether the page is broken.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// Whether it was meant as emphasis, or is one of the element's real uses, needs a person.
		return new FixPlan(
			FixKind::Manual,
			FixTarget::Content,
			__( 'Whether this was meant as emphasis — in which case em or strong carries it to a screen reader too — or is one of the genuine uses of the u element, is something only the author knows.', 'wowstudio-accessibility-kit' )
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

		foreach ( $document->find( '//u' ) as $element ) {
			if ( ! $element instanceof DOMElement || $document->is_hidden( $element ) ) {
				continue;
			}

			// Inside a link the underline is telling the truth, so it is not
			// the confusion this rule is about.
			if ( $document->find( 'ancestor::a[@href]', $element )->length > 0 ) {
				continue;
			}

			$text = trim( $document->text_of( $element ) );

			if ( '' === $text ) {
				continue;
			}

			$findings[] = new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				sprintf(
					/* translators: %s: the underlined text. */
					__( '"%s" is underlined but is not a link.', 'wowstudio-accessibility-kit' ),
					$this->shorten( $text )
				),
				$document->selector_for( $element ),
				$document->context_for( $element )
			);
		}

		return $findings;
	}

	/**
	 * Trims a quoted excerpt to something a message can hold.
	 *
	 * @since 0.18.0
	 *
	 * @param string $text Text to shorten.
	 * @return string
	 */
	private function shorten( string $text ): string {
		$text = (string) preg_replace( '/\s+/u', ' ', $text );

		if ( mb_strlen( $text ) <= 60 ) {
			return $text;
		}

		return mb_substr( $text, 0, 57 ) . '…';
	}
}
