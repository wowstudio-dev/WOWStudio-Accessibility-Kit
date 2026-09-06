<?php
/**
 * Readability tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use WOWStudio\AccessibilityKit\Readability\FleschKincaid;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests the reading-level formula, and mostly the things it refuses to answer.
 *
 * A readability score is a number in a box, and a number in a box gets believed.
 * Most of what follows is about the cases where believing it would be wrong.
 *
 * @covers \WOWStudio\AccessibilityKit\Readability\FleschKincaid
 */
final class ReadabilityTest extends TestCase {

	/**
	 * Plain writing scores low.
	 *
	 * @return void
	 */
	public function test_simple_writing_reads_as_simple(): void {
		$text  = str_repeat( 'The cat sat on the mat. The dog ran to the park. We had a good day. ', 8 );
		$grade = ( new FleschKincaid() )->grade( $text );

		$this->assertNotNull( $grade );
		$this->assertLessThan( 6.0, $grade );
	}

	/**
	 * Long words in long sentences score high.
	 *
	 * @return void
	 */
	public function test_dense_writing_reads_as_dense(): void {
		$text = str_repeat(
			'Notwithstanding the aforementioned considerations, the implementation of comprehensive accessibility remediation necessitates substantial organisational commitment. ',
			12
		);

		$grade = ( new FleschKincaid() )->grade( $text );

		$this->assertNotNull( $grade );
		$this->assertGreaterThan( FleschKincaid::LOWER_SECONDARY, $grade );
	}

	/**
	 * Too little text produces no answer at all.
	 *
	 * A formula fed two sentences reports a grade with the same confidence it
	 * reports one for an essay. Refusing is the honest reply, and it is why
	 * every caller has to handle null instead of being handed a zero.
	 *
	 * @return void
	 */
	public function test_too_little_text_is_refused(): void {
		$formula = new FleschKincaid();

		$this->assertNull( $formula->grade( '' ) );
		$this->assertNull( $formula->grade( 'A short page.' ) );
		$this->assertNull( $formula->measure( 'Barely anything here at all.' ) );
	}

	/**
	 * Text with no sentence punctuation is refused rather than guessed at.
	 *
	 * The regression that produced this test: the rule was handing over a whole
	 * page of headings and list items run together, so the formula saw one
	 * four-hundred-word sentence and reported postgraduate reading level for
	 * ordinary prose. It divides by whatever it is given and cannot notice.
	 *
	 * @return void
	 */
	public function test_text_without_sentences_is_refused(): void {
		$unpunctuated = str_repeat( 'The cat sat on the mat The dog ran to the park We had a good day ', 12 );

		$this->assertNull(
			( new FleschKincaid() )->grade( $unpunctuated ),
			'Sixty words to a sentence means missing punctuation, not difficult writing.'
		);

		// The same words, punctuated, are perfectly measurable.
		$punctuated = str_repeat( 'The cat sat on the mat. The dog ran to the park. We had a good day. ', 12 );
		$this->assertNotNull( ( new FleschKincaid() )->grade( $punctuated ) );
	}

	/**
	 * The formula refuses languages it was not built for.
	 *
	 * Both the syllable heuristic and the coefficients are English. Run over
	 * German and it still returns a number, which is the danger — a number that
	 * means nothing looks exactly like one that does.
	 *
	 * @return void
	 */
	public function test_the_formula_knows_it_is_english_only(): void {
		$this->assertTrue( FleschKincaid::supports( 'en_GB' ) );
		$this->assertTrue( FleschKincaid::supports( 'en_US' ) );
		$this->assertFalse( FleschKincaid::supports( 'de_DE' ) );
		$this->assertFalse( FleschKincaid::supports( 'ja' ) );
		$this->assertFalse( FleschKincaid::supports( 'fr_FR' ) );
	}

	/**
	 * A grade is never negative, however simple the writing.
	 *
	 * @return void
	 */
	public function test_a_grade_is_never_below_zero(): void {
		$grade = ( new FleschKincaid() )->grade( str_repeat( 'I am. You are. We go. It is. He ran. She sat. ', 20 ) );

		$this->assertNotNull( $grade );
		$this->assertGreaterThanOrEqual( 0.0, $grade );
	}

	/**
	 * The counts the formula runs on are reported, not just the result.
	 *
	 * @return void
	 */
	public function test_the_working_is_available(): void {
		$stats = ( new FleschKincaid() )->measure(
			str_repeat( 'The cat sat on the mat. The dog ran to the park. We had a good day. ', 8 )
		);

		$this->assertIsArray( $stats );
		$this->assertSame( 136, $stats['words'] );
		$this->assertSame( 24, $stats['sentences'] );
		$this->assertGreaterThan( 0, $stats['syllables'] );
	}
}
