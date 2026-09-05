<?php
/**
 * Scan engine tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use WOWStudio\AccessibilityKit\Scanner\Detection;
use WOWStudio\AccessibilityKit\Scanner\Engine;
use WOWStudio\AccessibilityKit\Scanner\RuleRegistry;
use WOWStudio\AccessibilityKit\Scanner\Finding;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests the engine against a page carrying one instance of every rule.
 *
 * @covers \WOWStudio\AccessibilityKit\Scanner\Engine
 * @covers \WOWStudio\AccessibilityKit\Scanner\Result
 * @covers \WOWStudio\AccessibilityKit\Scanner\Document
 */
final class EngineTest extends TestCase {

	/**
	 * A page with exactly one occurrence of each rule, and clean counterparts
	 * for the cases that must NOT be reported.
	 *
	 * @return string
	 */
	private function fixture(): string {
		return <<<'HTML'
<!DOCTYPE html>
<html>
<head><title></title></head>
<body>
	<h1>First title</h1>
	<h1>Second title</h1>
	<img src="/uploads/cat.png">
	<img src="/uploads/dog.png" alt="A sleeping dog">
	<a href="/empty"></a>
	<a href="/vague">read more</a>
	<a href="/good">Our accessibility statement</a>
	<button></button>
	<button>Save changes</button>
	<input type="text" name="email">
	<input type="text" id="named" name="named"><label for="named">Your name</label>
	<input type="hidden" name="nonce" value="x">
	<iframe src="//example.test/video"></iframe>
	<table><tr><td>1</td></tr></table>
	<table><tr><th scope="col">Year</th></tr><tr><td>1</td></tr></table>
	<h2>Section</h2>
	<h4>Skipped past three</h4>
</body>
</html>
HTML;
	}

	/**
	 * Returns the rule IDs a scan produced.
	 *
	 * @param string $html Markup to scan.
	 * @return string[]
	 */
	private function rule_ids( string $html ): array {
		$result = ( new Engine() )->scan( $html );

		$this->assertNotNull( $result );

		return array_map( static fn( Finding $f ): string => $f->rule_id, $result->findings );
	}

	/**
	 * Every MVP rule fires exactly once on the fixture.
	 *
	 * @return void
	 */
	public function test_every_rule_fires_once(): void {
		$counts = array_count_values( $this->rule_ids( $this->fixture() ) );

		ksort( $counts );

		$this->assertSame(
			array(
				'button-name-missing'        => 1,
				'document-title-missing'     => 1,
				'form-control-label-missing' => 1,
				'heading-level-skipped'      => 1,
				'heading-multiple-h1'        => 1,
				'html-lang-missing'          => 1,
				'iframe-title-missing'       => 1,
				'img-alt-missing'            => 1,
				'landmark-main-missing'      => 1,
				'link-name-missing'          => 1,
				'link-text-not-descriptive'  => 1,
				'table-headers-missing'      => 1,
			),
			$counts
		);
	}

	/**
	 * Correct markup produces nothing.
	 *
	 * A scanner that cries wolf on a good page is worse than no scanner, so
	 * this asserts the clean counterparts in the fixture stay quiet.
	 *
	 * @return void
	 */
	public function test_a_clean_page_produces_no_findings(): void {
		$html = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head><title>A properly built page</title></head>
<body>
	<main>
		<h1>A properly built page</h1>
		<img src="/uploads/dog.png" alt="A sleeping dog">
		<img src="/uploads/spacer.gif" alt="">
		<a href="/statement">Read our accessibility statement</a>
		<button>Save changes</button>
		<label for="email">Email address</label><input type="email" id="email" name="email">
		<label>Postcode <input type="text" name="postcode"></label>
		<iframe src="//example.test/video" title="Introductory video"></iframe>
		<table><tr><th scope="col">Year</th></tr><tr><td>2026</td></tr></table>
		<h2>A section</h2>
		<h3>A subsection</h3>
	</main>
</body>
</html>
HTML;

		$this->assertSame( array(), $this->rule_ids( $html ) );
	}

