<?php
/**
 * Alt text that repeats what is already said nearby.
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
 * Flags alt text that duplicates the image's caption or its title attribute.
 *
 * Both cases produce the same experience: the sentence is announced twice in a
 * row. It is not a serious failure — the information does arrive — but it is
 * the kind of small, constant friction that makes a site tiring rather than
 * unusable, and it is invisible to anybody who is not listening to the page.
 *
 * Only exact duplication after whitespace and case folding is reported. Alt
 * text that *overlaps* a caption is usually correct and often unavoidable:
 * "The mayor cutting the ribbon" beside a caption reading "Mayor Chen opens the
 * new library, June 2026" is two different jobs done well, and a looser
 * comparison would flag it.
 *
 * @since 0.17.0
 */
final class ImageAltRedundant implements Rule {

	use RunsOnServer;

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'img-alt-redundant';
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
		return __( 'Alt text repeats the caption or title', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'This image\'s alt text is word-for-word the same as its caption or its title attribute, so a screen reader announces the same sentence twice in a row. Either describe the image differently in the alt — what it shows, rather than what the caption already says about it — or mark it decorative with alt="" and let the caption do the work.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return __( 'The same sentence is announced twice in a row to anyone listening to the page.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// Which of the two should change depends on what each is for.
		return new FixPlan(
			FixKind::Manual,
			FixTarget::Content,
			__( 'Which of the two should change depends on what each is for: the caption is usually the context, the alt is usually what the picture shows. Sometimes the right answer is an empty alt.', 'wowstudio-accessibility-kit' )
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

			$alt = $this->folded( $image->getAttribute( 'alt' ) );

			if ( '' === $alt ) {
				continue;
			}

			if ( $this->folded( $image->getAttribute( 'title' ) ) === $alt ) {
				$findings[] = $this->finding(
					$document,
					$image,
					__( 'This image\'s alt text and title attribute are identical, so both are announced.', 'wowstudio-accessibility-kit' )
				);

				continue;
			}

			// Relative to this image. An absolute expression would find the
			// first figcaption anywhere on the page and compare every image
			// against it.
			$caption = $document->find( 'ancestor::figure[1]/figcaption', $image )->item( 0 );

			if ( $caption instanceof DOMElement && $this->folded( $document->text_of( $caption ) ) === $alt ) {
				$findings[] = $this->finding(
					$document,
					$image,
					__( 'This image\'s alt text is word-for-word its caption, so the same sentence is announced twice.', 'wowstudio-accessibility-kit' )
				);
			}
		}

		return $findings;
	}

	/**
	 * Builds one finding.
	 *
	 * @since 0.17.0
	 *
	 * @param Document   $document Parsed page.
	 * @param DOMElement $image    The image.
	 * @param string     $message  What to say.
	 * @return Finding
	 */
	private function finding( Document $document, DOMElement $image, string $message ): Finding {
		return new Finding(
			$this->id(),
			$this->wcag_sc(),
			$this->severity(),
			$this->detection(),
			$message,
			$document->selector_for( $image ),
			$document->context_for( $image )
		);
	}

	/**
	 * Normalises a string for comparison.
	 *
	 * @since 0.17.0
	 *
	 * @param string $value Value to fold.
	 * @return string
	 */
	private function folded( string $value ): string {
		$value = (string) preg_replace( '/\s+/u', ' ', strtolower( trim( $value ) ) );

		return trim( $value, " \t\n\r\0\x0B.," );
	}
}
