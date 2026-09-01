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
		return new TemplateProfile( true, 1, 1, true, true, true, 'twentytwentyfive', '1.0', gmdate( 'Y-m-d H:i:s' ) );
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
		$profile = new TemplateProfile( true, 0, 0, true, true, true, 'bare', '1.0', gmdate( 'Y-m-d H:i:s' ) );

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
	 * Returns the findings with their detection, so inference can be told apart.
	 *
	 * @param string               $html    Markup to scan.
	 * @param TemplateProfile|null $profile Theme knowledge.
	 * @return array<int, array{rule: string, detection: string}>
	 */
	private function detailed( string $html, ?TemplateProfile $profile = null ): array {
		$result = ( new Engine() )->scan( $html, $profile );

		$this->assertNotNull( $result );

		return array_map(
			static fn( $finding ): array => array(
				'rule'      => $finding->rule_id,
				'detection' => $finding->detection->value,
				'message'   => $finding->message,
			),
			$result->findings
		);
	}

	/**
	 * A finding owed only to the profile is offered, never asserted.
	 *
	 * The bug this exists for, found by testing the plugin against three real
	 * page-builder pages. A page with exactly one top-level heading — correct
	 * structure — was reported as having a duplicate, because a profile measured
	 * under unrepresentative conditions claimed the theme contributed one.
	 *
	 * A profile is a measurement of a sample of one page. When it is wrong this
	 * rule used to invent a finding about a page that was perfectly fine, which
	 * is the one failure decision F9 said must never happen: under-reporting is
	 * recoverable and inventing is not.
	 *
	 * @return void
	 */
	public function test_a_finding_owed_only_to_the_profile_says_so(): void {
		$found = $this->detailed( '<h1>The only heading in this content</h1>', $this->typical_theme() );

		$h1 = array_values(
			array_filter( $found, static fn( array $f ): bool => 'heading-multiple-h1' === $f['rule'] )
		);

		$this->assertCount( 1, $h1, 'It is still reported — the profile may well be right.' );
		$this->assertSame( 'manual', $h1[0]['detection'] );

		// This rule was already manual, so the detection tag was never the
		// problem. The message was: "This is top-level heading number 2 on the
		// page" states as fact a count that includes a heading measured from a
		// sample of the theme, which the reader cannot see in their own content
		// and which may simply be wrong.
		$this->assertStringContainsString( 'Your theme appears to', $h1[0]['message'] );
		$this->assertStringNotContainsString( 'number 2 on the page', $h1[0]['message'] );
	}

	/**
	 * A second heading in the content stands on its own evidence.
	 *
	 * The profile is irrelevant here: two top-level headings in one post are a
	 * duplicate whatever the theme does.
	 *
	 * @return void
	 */
	public function test_a_finding_the_content_settles_states_it_plainly(): void {
		$found = $this->detailed(
			'<h1>First</h1><h1>Second</h1>',
			$this->typical_theme()
		);

		$h1 = array_values(
			array_filter( $found, static fn( array $f ): bool => 'heading-multiple-h1' === $f['rule'] )
		);

		$this->assertCount( 2, $h1 );
		$this->assertStringContainsString( 'Your theme appears to', $h1[0]['message'], 'The first rests on the profile.' );
		$this->assertStringContainsString( 'number 3 on the page', $h1[1]['message'], 'The second is settled by the content itself.' );
	}

	/**
	 * The same distinction for skipped heading levels.
	 *
	 * @return void
	 */
	public function test_a_skip_measured_from_the_profile_is_not_asserted(): void {
		$inferred = $this->detailed( '<h3>Opens at level three</h3>', $this->typical_theme() );
		$settled  = $this->detailed( '<h2>Two</h2><p>Words.</p><h4>Four</h4>', $this->typical_theme() );

		$first = array_values(
			array_filter( $inferred, static fn( array $f ): bool => 'heading-level-skipped' === $f['rule'] )
		);
		$later = array_values(
			array_filter( $settled, static fn( array $f ): bool => 'heading-level-skipped' === $f['rule'] )
		);

		$this->assertCount( 1, $first );
		$this->assertSame( 'manual', $first[0]['detection'] );

		$this->assertCount( 1, $later );
		$this->assertSame( 'auto', $later[0]['detection'], 'h2 to h4 is a gap the content itself shows.' );
	}

	/**
	 * An inferred finding costs nothing against the score.
	 *
	 * Following from the above: the score counts only what a scan settled, so a
	 * wrong profile can no longer drag a page's number down.
	 *
	 * @return void
	 */
	public function test_an_inferred_finding_does_not_move_the_score(): void {
		// The heading-skip rule is Detection::Auto, so before this change a
		// profile-seeded finding counted against the score as though the scan
		// had settled it. That is the one that mattered: a wrong profile could
		// silently take points off a page whose headings are fine.
		$result = ( new Engine() )->scan( '<h3>Opens at level three</h3>', $this->typical_theme() );

		$this->assertNotNull( $result );
		$this->assertSame( 100, $result->score() );
	}

	/**
	 * A measurement too old to trust is not used.
	 *
	 * The method promised this expiry in its own docblock from the day it was
	 * written, and never did it. A profile measured once was believed for ever,
	 * so a reading taken while a caching plugin or a maintenance screen was in
	 * the way could bias every scan on the site indefinitely.
	 *
	 * @return void
	 */
	public function test_an_old_profile_is_not_trusted(): void {
		$fresh   = new TemplateProfile( true, 1, 1, true, true, true, 'x', '1.0', gmdate( 'Y-m-d H:i:s' ) );
		$old     = new TemplateProfile( true, 1, 1, true, true, true, 'x', '1.0', gmdate( 'Y-m-d H:i:s', time() - ( TemplateProfile::MAX_AGE_DAYS + 1 ) * DAY_IN_SECONDS ) );
		$undated = new TemplateProfile( true, 1, 1, true, true, true, 'x', '1.0', '' );

		$this->assertTrue( $fresh->describes( 'x', '1.0' ) );
		$this->assertFalse( $old->describes( 'x', '1.0' ), 'An old measurement is re-taken rather than trusted.' );
		$this->assertFalse( $undated->describes( 'x', '1.0' ), 'No date means no provenance.' );
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
