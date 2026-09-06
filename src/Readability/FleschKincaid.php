<?php
/**
 * How hard the text is to read, by a formula.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Readability;

defined( 'ABSPATH' ) || exit;

/**
 * The Flesch–Kincaid grade level, and an honest account of what it is worth.
 *
 * WCAG 3.1.5 asks that where text needs reading ability beyond lower secondary
 * education, a simpler version or supplement is available. Something has to
 * estimate "beyond lower secondary", and this is the estimate the field has
 * settled on — not because it is good, but because it is cheap, reproducible
 * and universally understood.
 *
 * It is worth being blunt about what it measures, because a number in a box
 * gets treated as a fact. Flesch–Kincaid counts words per sentence and
 * syllables per word. That is all. It has no idea what the words mean. "The cat
 * sat on the antidisestablishmentarianism" scores as difficult and is not; a
 * paragraph of short, common words arranged into a legal condition precedent
 * scores as easy and is not. It rewards short sentences, so it can be gamed by
 * chopping one clear sentence into three fragments, which makes text worse and
 * the score better.
 *
 * **It is also English-only.** The syllable heuristic below is English
 * orthography, and the coefficients were fitted to English. Run it over German
 * or Japanese and it produces a number, which is the problem — a number that
 * means nothing looks exactly like a number that does. So the caller checks the
 * site language first, and this class refuses rather than guessing.
 *
 * @since 0.25.0
 */
final class FleschKincaid {

	/**
	 * Locales this formula can honestly be applied to.
	 *
	 * @since 0.25.0
	 * @var string[]
	 */
	private const SUPPORTED = array( 'en' );

	/**
	 * The grade level above which 3.1.5 asks for a simpler version.
	 *
	 * Lower secondary education is roughly grade 9 in the scale this formula
	 * reports, which is where the W3C's own guidance puts the boundary.
	 *
	 * @since 0.25.0
	 * @var float
	 */
	public const LOWER_SECONDARY = 9.0;

	/**
	 * Reports whether the formula applies to a locale at all.
	 *
	 * @since 0.25.0
	 *
	 * @param string $locale A WordPress locale, such as en_GB.
	 * @return bool
	 */
	public static function supports( string $locale ): bool {
		$language = strtolower( substr( $locale, 0, 2 ) );

		return in_array( $language, self::SUPPORTED, true );
	}

	/**
	 * Returns the grade level for a piece of text, or null.
	 *
	 * Null when there is not enough text to say anything. A formula fed two
	 * sentences reports a grade level with the same confidence it reports one
	 * for an essay, and the first number is noise. Refusing is the honest
	 * answer, and it is why the caller has to handle null rather than being
	 * handed a zero.
	 *
	 * @since 0.25.0
	 *
	 * @param string $text Plain text, tags already stripped.
	 * @return float|null
	 */
	public function grade( string $text ): ?float {
		$stats = $this->measure( $text );

		if ( null === $stats ) {
			return null;
		}

		$grade = ( 0.39 * ( $stats['words'] / $stats['sentences'] ) )
			+ ( 11.8 * ( $stats['syllables'] / $stats['words'] ) )
			- 15.59;

		// The formula can go negative on very short simple sentences, which is
		// not a grade anybody recognises.
		return round( max( 0.0, $grade ), 1 );
	}

	/**
	 * Returns the counts the formula runs on, or null when there are too few.
	 *
	 * @since 0.25.0
	 *
	 * @param string $text Plain text.
	 * @return array{words: int, sentences: int, syllables: int}|null
	 */
	public function measure( string $text ): ?array {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );

		if ( '' === $text ) {
			return null;
		}

		$sentences = preg_split( '/[.!?]+(?:\s|$)/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		$sentences = is_array( $sentences ) ? count( $sentences ) : 0;

		$words = preg_split( "/[^A-Za-z0-9'’-]+/u", $text, -1, PREG_SPLIT_NO_EMPTY );
		$words = is_array( $words ) ? $words : array();

		/**
		 * Filters the minimum number of words before a grade is reported.
		 *
		 * @since 0.25.0
		 *
		 * @param int $minimum Words required.
		 */
		$minimum = (int) apply_filters( 'wsak_readability_minimum_words', 100 );

		if ( count( $words ) < $minimum || $sentences < 1 ) {
			return null;
		}

		/*
		 * A refusal that had to be added after the formula reported grade 23 on
		 * this plugin's own accessibility statement. The cause was not the
		 * prose: the text had arrived with its sentence punctuation stripped, so
		 * the whole page counted as one sentence and words-per-sentence went
		 * through the roof. Flesch–Kincaid has no way to notice that — it
		 * divides by whatever it was given and returns a confident number.
		 *
		 * No natural English writing averages sixty words a sentence. Past that
		 * the input is almost certainly punctuation-free rather than difficult,
		 * and the honest answer is that this cannot be measured.
		 */
		if ( ( count( $words ) / $sentences ) > 60 ) {
			return null;
		}

		$syllables = 0;

		foreach ( $words as $word ) {
			$syllables += $this->syllables( $word );
		}

		return array(
			'words'     => count( $words ),
			'sentences' => max( 1, $sentences ),
			'syllables' => $syllables,
		);
	}

	/**
	 * Estimates the syllables in one English word.
	 *
	 * A heuristic, and known to be one. It counts vowel groups, discounts a
	 * silent terminal "e", and never returns less than one. It gets "queue"
	 * wrong, and "business", and most names. Across a few hundred words those
	 * errors mostly cancel, which is the only reason the formula works at all —
	 * and a reason not to read too much into a single decimal place.
	 *
	 * @since 0.25.0
	 *
	 * @param string $word One word.
	 * @return int
	 */
	private function syllables( string $word ): int {
		$word = strtolower( (string) preg_replace( '/[^a-z]/i', '', $word ) );

		if ( '' === $word ) {
			return 0;
		}

		if ( strlen( $word ) <= 3 ) {
			return 1;
		}

		// A terminal "es", "ed" or bare "e" is usually not its own syllable:
		// "grades" is one, "graded" is two because the d keeps it.
		$trimmed = (string) preg_replace( '/(?:[^laeiouy]es|[^laeiouy]e)$/', '', $word );
		$trimmed = (string) preg_replace( '/^y/', '', $trimmed );

		$groups = preg_match_all( '/[aeiouy]{1,2}/', $trimmed );

		return max( 1, (int) $groups );
	}
}
