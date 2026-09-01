<?php
/**
 * Tests for seeding a content-only scan with what the theme contributes.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use WOWStudio\AccessibilityKit\Scanner\Engine;
use WOWStudio\AccessibilityKit\Scanner\TemplateProfile;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests the two rules that judge content against what came before it.
 *
 * These are the reason the template profile exists. Scanning post content alone
 * is blind to the theme, and for these two that blindness is not neutral: it
 * makes them miss the commonest real faults on exactly the well-built themes
 * where the remaining problems are subtle.
 *
 * Every case is run three ways — whole page, content with a profile, content
 * without one — because the point is not that the profile changes the answer
 * but that it changes it *to the one the whole page gives*.
 *
 * @covers \WOWStudio\AccessibilityKit\Scanner\TemplateProfile
 * @covers \WOWStudio\AccessibilityKit\Scanner\Rules\HeadingLevelSkipped
 * @covers \WOWStudio\AccessibilityKit\Scanner\Rules\MultipleTopHeadings
 */
final class TemplateProfileTest extends TestCase {

	/**
	 * A profile describing a theme that prints the title as an h1.
	 *
	 * @return TemplateProfile
	 */
	private function typical_theme(): TemplateProfile {
		return new TemplateProfile( true, 1, 1, true, true, true, 'twentytwentyfive', '1.0', '2026-09-01 00:00:00' );
	}

	/**
	 * Returns the rule IDs a scan produced.
	 *
	 * @param string               $html    Markup to scan.
	 * @param TemplateProfile|null $profile Theme knowledge.
	 * @return string[]
	 */
	private function scan( string $html, ?TemplateProfile $profile = null ): array {
		$result = ( new Engine() )->scan( $html, $profile );

		$this->assertNotNull( $result );

		return array_map( static fn( $finding ): string => $finding->rule_id, $result->findings );
	}

	/**
	 * A post opening at h3 under the theme's h1 is a real skipped level.
	 *
	 * @return void
	 */
	public function test_a_content_only_scan_finds_a_skip_it_would_otherwise_miss(): void {
		$whole_page = '<html lang="en"><head><title>T</title></head><body><main><h1>The title</h1><h3>A section</h3></main></body></html>';
		$content    = '<h3>A section</h3>';

		$this->assertContains( 'heading-level-skipped', $this->scan( $whole_page ) );
		$this->assertContains(
			'heading-level-skipped',
			$this->scan( $content, $this->typical_theme() ),
			'With the theme known, the content-only scan reaches the same verdict as the whole page.'
		);
		$this->assertNotContains(
			'heading-level-skipped',
			$this->scan( $content ),
			'Without a profile it stays quiet, which is the behaviour this replaced.'
		);
	}

	/**
	 * An h1 in content is a second top-level heading when the theme printed one.
	 *
	 * @return void
	 */
	public function test_a_content_only_scan_finds_a_second_top_heading(): void {
		$whole_page = '<html lang="en"><head><title>T</title></head><body><main><h1>The title</h1><h1>Another</h1></main></body></html>';
		$content    = '<h1>Another</h1>';

		$this->assertContains( 'heading-multiple-h1', $this->scan( $whole_page ) );
		$this->assertContains( 'heading-multiple-h1', $this->scan( $content, $this->typical_theme() ) );
		$this->assertNotContains( 'heading-multiple-h1', $this->scan( $content ) );
	}

	/**
	 * Content that follows on properly is still not reported.
	 *
	 * The failure that would matter most: a profile that turned every
	 * well-formed post into a finding would be worse than the under-reporting
	 * it replaced, because it would be wrong rather than merely quiet.
	 *
	 * @return void
	 */
	public function test_well_formed_content_is_not_reported(): void {
		$this->assertNotContains(
			'heading-level-skipped',
			$this->scan( '<h2>A section</h2><p>Words.</p><h3>Deeper</h3>', $this->typical_theme() )
		);
		$this->assertNotContains(
			'heading-multiple-h1',
			$this->scan( '<h2>A section</h2><h3>Deeper</h3>', $this->typical_theme() )
		);
	}

	/**
	 * A profile is only applied to a fragment, never to a whole page.
	 *
	 * A whole page carries its own theme. Seeding it as well would count the
	 * theme's heading twice and invent a finding on the first real h1.
	 *
	 * @return void
	 */
	public function test_a_whole_page_ignores_the_profile(): void {
		$whole_page = '<html lang="en"><head><title>T</title></head><body><main><h1>The only title</h1><h2>A section</h2></main></body></html>';

		$this->assertNotContains( 'heading-multiple-h1', $this->scan( $whole_page, $this->typical_theme() ) );
		$this->assertNotContains( 'heading-level-skipped', $this->scan( $whole_page, $this->typical_theme() ) );
	}

	/**
	 * A theme with no top-level heading changes nothing.
	 *
	 * @return void
	 */
	public function test_a_theme_without_a_top_heading_seeds_nothing(): void {
		$profile = new TemplateProfile( true, 0, 0, true, true, true, 'bare', '1.0', '2026-09-01 00:00:00' );

		$this->assertNotContains( 'heading-level-skipped', $this->scan( '<h3>A section</h3>', $profile ) );
		$this->assertNotContains( 'heading-multiple-h1', $this->scan( '<h1>A title</h1>', $profile ) );
	}

	/**
	 * The page-level rules keep abstaining on a fragment, profile or not.
	 *
	 * The profile knows the theme has a title, a language and a landmark. That
	 * must not become a licence to report on markup this scan never saw.
	 *
	 * @return void
	 */
	public function test_page_level_rules_still_abstain_on_a_fragment(): void {
		$found = $this->scan( '<p>Just some words.</p>', $this->typical_theme() );

		$this->assertNotContains( 'html-lang-missing', $found );
		$this->assertNotContains( 'document-title-missing', $found );
		$this->assertNotContains( 'landmark-main-missing', $found );
	}

	/**
	 * A profile that does not describe the current theme is not used.
	 *
	 * @return void
	 */
	public function test_a_profile_only_speaks_for_the_theme_it_measured(): void {
		$profile = $this->typical_theme();

		$this->assertTrue( $profile->describes( 'twentytwentyfive', '1.0' ) );
		$this->assertFalse( $profile->describes( 'twentytwentyfive', '1.1' ), 'A theme update invalidates it.' );
		$this->assertFalse( $profile->describes( 'storefront', '1.0' ), 'A theme switch invalidates it.' );
		$this->assertFalse( TemplateProfile::unknown()->describes( 'twentytwentyfive', '1.0' ) );
	}

	/**
	 * An unknown profile asserts nothing.
	 *
	 * @return void
	 */
	public function test_an_unknown_profile_asserts_nothing(): void {
		$unknown = TemplateProfile::unknown();

		$this->assertSame( 0, $unknown->heading_context() );
		$this->assertSame( 0, $unknown->top_headings_before_content() );
	}

	/**
	 * A profile survives being stored and read back.
	 *
	 * @return void
	 */
	public function test_a_profile_round_trips(): void {
		$original = $this->typical_theme();
		$restored = TemplateProfile::from_array( $original->to_array() );

		$this->assertEquals( $original, $restored );
	}

	/**
	 * A stored payload that never held a profile reads as unknown.
	 *
	 * @return void
	 */
	public function test_an_empty_payload_reads_as_unknown(): void {
		$this->assertFalse( TemplateProfile::from_array( array() )->known );
	}
}