	/**
	 * An empty alt attribute is a decorative image, not a missing one.
	 *
	 * @return void
	 */
	public function test_empty_alt_is_treated_as_decorative(): void {
		$ids = $this->rule_ids( '<p><img src="/line.gif" alt=""></p>' );

		$this->assertNotContains( 'img-alt-missing', $ids );
	}

	/**
	 * Elements hidden from assistive technology are not reported.
	 *
	 * @return void
	 */
	public function test_hidden_elements_are_skipped(): void {
		$ids = $this->rule_ids( '<div aria-hidden="true"><img src="/a.png"><button></button></div>' );

		$this->assertSame( array(), $ids );
	}

	/**
	 * Document-level rules do not fire on a fragment.
	 *
	 * A fragment legitimately has no html element, title, or main landmark.
	 * Reporting those would be a guaranteed false positive.
	 *
	 * @return void
	 */
	public function test_document_rules_do_not_fire_on_a_fragment(): void {
		$ids = $this->rule_ids( '<p>Just some content.</p><img src="/a.png">' );

		$this->assertSame( array( 'img-alt-missing' ), $ids );
	}

	/**
	 * Only auto-detected findings reduce the score.
	 *
	 * @return void
	 */
	public function test_score_counts_only_auto_detected_findings(): void {
		$result = ( new Engine() )->scan( $this->fixture() );

		$this->assertNotNull( $result );

		// Four critical (10), three serious (6), one moderate (3) = 61.
		$this->assertSame( 39, $result->score() );

		$this->assertSame(
			array(
				Detection::Auto->value   => 8,
				Detection::Manual->value => 4,
			),
			$result->count_by_detection()
		);
	}

	/**
	 * A page with only review items keeps a full score.
	 *
	 * @return void
	 */
	public function test_review_items_alone_do_not_reduce_the_score(): void {
		$result = ( new Engine() )->scan( '<p><a href="/x">click here</a></p>' );

		$this->assertNotNull( $result );
		$this->assertSame( 100, $result->score() );
		$this->assertSame( 1, $result->count_by_detection()[ Detection::Manual->value ] );
	}

	/**
	 * Malformed markup is still scanned rather than rejected.
	 *
	 * @return void
	 */
	public function test_malformed_markup_is_still_scanned(): void {
		$ids = $this->rule_ids( '<div><p>unclosed <img src="/a.png"><span></div>' );

		$this->assertContains( 'img-alt-missing', $ids );
	}

	/**
	 * Empty input yields no result rather than an empty scan.
	 *
	 * @return void
	 */
	public function test_empty_input_returns_null(): void {
		$this->assertNull( ( new Engine() )->scan( '   ' ) );
	}

	/**
	 * Non-ASCII content survives parsing intact.
	 *
	 * @return void
	 */
	public function test_utf8_content_is_preserved(): void {
		$result = ( new Engine() )->scan( '<p><a href="/x">Zurück</a><img src="/naïve.png"></p>' );

		$this->assertNotNull( $result );
		$this->assertStringContainsString( 'naïve.png', $result->findings[0]->message );
	}

	/**
	 * The summary records what ran, for the coverage panel.
	 *
	 * @return void
	 */
	public function test_summary_reports_what_ran(): void {
		$result = ( new Engine() )->scan( $this->fixture() );

		$this->assertNotNull( $result );

		$summary = $result->summary();

		$this->assertSame( 12, $summary['total'] );

		/*
		 * Every registered rule, not a number that happens to be right. The
		 * engine swallows a throwing rule so that one bad check cannot lose the
		 * findings of the rest, which means a rule that fatals looks exactly
		 * like a rule that found nothing. This assertion is the only thing that
		 * tells them apart, and it has already caught two.
		 */
		$this->assertSame(
			count( RuleRegistry::with_defaults()->all() ),
			$summary['rules_run'],
			'A rule threw and the engine swallowed it.'
		);
		$this->assertTrue( $summary['full_page'] );
	}
}
