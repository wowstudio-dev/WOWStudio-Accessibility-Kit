<?php
/**
 * Headings with nothing in them.
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
 * Flags heading elements that contain no text.
 *
 * Screen reader users navigate long pages by jumping from heading to heading —
 * it is the fastest way through a document and, for many people, the only
 * practical one. An empty heading is a stop on that route that announces
 * "heading level two" and then nothing, so the reader has to move into the
 * content to find out where they landed.
 *
 * They are almost always accidental: a stray heading block left behind in the
 * editor, a theme printing a heading around a value that turned out to be
 * empty, or a heading used to hold a decorative image with no alt text.
 *
 * An image inside the heading counts as text if it has alt text, because that
 * is exactly what alt text is for — a heading whose only content is a logo with
 * `alt="Acme"` announces "Acme" and is fine.
 *
 * @since 0.17.0
 */
final class HeadingEmpty implements Rule {

	use RunsOnServer;

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'heading-empty';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '1.3.1';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return Severity
	 */
	public function severity(): Severity {
		return Severity::Moderate;
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
		return __( 'Heading is empty', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'This heading contains no text, so a screen reader announces a heading and then nothing. People who navigate by jumping between headings get a stop on that route that tells them nothing about where they are. Either give the heading text, or remove it — an empty heading is usually left over from editing.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return __( 'Anyone navigating by headings lands on one that announces nothing.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.17.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// Whether it wants text or wants deleting depends on why it is there.
		return new FixPlan(
			FixKind::Manual,
			FixTarget::Content,
			__( 'Whether this heading wants text or wants deleting depends on why it is there, which is a question about the page. Both are edits in the editor.', 'wowstudio-accessibility-kit' )
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

		foreach ( $document->find( '//h1|//h2|//h3|//h4|//h5|//h6' ) as $heading ) {
			if ( ! $heading instanceof DOMElement || $document->is_hidden( $heading ) ) {
				continue;
			}

			// accessible_name folds in alt text from any image inside, which is
			// the behaviour wanted here: a heading holding a logo with alt text
			// announces that text and is not empty.
			if ( '' !== trim( $document->accessible_name( $heading ) ) ) {
				continue;
			}

			$findings[] = new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				sprintf(
					/* translators: %s: the heading element name, for example h2. */
					__( 'This %s contains no text.', 'wowstudio-accessibility-kit' ),
					strtolower( $heading->nodeName )
				),
				$document->selector_for( $heading ),
				$document->context_for( $heading )
			);
		}

		return $findings;
	}
}
