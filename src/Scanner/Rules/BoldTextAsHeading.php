<?php
/**
 * Bold paragraphs standing in for headings.
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
 * Flags a short paragraph whose entire content is bold.
 *
 * This is how most pages end up with no usable structure. Somebody wants a
 * section title, selects the text, presses bold, and gets something that looks
 * exactly like a heading and is not one. The page reads correctly to the eye
 * and has no outline at all for anybody navigating by headings — which is the
 * main way screen reader users move through a long page.
 *
 * The test is deliberately narrow, because "bold text" on its own is far too
 * common to report: the whole paragraph must be bold, it must be short enough
 * to be a title rather than an emphasised sentence, and it must not end in
 * sentence punctuation. Even then this asks rather than asserts, because a
 * short bold line genuinely is sometimes just a short bold line.
 *
 * It also has to be in ordinary document flow. A bold line inside a callout, a
 * quotation, a caption, a list item or a table cell is doing a different job,
 * and so is one inside anything carrying an explicit landmark or widget role —
 * a `role` attribute means somebody has already decided what that region is.
 * This plugin's own draft-statement banner is the case that proved it: a bold
 * line inside `role="note"`, correctly marked up, reported as a stray heading
 * until the exclusion below existed. The dogfooding test caught it.
 *
 * @since 0.18.0
 */
final class BoldTextAsHeading implements Rule {

	use RunsOnServer;

	/**
	 * How long a bold paragraph can be before it is obviously a sentence.
	 *
	 * A heading that runs past a hundred characters is unusual; an emphasised
	 * sentence that long is not. Reporting above this would trade a rule that
	 * finds real structure problems for one that argues about emphasis.
	 *
	 * @since 0.18.0
	 * @var int
	 */
	private const MAX_LENGTH = 100;

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'bold-text-as-heading';
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
		return Severity::Moderate;
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
		return __( 'Bold text may be standing in for a heading', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'This paragraph is entirely bold, short, and has no closing punctuation, which usually means it is a section title formatted by hand rather than marked as a heading. It looks like a heading and is not one, so it does not appear in the list of headings screen reader users navigate by, and the page has no outline where it appears to have one. If it is a heading, change the block type to a heading.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return __( 'The page looks structured but has no outline, so there is nothing to navigate by.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.18.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// Whether this is a title or an emphasised line is a judgement about the text.
		return new FixPlan(
			FixKind::Manual,
			FixTarget::Content,
			__( 'Whether this is a section title or simply an emphasised line is a judgement about what the page says. If it is a title, change the block from paragraph to heading and pick the level that fits the ones around it.', 'wowstudio-accessibility-kit' )
		);
	}

	/**
	 * Containers in which a bold line is not a heading standing in for one.
	 *
	 * @since 0.18.0
	 * @var string[]
	 */
	private const CALLOUTS = array( 'blockquote', 'figure', 'figcaption', 'caption', 'aside', 'legend', 'td', 'th', 'dt', 'dd', 'li', 'label', 'details', 'summary' );

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

		foreach ( $document->find( '//p' ) as $paragraph ) {
			if ( ! $paragraph instanceof DOMElement || $document->is_hidden( $paragraph ) ) {
				continue;
			}

			$text = trim( (string) preg_replace( '/\s+/u', ' ', $document->text_of( $paragraph ) ) );

			if ( '' === $text || mb_strlen( $text ) > self::MAX_LENGTH ) {
				continue;
			}

			// Sentence punctuation says it is a sentence, not a title.
			if ( 1 === preg_match( '/[.!?:;,]$/u', $text ) ) {
				continue;
			}

			if ( $this->is_in_a_callout( $document, $paragraph ) ) {
				continue;
			}

			$bold = $document->find( 'strong|b', $paragraph );

			if ( 1 !== $bold->length ) {
				continue;
			}

			$inner = $bold->item( 0 );

			if ( ! $inner instanceof DOMElement ) {
				continue;
			}

			// The bold run has to be the whole paragraph, not a phrase in it.
			if ( trim( (string) preg_replace( '/\s+/u', ' ', $document->text_of( $inner ) ) ) !== $text ) {
				continue;
			}

			$findings[] = new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				sprintf(
					/* translators: %s: the bold text. */
					__( '"%s" is a whole paragraph in bold, which often means a heading formatted by hand.', 'wowstudio-accessibility-kit' ),
					$text
				),
				$document->selector_for( $paragraph ),
				$document->context_for( $paragraph )
			);
		}

		return $findings;
	}

	/**
	 * Reports whether this paragraph sits somewhere a heading would not.
	 *
	 * Walks a bounded number of ancestors rather than the whole tree. Page
	 * builders nest deeply enough that testing every ancestor for a role would
	 * eventually hit a wrapper carrying one and silence the check for the
	 * entire page.
	 *
	 * @since 0.18.0
	 *
	 * @param Document   $document  Parsed page.
	 * @param DOMElement $paragraph The paragraph.
	 * @return bool
	 */
	private function is_in_a_callout( Document $document, DOMElement $paragraph ): bool {
		$node  = $paragraph;
		$depth = 0;

		while ( $node instanceof DOMElement && $depth < 4 ) {
			if ( $node->hasAttribute( 'role' ) ) {
				return true;
			}

			if ( in_array( strtolower( $node->nodeName ), self::CALLOUTS, true ) ) {
				return true;
			}

			$node = $node->parentNode instanceof DOMElement ? $node->parentNode : null;
			++$depth;
		}

		return false;
	}
}
