<?php
/**
 * Next-step guidance tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use WOWStudio\AccessibilityKit\Guidance\NextStep;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests what the dashboard suggests doing next.
 *
 * Half of these are about it saying nothing. A panel that always has something
 * to say becomes furniture within a week and is then ignored on the day it
 * matters, so silence is a feature with its own tests rather than an accident.
 *
 * @covers \WOWStudio\AccessibilityKit\Guidance\NextStep
 */
final class NextStepTest extends TestCase {

	/**
	 * Builds a NextStep whose collaborators answer with fixed numbers.
	 *
	 * @param int $fixes_off   How many site fixes are switched off.
	 * @param int $undescribed How many images have never been described.
	 * @return NextStep
	 */
	private function guidance( int $fixes_off, int $undescribed ): NextStep {
		return new NextStep(
			static fn (): int => $fixes_off,
			static fn (): int => $undescribed
		);
	}

	/**
	 * Builds an overview payload.
	 *
	 * @param int $scanned Pages scanned.
	 * @param int $open    Findings open.
	 * @return array<string, mixed>
	 */
	private function overview( int $scanned, int $open ): array {
		return array(
			'scanned' => array( 'pages' => $scanned ),
			'issues'  => array( 'open' => $open ),
		);
	}

	/**
	 * A site with nothing scanned is told to scan something.
	 *
	 * The first-run cliff this exists for: an empty dashboard that reports the
	 * absence and leaves the reader to work out what to do about it.
	 *
	 * @return void
	 */
	public function test_a_fresh_site_is_told_where_to_start(): void {
		$step = $this->guidance( 14, 100 )->for_overview( $this->overview( 0, 0 ) );

		$this->assertIsArray( $step );
		$this->assertSame( 'scan', $step['id'] );
		$this->assertSame( 'scan', $step['view'] );
		$this->assertNotSame( '', $step['label'] );
	}

	/**
	 * Scanning comes before everything else.
	 *
	 * Even with fixes off and a hundred undescribed images waiting, somebody
	 * who has never scanned anything has no idea what this plugin reports, and
	 * sending them to a settings screen first is answering a question they have
	 * not asked yet.
	 *
	 * @return void
	 */
	public function test_scanning_outranks_the_other_suggestions(): void {
		$step = $this->guidance( 14, 500 )->for_overview( $this->overview( 0, 999 ) );

		$this->assertSame( 'scan', $step['id'] );
	}

	/**
	 * Once scanned, unswitched fixes are the next thing.
	 *
	 * @return void
	 */
	public function test_fixes_come_next(): void {
		$step = $this->guidance( 6, 40 )->for_overview( $this->overview( 5, 20 ) );

		$this->assertSame( 'fixes', $step['id'] );
		$this->assertStringContainsString( '6', $step['body'] );
	}

	/**
	 * With every fix on, undescribed images are the suggestion.
	 *
	 * @return void
	 */
	public function test_images_come_after_the_fixes(): void {
		$step = $this->guidance( 0, 42 )->for_overview( $this->overview( 5, 20 ) );

		$this->assertSame( 'images', $step['id'] );
		$this->assertStringContainsString( '42', $step['body'] );
	}

	/**
	 * With nothing else outstanding, it points at the open findings.
	 *
	 * @return void
	 */
	public function test_open_findings_are_the_last_suggestion(): void {
		$step = $this->guidance( 0, 0 )->for_overview( $this->overview( 5, 7 ) );

		$this->assertSame( 'review', $step['id'] );
		$this->assertStringContainsString( '7', $step['body'] );
	}

	/**
	 * With nothing to suggest it says nothing at all.
	 *
	 * And says nothing rather than congratulating: a clean automated scan means
	 * the checks passed, which is a far smaller statement than the site being
	 * usable, and the empty dashboard is the last place that should blur the
	 * two.
	 *
	 * @return void
	 */
	public function test_it_goes_quiet_rather_than_congratulating(): void {
		$this->assertNull(
			$this->guidance( 0, 0 )->for_overview( $this->overview( 12, 0 ) ),
			'With nothing worth suggesting, the panel must not appear at all.'
		);
	}

	/**
	 * Only ever one step, never a checklist.
	 *
	 * @return void
	 */
	public function test_it_offers_one_thing_and_not_a_list(): void {
		$step = $this->guidance( 6, 42 )->for_overview( $this->overview( 5, 20 ) );

		$this->assertIsArray( $step );
		$this->assertSame(
			array( 'id', 'title', 'body', 'label', 'view' ),
			array_keys( $step ),
			'A step is one action. Anything shaped like a list of them belongs somewhere else.'
		);
	}
}
