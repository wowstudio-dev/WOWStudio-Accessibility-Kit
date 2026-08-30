<?php
/**
 * Tests for the managed block of Additional CSS.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Functions;
use WOWStudio\AccessibilityKit\Remediation\CustomCss;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests writing into a stylesheet somebody else owns.
 *
 * Almost every case here is about what must *not* change. This class edits the
 * site's own Additional CSS, which may contain work nobody on this project has
 * seen and which the owner can only recover by hand. Losing a line of it would
 * be a worse failure than never shipping the feature, so the tests are weighted
 * accordingly: the happy path gets a few, and everything that could eat
 * somebody's stylesheet gets the rest.
 *
 * @covers \WOWStudio\AccessibilityKit\Remediation\CustomCss
 */
final class CustomCssTest extends TestCase {

	/**
	 * Stand-in for the stored Additional CSS.
	 *
	 * @var string
	 */
	private string $stored = '';

	/**
	 * Wires the two core functions this class uses.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->stored = '';

		Functions\when( 'wp_get_custom_css' )->alias( fn(): string => $this->stored );
		Functions\when( 'wp_update_custom_css_post' )->alias(
			function ( string $css ) {
				$this->stored = $css;

				return (object) array( 'ID' => 1 );
			}
		);
		Functions\when( 'is_wp_error' )->justReturn( false );
	}

	/**
	 * Everything the site owner wrote, as a stylesheet they would recognise.
	 *
	 * @return string
	 */
	private function theirs(): string {
		return ".site-header {\n\tcolor: #123456;\n}\n\n/* Their own note. */\n.footer a:hover {\n\ttext-decoration: underline;\n}";
	}

	/**
	 * A first rule is added without disturbing what was already there.
	 *
	 * @return void
	 */
	public function test_a_rule_is_added_below_existing_css(): void {
		$this->stored = $this->theirs();

		$css = new CustomCss();
		$this->assertTrue( $css->put( 12, '.entry a { color: #1a4d8f; }' ) );

		$this->assertStringContainsString( $this->theirs(), $this->stored, 'Their CSS must survive byte for byte.' );
		$this->assertStringContainsString( '.entry a { color: #1a4d8f; }', $this->stored );
		$this->assertSame( array( 12 => '.entry a { color: #1a4d8f; }' ), $css->rules() );
	}

	/**
	 * Writing again replaces our block rather than stacking another one.
	 *
	 * @return void
	 */
	public function test_writing_again_replaces_the_block(): void {
		$this->stored = $this->theirs();

		$css = new CustomCss();
		$css->put( 12, '.entry a { color: #111111; }' );
		$css->put( 12, '.entry a { color: #222222; }' );

		$this->assertSame( 1, substr_count( $this->stored, CustomCss::START ) );
		$this->assertStringNotContainsString( '#111111', $this->stored );
		$this->assertStringContainsString( '#222222', $this->stored );
		$this->assertStringContainsString( $this->theirs(), $this->stored );
	}

	/**
	 * Several rules coexist and are individually removable.
	 *
	 * @return void
	 */
	public function test_rules_are_removed_one_at_a_time(): void {
		$css = new CustomCss();
		$css->put( 1, '.a { color: #111111; }' );
		$css->put( 2, '.b { color: #222222; }' );
		$css->put( 3, '.c { color: #333333; }' );

		$this->assertCount( 3, $css->rules() );

		$css->remove( 2 );

		$rules = $css->rules();
		$this->assertSame( array( 1, 3 ), array_keys( $rules ) );
		$this->assertStringNotContainsString( '#222222', $this->stored );
		$this->assertStringContainsString( '#111111', $this->stored );
		$this->assertStringContainsString( '#333333', $this->stored );
	}

	/**
	 * Removing the last rule takes the block away and leaves their CSS.
	 *
	 * @return void
	 */
	public function test_removing_the_last_rule_leaves_only_their_css(): void {
		$this->stored = $this->theirs();

		$css = new CustomCss();
		$css->put( 1, '.a { color: #111111; }' );
		$css->remove( 1 );

		$this->assertStringNotContainsString( CustomCss::START, $this->stored );
		$this->assertSame( $this->theirs(), $this->stored );
	}

