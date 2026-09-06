<?php
/**
 * Text that asks a lot of the reader.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner\Rules;

use DOMElement;
use WOWStudio\AccessibilityKit\Readability\FleschKincaid;
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
 * Flags a page whose text reads above lower secondary level.
 *
 * WCAG 3.1.5 asks that where text requires reading ability beyond lower
 * secondary education, a simpler version or a supplement is available. This
 * estimates the first half of that with Flesch–Kincaid, and points at the
 * simplified summary for the second.
 *
 * Reported as needing review, and it could not honestly be anything else. The
 * formula counts words per sentence and syllables per word and knows nothing
 * about meaning: it can be improved by chopping one clear sentence into three
 * fragments, which makes the writing worse. It also has no idea what your
 * audience is — a page written for cardiologists is allowed to read like one.
 * So this reports a measurement and what it might mean, never a failure.
 *
 * Three things stop it firing where it would be noise: it needs a hundred words
 * before it will say anything, because a formula fed two sentences reports a
 * number with the same confidence it reports one for an essay; it only runs on
 * a full page; and it refuses entirely on a site that is not in English, since
 * both the syllable heuristic and the coefficients are English and a number
 * that means nothing looks exactly like a number that does.
 *
 * @since 0.25.0
 */
final class ReadingLevelHigh implements Rule {

	use RunsOnServer;

	/**
	 * The readability formula.
	 *
	 * @since 0.25.0
	 * @var FleschKincaid
	 */
	private FleschKincaid $formula;

	/**
	 * Constructor.
	 *
	 * @since 0.25.0
	 *
	 * @param FleschKincaid|null $formula The formula.
	 */
	public function __construct( ?FleschKincaid $formula = null ) {
		$this->formula = $formula ?? new FleschKincaid();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.25.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'reading-level-high';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.25.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '3.1.5';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.25.0
	 *
	 * @return Severity
	 */
	public function severity(): Severity {
		return Severity::Minor;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.25.0
	 *
	 * @return Detection
	 */
	public function detection(): Detection {
		return Detection::Manual;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.25.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Text may be hard to read', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.25.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'By the Flesch–Kincaid formula this page reads above lower secondary school level, which WCAG 3.1.5 treats as the point where a simpler version or a plain-language summary should be available. Treat this as a prompt rather than a verdict: the formula counts sentence length and syllables and understands nothing, so a page written for a specialist audience may be exactly right as it is.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.25.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return __( 'Readers with a cognitive disability, or reading in a second language, may not get through this page.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.25.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// Rewriting or summarising is writing, which nothing here can do.
		return new FixPlan(
			FixKind::Manual,
			FixTarget::Content,
			__( 'Two ways to answer this and both are writing: simplify the page, or add a plain-language summary in the Accessibility panel of the editor. Do not chase the number — shortening sentences until the score drops usually makes the writing worse, and the formula cannot tell.', 'wowstudio-accessibility-kit' )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.25.0
	 *
	 * @param Document $document Parsed page.
	 * @return Finding[]
	 */
	public function evaluate( Document $document ): array {
		if ( ! $document->is_full_page() || ! FleschKincaid::supports( get_locale() ) ) {
			return array();
		}

		$grade = $this->formula->grade( $this->prose( $document ) );

		if ( null === $grade || $grade <= FleschKincaid::LOWER_SECONDARY ) {
			return array();
		}

		return array(
			new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				sprintf(
					/* translators: 1: the estimated reading grade level. 2: the level at which this starts being reported. */
					__( 'This page reads at about grade %1$s. Above grade %2$s, WCAG 3.1.5 asks for a simpler version or a summary.', 'wowstudio-accessibility-kit' ),
					number_format_i18n( $grade, 1 ),
					number_format_i18n( FleschKincaid::LOWER_SECONDARY, 0 )
				),
				'',
				''
			),
		);
	}

	/**
	 * Returns the page's prose, with its sentence boundaries intact.
	 *
	 * Paragraphs only, and each one terminated before the next is appended.
	 * Both halves of that matter and both were learned the hard way.
	 *
	 * Taking the whole element's text ran headings, navigation and list items
	 * together into one unpunctuated run, so the formula saw a single
	 * four-hundred-word sentence and reported postgraduate reading level for an
	 * ordinary page. Terminating each paragraph fixes that.
	 *
	 * Paragraphs only, because a readability formula is for prose. Menu labels
	 * and button text are not sentences, and averaging them in measures the
	 * theme rather than the writing.
	 *
	 * @since 0.25.0
	 *
	 * @param Document $document Parsed page.
	 * @return string
	 */
	private function prose( Document $document ): string {
		$scope = $document->first( '//main' ) instanceof DOMElement ? '//main//p' : '//p';
		$parts = array();

		foreach ( $document->find( $scope ) as $paragraph ) {
			if ( ! $paragraph instanceof DOMElement || $document->is_hidden( $paragraph ) ) {
				continue;
			}

			$text = trim( (string) preg_replace( '/\s+/u', ' ', $document->text_of( $paragraph ) ) );

			if ( '' === $text ) {
				continue;
			}

			// A paragraph that does not end in sentence punctuation still ends
			// a sentence; without this the next one is glued to it.
			if ( 1 !== preg_match( '/[.!?]$/u', $text ) ) {
				$text .= '.';
			}

			$parts[] = $text;
		}

		return implode( ' ', $parts );
	}
}
