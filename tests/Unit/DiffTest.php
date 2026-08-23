<?php
/**
 * Diff tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use WOWStudio\AccessibilityKit\Remediation\Diff;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests the comparison a reviewer reads before approving anything.
 *
 * @covers \WOWStudio\AccessibilityKit\Remediation\Diff
 */
final class DiffTest extends TestCase {

	/**
	 * Joins the segments of one kind back into a string.
	 *
	 * @param array<int, array{type: string, value: string}> $segments Diff output.
	 * @param string                                         $type     Segment type.
	 * @return string
	 */
	private function collect( array $segments, string $type ): string {
		$parts = array_filter( $segments, static fn( array $s ): bool => $s['type'] === $type );

		return implode( '', array_column( $parts, 'value' ) );
	}

	/**
	 * Identical markup produces no changes.
	 *
	 * @return void
	 */
	public function test_identical_markup_has_no_changes(): void {
		$segments = Diff::compare( '<img src="a.png">', '<img src="a.png">' );

		$this->assertSame( '', $this->collect( $segments, 'added' ) );
		$this->assertSame( '', $this->collect( $segments, 'removed' ) );
		$this->assertFalse( Diff::differ( '<p>x</p>', '<p>x</p>' ) );
	}

	/**
	 * An added attribute shows up as an addition, and nothing else moves.
	 *
	 * The common case: one attribute appears and the rest of the element is
	 * untouched. A reviewer must be able to see that at a glance.
	 *
	 * @return void
	 */
	public function test_an_added_attribute_is_the_only_change(): void {
		$segments = Diff::compare(
			'<img src="/cat.png" class="wp-image-4">',
			'<img src="/cat.png" alt="A sleeping cat" class="wp-image-4">'
		);

		$this->assertStringContainsString( 'alt="A sleeping cat"', $this->collect( $segments, 'added' ) );
		$this->assertSame( '', $this->collect( $segments, 'removed' ) );
		$this->assertStringContainsString( 'src="/cat.png"', $this->collect( $segments, 'same' ) );
	}

	/**
	 * A replaced value reports both halves.
	 *
	 * @return void
	 */
	public function test_a_replaced_value_reports_both_sides(): void {
		$segments = Diff::compare( '<a href="/x">read more</a>', '<a href="/x">read our privacy notice</a>' );

		$this->assertStringContainsString( 'read', $this->collect( $segments, 'same' ) );
		$this->assertStringContainsString( 'privacy', $this->collect( $segments, 'added' ) );
		$this->assertStringContainsString( 'more', $this->collect( $segments, 'removed' ) );
	}

	/**
	 * Reassembling the unchanged and removed parts gives back the original.
	 *
	 * If this did not hold, the diff would be misrepresenting what is there.
	 *
	 * @return void
	 */
	public function test_the_diff_reconstructs_both_inputs(): void {
		$before = '<button class="menu-toggle"></button>';
		$after  = '<button class="menu-toggle" aria-label="Open menu"></button>';

		$segments = Diff::compare( $before, $after );

		$original = implode(
			'',
			array_column(
				array_filter( $segments, static fn( array $s ): bool => 'added' !== $s['type'] ),
				'value'
			)
		);
		$proposed = implode(
			'',
			array_column(
				array_filter( $segments, static fn( array $s ): bool => 'removed' !== $s['type'] ),
				'value'
			)
		);

		$this->assertSame( $before, $original );
		$this->assertSame( $after, $proposed );
	}

	/**
	 * Whitespace-only differences still count as a change.
	 *
	 * @return void
	 */
	public function test_trailing_whitespace_alone_is_not_a_change(): void {
		$this->assertFalse( Diff::differ( '<p>x</p>', "  <p>x</p>\n" ) );
	}
}