	/**
	 * An empty stylesheet gains a block and nothing else.
	 *
	 * @return void
	 */
	public function test_an_empty_stylesheet_is_handled(): void {
		$css = new CustomCss();
		$css->put( 5, '.a { color: #111111; }' );

		$this->assertStringStartsWith( CustomCss::START, $this->stored );
		$this->assertStringEndsWith( CustomCss::END, $this->stored );
	}

	/**
	 * A start marker with no end is left alone entirely.
	 *
	 * The case that could eat a stylesheet. Treating an unclosed marker as
	 * "everything after this is ours" would delete every rule the owner wrote
	 * below it. Appending a second block is untidy and recoverable; deleting
	 * their work is neither.
	 *
	 * @return void
	 */
	public function test_an_unclosed_marker_is_never_treated_as_a_block(): void {
		$this->stored = $this->theirs() . "\n" . CustomCss::START . "\n.orphan { color: red; }\n.more-of-theirs { color: blue; }";

		$css = new CustomCss();

		$this->assertSame( array(), $css->rules(), 'A malformed block contains no rules we may claim.' );

		$css->put( 9, '.new { color: #333333; }' );

		$this->assertStringContainsString( '.orphan { color: red; }', $this->stored );
		$this->assertStringContainsString( '.more-of-theirs { color: blue; }', $this->stored );
		$this->assertStringContainsString( $this->theirs(), $this->stored );
	}

	/**
	 * An end marker appearing before a start marker is not a block either.
	 *
	 * @return void
	 */
	public function test_markers_in_the_wrong_order_are_ignored(): void {
		$this->stored = CustomCss::END . "\n.theirs { color: red; }\n" . CustomCss::START;

		$css = new CustomCss();

		$this->assertSame( array(), $css->rules() );

		$css->put( 4, '.new { color: #444444; }' );

		$this->assertStringContainsString( '.theirs { color: red; }', $this->stored );
	}

	/**
	 * Marker text inside a string is not a marker.
	 *
	 * A stylesheet is allowed to contain our words. Matching them anywhere
	 * would mean rewriting a region of somebody's CSS chosen by coincidence.
	 *
	 * @return void
	 */
	public function test_marker_text_inside_a_declaration_is_not_a_boundary(): void {
		$this->stored = '.thing::before { content: "' . CustomCss::START . '"; }';

		$css = new CustomCss();

		$this->assertSame( array(), $css->rules() );

		$css->put( 7, '.new { color: #777777; }' );

		$this->assertStringContainsString( '.thing::before', $this->stored );
		$this->assertStringContainsString( 'content: "', $this->stored );
	}

	/**
	 * Their CSS is preserved exactly, including blank lines and comments.
	 *
	 * @return void
	 */
	public function test_their_formatting_is_never_normalised(): void {
		$this->stored = "/* keep */\n\n\n.a    {   color:red   }\n\n/* keep too */";

		$css = new CustomCss();
		$css->put( 1, '.b { color: #111111; }' );
		$css->remove( 1 );

		$this->assertSame( "/* keep */\n\n\n.a    {   color:red   }\n\n/* keep too */", $this->stored );
	}

	/**
	 * Rules keep a stable order regardless of the order they arrived in.
	 *
	 * Otherwise every write reshuffles the block and the diff a user reviews is
	 * mostly noise.
	 *
	 * @return void
	 */
	public function test_rules_are_written_in_a_stable_order(): void {
		$css = new CustomCss();
		$css->put( 30, '.c { color: #333333; }' );
		$css->put( 10, '.a { color: #111111; }' );
		$css->put( 20, '.b { color: #222222; }' );

		$this->assertSame( array( 10, 20, 30 ), array_keys( $css->rules() ) );
		$this->assertLessThan( strpos( $this->stored, '#222222' ), strpos( $this->stored, '#111111' ) );
		$this->assertLessThan( strpos( $this->stored, '#333333' ), strpos( $this->stored, '#222222' ) );
	}
}
