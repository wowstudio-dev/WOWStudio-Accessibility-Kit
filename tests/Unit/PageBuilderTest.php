<?php
/**
 * Page-builder compatibility tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Guards the path that lets a page builder's content be scanned at all.
 *
 * The failure this protects against was found by building a real Elementor page
 * and looking: Elementor keeps nothing in `post_content` — a JSON tree in
 * postmeta, rendered through hooks that only fire inside the loop on a real
 * request. The content fallback applied `the_content` to the empty string
 * Elementor leaves behind and got the empty string back, so on any site where
 * loopback is blocked every Elementor page scanned as though it contained
 * nothing. The rendered page was eighty kilobytes; the scanner saw zero.
 *
 * Source-reading, because the alternative is installing Elementor into the unit
 * suite. What is worth guarding is that the fallback exists and is reached,
 * which is the part that can be deleted by accident.
 *
 * @coversNothing
 */
final class PageBuilderTest extends TestCase {

	/**
	 * Reads a file from the plugin root.
	 *
	 * @param string $relative Path relative to the plugin root.
	 * @return string
	 */
	private function source( string $relative ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local source file in a unit test; WordPress is not loaded.
		return (string) file_get_contents( __DIR__ . '/../../' . $relative );
	}

	/**
	 * The builder is asked first, not as a fallback for empty content.
	 *
	 * The ordering is the entire fix and it is easy to reverse by accident,
	 * because asking-when-empty reads like the more conservative choice. It is
	 * not. Elementor writes a stripped text copy of the page into
	 * `post_content` for search engines — on a real home page, 3,808 bytes of
	 * bare headings against 69,503 bytes of page. A fallback that waits for
	 * empty never fires on that, and the scan reports a confident hundred out of
	 * a hundred on a page nobody has really looked at.
	 *
	 * @return void
	 */
	public function test_the_builder_is_asked_before_the_content_filter(): void {
		$source = $this->source( 'src/Scanner/PageSource.php' );

		$this->assertStringContainsString( 'private function builder_content(', $source );

		$builder_call = strpos( $source, '$this->builder_content(' );
		$content_call = strpos( $source, "apply_filters( 'the_content'" );

		$this->assertIsInt( $builder_call );
		$this->assertIsInt( $content_call );
		$this->assertLessThan(
			$content_call,
			$builder_call,
			'The builder has to be asked before the_content, or a builder that leaves a stub behind is scanned as the page.'
		);
	}

	/**
	 * Elementor is handled directly, and defensively.
	 *
	 * Guarded on every hop, because this reaches into another plugin's
	 * internals: a class that may not exist, a property that may not be set,
	 * and a method that may be renamed in a version we have never seen. Any of
	 * those unguarded is a fatal error on somebody's dashboard.
	 *
	 * @return void
	 */
	public function test_elementor_is_reached_without_assuming_it_is_there(): void {
		$source = $this->source( 'src/Scanner/PageSource.php' );

		$this->assertStringContainsString( "class_exists( '\\Elementor\\Plugin' )", $source );
		$this->assertStringContainsString( 'get_builder_content_for_display', $source );
		$this->assertStringContainsString( 'method_exists(', $source );
	}

	/**
	 * Any other builder can supply its content without editing this file.
	 *
	 * @return void
	 */
	public function test_other_builders_have_a_seam(): void {
		$this->assertStringContainsString(
			"apply_filters( 'wsak_builder_content'",
			$this->source( 'src/Scanner/PageSource.php' )
		);
	}

	/**
	 * Finding nothing is reported as finding nothing, with both causes named.
	 *
	 * It used to fall through to the parser and report "the page could not be
	 * parsed as HTML" — true in a narrow sense and useless, because it named
	 * the symptom rather than either of the two things a reader could act on.
	 * The replacement names both possible causes and asserts neither, because
	 * from the server an empty page and a page rendered by something
	 * unreachable look identical.
	 *
	 * @return void
	 */
	public function test_nothing_to_scan_says_so_without_guessing_why(): void {
		$source = $this->source( 'src/Scanner/PageSource.php' );

		$this->assertStringContainsString( 'wsak_no_scannable_content', $source );
		$this->assertStringContainsString( 'content field is empty', $source );
		$this->assertStringContainsString( 'no page builder supplied anything either', $source );
	}
}
