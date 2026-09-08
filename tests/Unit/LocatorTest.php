<?php
/**
 * Saying where a finding is, in terms a person can use.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use WOWStudio\AccessibilityKit\Scanner\Locator;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests the human-readable half of a finding's location.
 *
 * @covers \WOWStudio\AccessibilityKit\Scanner\Locator
 */
final class LocatorTest extends TestCase {

	/**
	 * The element and its words, which is what somebody is looking at.
	 *
	 * The markup here is the one from the report a tester found unreadable: the
	 * only location on the card was
	 * /html/body/main/div/div[2]/div/div/div/div/div/div/div/div[1]/div/h3/span,
	 * which cannot tell anybody whether the finding is about their headline or
	 * their cookie banner.
	 *
	 * @return void
	 */
	public function test_it_names_the_element_and_quotes_its_words(): void {
		$this->assertSame(
			'span.first-title — “About the Conference”',
			Locator::describe( '<span class="first-title">About the Conference</span>' )
		);
	}

	/**
	 * An id is more use than a class, so it wins.
	 *
	 * @return void
	 */
	public function test_an_id_is_preferred_to_a_class(): void {
		$this->assertSame(
			'h2#intro — “Hello”',
			Locator::describe( '<h2 id="intro" class="title big">Hello</h2>' )
		);
	}

	/**
	 * One class, not the whole utility soup.
	 *
	 * A dozen classes is the same wall of noise the XPath was, only wider.
	 *
	 * @return void
	 */
	public function test_only_the_first_class_is_used(): void {
		$this->assertSame(
			'div.card — “Text”',
			Locator::describe( '<div class="card mt-4 flex items-center justify-between gap-2">Text</div>' )
		);
	}

	/**
	 * An image with no description falls back to its file.
	 *
	 * The case where there is nothing else to go on is exactly the missing
	 * description, so the file name is what the author will recognise in the
	 * media library.
	 *
	 * @return void
	 */
	public function test_an_undescribed_image_is_named_by_its_file(): void {
		$this->assertSame(
			'img — “team-photo.jpg”',
			Locator::describe( '<img src="/wp-content/uploads/2026/09/team-photo.jpg?ver=2">' )
		);
	}

	/**
	 * A described image is named by what it says, not what it is called.
	 *
	 * @return void
	 */
	public function test_a_described_image_is_named_by_its_description(): void {
		$this->assertSame(
			'img — “Two students in a library”',
			Locator::describe( '<img src="a.jpg" alt="Two students in a library">' )
		);
	}

	/**
	 * A control with no text is named by whatever names it.
	 *
	 * @return void
	 */
	public function test_a_control_falls_back_to_its_accessible_name(): void {
		$this->assertSame(
			'button.close — “Close dialog”',
			Locator::describe( '<button class="close" aria-label="Close dialog"><svg></svg></button>' )
		);
	}

	/**
	 * Long text is cut rather than allowed to run past the tag it belongs to.
	 *
	 * @return void
	 */
	public function test_long_text_is_shortened(): void {
		$found = Locator::describe(
			'<p>' . str_repeat( 'a very long sentence ', 12 ) . '</p>'
		);

		$this->assertStringStartsWith( 'p — “a very long', $found );
		$this->assertStringEndsWith( '…”', $found );
		$this->assertLessThan( 60, mb_strlen( $found, 'UTF-8' ) );
	}

	/**
	 * Whitespace across lines collapses, as it does on screen.
	 *
	 * @return void
	 */
	public function test_whitespace_is_collapsed(): void {
		$this->assertSame(
			'a — “read more”',
			Locator::describe( "<a href=\"#\">read\n\t  more</a>" )
		);
	}

	/**
	 * An element with nothing to quote is still named.
	 *
	 * @return void
	 */
	public function test_an_element_with_no_words_is_named_anyway(): void {
		$this->assertSame(
			'div.spacer',
			Locator::describe( '<div class="spacer"></div>' )
		);
	}

	/**
	 * A label never contains markup, however mangled the context is.
	 *
	 * Context is truncated to a fixed length when stored, so a long block
	 * arrives cut off mid-tag. Found on real data: a card read
	 * `div.eb-wrapper-outer — "<div class="eb-parent-wrapper e…"`, which is the
	 * same unreadable thing this class exists to replace.
	 *
	 * @return void
	 */
	public function test_a_label_never_contains_markup(): void {
		$found = Locator::describe(
			'<div class="eb-wrapper-outer">&lt;div class="eb-parent-wrapper eb-'
		);

		$this->assertStringNotContainsString( '<', $found );
		$this->assertStringNotContainsString( 'class=', $found );
	}

	/**
	 * Nothing to read means nothing claimed.
	 *
	 * Page-level findings — no headings anywhere, a missing page language —
	 * are about the document rather than an element, and inventing a location
	 * for them would be worse than leaving the field empty.
	 *
	 * @dataProvider unreadable
	 *
	 * @param string $context What was stored.
	 * @return void
	 */
	public function test_unreadable_markup_yields_nothing( string $context ): void {
		$this->assertSame( '', Locator::describe( $context ) );
	}

	/**
	 * Contexts that name no element.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function unreadable(): array {
		return array(
			'empty'      => array( '' ),
			'whitespace' => array( "  \n " ),
			'plain text' => array( 'the page has no headings' ),
		);
	}
}
