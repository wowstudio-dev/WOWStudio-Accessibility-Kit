<?php
/**
 * Document tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use DOMElement;
use WOWStudio\AccessibilityKit\Scanner\Document;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests DOM parsing and accessible-name computation.
 *
 * @covers \WOWStudio\AccessibilityKit\Scanner\Document
 */
final class DocumentTest extends TestCase {

	/**
	 * Parses markup and returns the first matching element.
	 *
	 * @param string $html       Markup.
	 * @param string $expression XPath.
	 * @return array{0: Document, 1: DOMElement}
	 */
	private function element( string $html, string $expression ): array {
		$document = Document::from_html( $html );

		$this->assertNotNull( $document );

		$element = $document->first( $expression );

		$this->assertInstanceOf( DOMElement::class, $element );

		return array( $document, $element );
	}

	/**
	 * The aria-labelledby attribute wins over everything else.
	 *
	 * @return void
	 */
	public function test_accessible_name_prefers_aria_labelledby(): void {
		list( $document, $element ) = $this->element(
			'<span id="t">From labelledby</span><button aria-labelledby="t" aria-label="From label">Text</button>',
			'//button'
		);

		$this->assertSame( 'From labelledby', $document->accessible_name( $element ) );
	}

	/**
	 * The aria-label attribute wins over contained text.
	 *
	 * @return void
	 */
	public function test_accessible_name_prefers_aria_label_over_text(): void {
		list( $document, $element ) = $this->element( '<button aria-label="Close dialog">X</button>', '//button' );

		$this->assertSame( 'Close dialog', $document->accessible_name( $element ) );
	}

	/**
	 * A nested image's alt text names its link.
	 *
	 * @return void
	 */
	public function test_accessible_name_uses_nested_image_alt(): void {
		list( $document, $element ) = $this->element( '<a href="/"><img src="/logo.png" alt="Home"></a>', '//a' );

		$this->assertSame( 'Home', $document->accessible_name( $element ) );
	}

	/**
	 * The title attribute is the last resort.
	 *
	 * @return void
	 */
	public function test_accessible_name_falls_back_to_title(): void {
		list( $document, $element ) = $this->element( '<a href="/" title="Home page"></a>', '//a' );

		$this->assertSame( 'Home page', $document->accessible_name( $element ) );
	}

	/**
	 * Whitespace-only content is not a name.
	 *
	 * @return void
	 */
	public function test_whitespace_is_not_a_name(): void {
		list( $document, $element ) = $this->element( "<button>  \n\t </button>", '//button' );

		$this->assertSame( '', $document->accessible_name( $element ) );
	}

	/**
	 * Hiding is inherited from ancestors.
	 *
	 * @return void
	 */
	public function test_hidden_state_is_inherited(): void {
		list( $document, $element ) = $this->element( '<div hidden><span><img src="/a.png"></span></div>', '//img' );

		$this->assertTrue( $document->is_hidden( $element ) );
	}

	/**
	 * A presentation role also removes an element from the accessibility tree.
	 *
	 * @return void
	 */
	public function test_presentation_role_counts_as_hidden(): void {
		list( $document, $element ) = $this->element( '<div role="presentation"><img src="/a.png"></div>', '//img' );

		$this->assertTrue( $document->is_hidden( $element ) );
	}

	/**
	 * A whole page is told apart from a fragment.
	 *
	 * @return void
	 */
	public function test_full_page_is_distinguished_from_a_fragment(): void {
		$page = Document::from_html( '<html><body><p>Hi</p></body></html>' );
		$part = Document::from_html( '<p>Hi</p>' );

		$this->assertNotNull( $page );
		$this->assertNotNull( $part );
		$this->assertTrue( $page->is_full_page() );
		$this->assertFalse( $part->is_full_page() );
	}

	/**
	 * XPath literals survive both quote characters.
	 *
	 * An id attribute containing quotes must not be able to break out of the
	 * expression it is interpolated into.
	 *
	 * @return void
	 */
	public function test_xpath_quoting_handles_both_quote_styles(): void {
		$this->assertSame( "'plain'", Document::quote( 'plain' ) );
		$this->assertSame( '"it\'s"', Document::quote( "it's" ) );
		$this->assertStringStartsWith( 'concat(', Document::quote( 'it\'s "quoted"' ) );
	}

	/**
	 * A quoted value with both quote styles still resolves in a real query.
	 *
	 * @return void
	 */
	public function test_quoted_value_resolves_in_a_query(): void {
		$id       = 'it\'s "odd"';
		$document = Document::from_html( '<span id=\'it&apos;s "odd"\'>Found</span>' );

		$this->assertNotNull( $document );

		$found = $document->first( sprintf( '//*[@id=%s]', Document::quote( $id ) ) );

		$this->assertInstanceOf( DOMElement::class, $found );
		$this->assertSame( 'Found', $document->text_of( $found ) );
	}

	/**
	 * Stored markup snippets are capped.
	 *
	 * @return void
	 */
	public function test_context_is_truncated(): void {
		list( $document, $element ) = $this->element( '<p>' . str_repeat( 'word ', 400 ) . '</p>', '//p' );

		$context = $document->context_for( $element );

		$this->assertLessThanOrEqual( 501, mb_strlen( $context, 'UTF-8' ) );
		$this->assertStringEndsWith( '…', $context );
	}
}
