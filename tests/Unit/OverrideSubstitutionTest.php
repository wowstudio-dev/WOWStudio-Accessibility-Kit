<?php
/**
 * Override substitution tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use WOWStudio\AccessibilityKit\Remediation\Substitution;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests the substitution that rewrites a page as it renders.
 *
 * @covers \WOWStudio\AccessibilityKit\Remediation\Substitution
 */
final class OverrideSubstitutionTest extends TestCase {

	/**
	 * The matched element is replaced.
	 *
	 * @return void
	 */
	public function test_matching_element_is_replaced(): void {
		$result = Substitution::replace(
			'<p>Hello <img src="a.png"> world</p>',
			'<img src="a.png">',
			'<img src="a.png" alt="A cat">'
		);

		$this->assertStringContainsString( 'alt="A cat"', $result );
		$this->assertStringContainsString( 'Hello', $result );
		$this->assertStringContainsString( 'world', $result );
	}

	/**
	 * Attributes WordPress adds on the way out do not break the match.
	 *
	 * This is the case that a byte-for-byte string match got wrong. A scan reads
	 * the rendered page, where WordPress has added decoding and loading; the
	 * same element in post content has neither. The recorded markup may carry
	 * more attributes than the element it has to find.
	 *
	 * @return void
	 */
	public function test_extra_attributes_in_the_recorded_markup_still_match(): void {
		$result = Substitution::replace(
			'<p><img src="/cat.png"></p>',
			'<img decoding="async" loading="lazy" src="/cat.png">',
			'<img decoding="async" loading="lazy" src="/cat.png" alt="A sleeping cat">'
		);

		$this->assertStringContainsString( 'alt="A sleeping cat"', $result );
	}

	/**
	 * A genuinely different element is left alone.
	 *
	 * Tolerance runs one way only. An element carrying an attribute the record
	 * does not have is a different element, and must not be touched.
	 *
	 * @return void
	 */
	public function test_a_different_element_is_not_touched(): void {
		$content = '<p><img src="/dog.png"></p>';

		$this->assertSame(
			$content,
			Substitution::replace( $content, '<img src="/cat.png">', '<img src="/cat.png" alt="A cat">' )
		);
	}

	/**
	 * Only the first match changes.
	 *
	 * A reviewer approved one element. Changing every similar element on the
	 * page would alter things nobody looked at.
	 *
	 * @return void
	 */
	public function test_only_the_first_match_is_replaced(): void {
		$result = Substitution::replace(
			'<img src="a.png"><img src="a.png">',
			'<img src="a.png">',
			'<img src="a.png" alt="A cat">'
		);

		$this->assertSame( 1, substr_count( $result, 'alt="A cat"' ) );
	}

	/**
	 * Content without the element is returned byte-identical.
	 *
	 * Nothing is re-serialised when there is no match, so an override whose
	 * target has been edited away cannot perturb the page at all.
	 *
	 * @return void
	 */
	public function test_absent_markup_returns_content_untouched(): void {
		$content = '<p>The page changed since   the scan.</p>';

		$this->assertSame(
			$content,
			Substitution::replace( $content, '<img src="gone.png">', '<img src="gone.png" alt="x">' )
		);
	}

	/**
	 * Text content is part of the identity of an element.
	 *
	 * @return void
	 */
	public function test_link_text_distinguishes_two_links(): void {
		$result = Substitution::replace(
			'<a href="/x">Home</a><a href="/x">read more</a>',
			'<a href="/x">read more</a>',
			'<a href="/x">Read our accessibility statement</a>'
		);

		$this->assertStringContainsString( '>Home<', $result );
		$this->assertStringContainsString( 'Read our accessibility statement', $result );
	}

	/**
	 * Whitespace differences inside the markup do not prevent a match.
	 *
	 * @return void
	 */
	public function test_whitespace_differences_still_match(): void {
		$result = Substitution::replace(
			"<p><img\n   src=\"a.png\"></p>",
			'<img src="a.png">',
			'<img src="a.png" alt="A cat">'
		);

		$this->assertStringContainsString( 'alt="A cat"', $result );
	}

	/**
	 * Multi-byte content survives the round trip.
	 *
	 * @return void
	 */
	public function test_multibyte_content_is_preserved(): void {
		$result = Substitution::replace(
			'<p>Zurück <img src="naive.png"> weiter</p>',
			'<img src="naive.png">',
			'<img src="naive.png" alt="Beschreibung">'
		);

		$this->assertStringContainsString( 'Zurück', $result );
		$this->assertStringContainsString( 'alt="Beschreibung"', $result );
	}

	/**
	 * Empty inputs are no-ops.
	 *
	 * @return void
	 */
	public function test_empty_inputs_are_no_ops(): void {
		$this->assertSame( '<p>x</p>', Substitution::replace( '<p>x</p>', '', 'anything' ) );
		$this->assertSame( '<p>x</p>', Substitution::replace( '<p>x</p>', '<img src="a">', '' ) );
	}

	/**
	 * Locatable reports whether an override could apply at all.
	 *
	 * @return void
	 */
	public function test_locatable_reports_whether_a_fix_can_apply(): void {
		$this->assertTrue( Substitution::locatable( '<p><img src="a.png"></p>', '<img src="a.png">' ) );
		$this->assertFalse( Substitution::locatable( '<p>no images here</p>', '<img src="a.png">' ) );
	}
}
