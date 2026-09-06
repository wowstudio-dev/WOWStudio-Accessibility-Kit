<?php
/**
 * The plugin's own output, put through the plugin's own checks.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Functions;
use WOWStudio\AccessibilityKit\Conformance\StatementGenerator;
use WOWStudio\AccessibilityKit\Readability\FleschKincaid;
use WOWStudio\AccessibilityKit\Conformance\StatementSettings;
use WOWStudio\AccessibilityKit\Scanner\Engine;
use WOWStudio\AccessibilityKit\Scanner\Finding;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Dogfooding.
 *
 * A tool that reports accessibility failures has no business shipping them. The
 * accessibility statement is the one substantial piece of front-end markup this
 * plugin puts on somebody's site, so it is held to the checks the plugin runs
 * on everybody else — every conformance status, with and without sign-off, with
 * and without each optional section.
 *
 * The admin dashboard is React and is not reachable from here; it is covered by
 * the audit recorded in docs/accessibility-audit.md.
 *
 * @covers \WOWStudio\AccessibilityKit\Conformance\StatementGenerator
 */
final class DogfoodTest extends TestCase {

	/**
	 * Success criteria above the level this plugin holds itself to.
	 *
	 * The dogfooding rule is WCAG 2.2 **AA** — CLAUDE.md rule 6 — and 3.1.5
	 * Reading Level is AAA. Excluded here rather than quietly passing, because
	 * an exclusion nobody can see is the way a dogfooding test starts lying.
	 *
	 * This is not a licence to ignore it. The statement's actual reading level
	 * is asserted separately below, so a rewrite that made our own conformance
	 * statement harder to read would still fail.
	 *
	 * @var string[]
	 */
	private const TRIPLE_A = array( '3.1.5' );

	/**
	 * Stand-in for the options table.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = array();

	/**
	 * Wires the WordPress functions the statement touches.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->options = array();

		Functions\when( 'get_option' )->alias(
			fn( string $name, $fallback = false ) => $this->options[ $name ] ?? $fallback
		);
		Functions\when( 'update_option' )->alias(
			function ( string $name, $value ): bool {
				$this->options[ $name ] = $value;

				return true;
			}
		);
		Functions\when( 'get_bloginfo' )->justReturn( 'Example Site' );
		Functions\when( 'sanitize_text_field' )->alias( 'trim' );
		Functions\when( 'sanitize_textarea_field' )->alias( 'trim' );
		Functions\when( 'sanitize_email' )->alias( 'trim' );
		Functions\when( 'esc_url_raw' )->alias( 'trim' );
		Functions\when( 'wp_date' )->justReturn( '23 August 2026' );
	}

	/**
	 * Every combination of settings worth rendering.
	 *
	 * @return array<string, array{array<string, mixed>}>
	 */
	public function statementVariants(): array {
		return array(
			'bare, no contact route yet' => array( array( 'organisation' => 'Example Ltd' ) ),
			'partial with limitations'   => array(
				array(
					'organisation'      => 'Example Ltd',
					'status'            => 'partial',
					'feedback_email'    => 'access@example.test',
					'known_limitations' => "Some PDFs are not tagged.\nOne video has no captions.",
				),
			),
			'full, phone and form'       => array(
				array(
					'organisation'   => 'Example Ltd',
					'status'         => 'full',
					'feedback_phone' => '0800 000 000',
					'feedback_url'   => 'https://example.test/contact',
				),
			),
			'none, external assessment'  => array(
				array(
					'organisation'     => 'Example Ltd',
					'status'           => 'none',
					'assessment'       => 'external',
					'feedback_email'   => 'access@example.test',
					'enforcement_body' => 'Equality Ombudsman',
					'enforcement_url'  => 'https://example.test/complain',
				),
			),
		);
	}

	/**
	 * Wraps a statement in the smallest valid page around it.
	 *
	 * The statement is a fragment. Dropping it into a page that is otherwise
	 * sound means anything the scan reports came from the statement itself
	 * rather than from the scaffolding around it.
	 *
	 * @param string $statement Rendered statement.
	 * @return string
	 */
	private function as_page( string $statement ): string {
		return '<!DOCTYPE html><html lang="en"><head><title>Accessibility</title></head>'
			. '<body><main><h1>Accessibility</h1>' . $statement . '</main></body></html>';
	}

	/**
	 * Scans a statement and returns readable failures.
	 *
	 * @param string $statement Rendered statement.
	 * @return string[]
	 */
	private function findings( string $statement ): array {
		$result = ( new Engine() )->scan( $this->as_page( $statement ) );

		$this->assertNotNull( $result, 'The scanner could not parse our own statement.' );

		return array_map(
			static fn( Finding $f ): string => sprintf( '%s (%s): %s', $f->rule_id, $f->wcag_sc, $f->message ),
			array_filter(
				$result->findings,
				static fn( Finding $f ): bool => ! in_array( $f->wcag_sc, self::TRIPLE_A, true )
			)
		);
	}

	/**
	 * Returns the reading level of our own statement.
	 *
	 * @param string $statement The rendered statement.
	 * @return float|null
	 */
	private function reading_level( string $statement ): ?float {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $statement ) ) );

		return ( new FleschKincaid() )->grade( $text );
	}

	/**
	 * Builds settings from a variant.
	 *
	 * @param array<string, mixed> $changes Settings to apply.
	 * @return StatementSettings
	 */
	private function settings( array $changes ): StatementSettings {
		$settings = new StatementSettings();
		$settings->update( $changes );

		return $settings;
	}

	/**
	 * Every unsigned variant passes our own checks.
	 *
	 * @dataProvider statementVariants
	 *
	 * @param array<string, mixed> $changes Settings for this variant.
	 * @return void
	 */
	public function test_the_draft_statement_passes_our_own_checks( array $changes ): void {
		$html = ( new StatementGenerator( $this->settings( $changes ) ) )->render();

		$this->assertSame( array(), $this->findings( $html ) );
	}

	/**
	 * The signed-off statement passes too.
	 *
	 * @return void
	 */
	public function test_the_signed_off_statement_passes_our_own_checks(): void {
		$settings = $this->settings(
			array(
				'organisation'      => 'Example Ltd',
				'feedback_email'    => 'access@example.test',
				'known_limitations' => 'Some PDFs are not tagged.',
			)
		);
		$settings->attest( 'Ada Lovelace', 'Head of Digital' );

		$html = ( new StatementGenerator( $settings ) )->render();

		$this->assertSame( array(), $this->findings( $html ) );
	}

	/**
	 * Our own statement does not drift towards being unreadable.
	 *
	 * Reading level is AAA and therefore not part of the pass/fail above, but
	 * an accessibility statement that only a lawyer can read is a poor
	 * advertisement for a plugin about accessibility. This is a ceiling rather
	 * than the AAA threshold: it measures around grade ten, and the assertion
	 * is here so that a future edit which pushes it towards fifteen is noticed
	 * by somebody.
	 *
	 * @return void
	 */
	public function test_our_own_statement_stays_readable(): void {
		$html  = ( new StatementGenerator( $this->settings( array( 'organisation' => 'Example Ltd' ) ) ) )->render();
		$grade = $this->reading_level( $html );

		$this->assertNotNull( $grade, 'The statement should be long enough to measure.' );
		$this->assertLessThan(
			12.0,
			$grade,
			sprintf( 'Our own accessibility statement now reads at grade %s.', number_format( (float) $grade, 1 ) )
		);
	}

	/**
	 * The scan actually ran, rather than passing because nothing was checked.
	 *
	 * Without this the suite above would still be green if the registry were
	 * emptied by accident, which is the one way a dogfooding test can lie.
	 *
	 * @return void
	 */
	public function test_the_checks_really_ran(): void {
		$html   = ( new StatementGenerator( $this->settings( array( 'organisation' => 'Example Ltd' ) ) ) )->render();
		$result = ( new Engine() )->scan( $this->as_page( $html ) );

		$this->assertNotNull( $result );
		$this->assertGreaterThanOrEqual( 12, $result->rules_run );
		$this->assertSame( 100, $result->score() );
	}

	/**
	 * Published on its own page, the statement keeps its natural heading levels.
	 *
	 * @return void
	 */
	public function test_the_published_statement_starts_at_level_two(): void {
		$html = ( new StatementGenerator( $this->settings( array( 'organisation' => 'Example Ltd' ) ) ) )->render();

		$this->assertStringContainsString( '<h2>Accessibility statement</h2>', $html );
		$this->assertStringContainsString( '<h3>Tell us about a problem</h3>', $html );
	}

	/**
	 * Nested in the admin panel, every heading moves down together.
	 *
	 * The preview sits below a third-level heading on a screen that already has
	 * an "Accessibility statement" heading of its own. Rendered at its natural
	 * level it would put a second one in the outline at the same rank, and its
	 * sections would read as siblings of the panel's rather than as part of the
	 * preview.
	 *
	 * @return void
	 */
	public function test_the_preview_nests_below_the_panel(): void {
		$html = ( new StatementGenerator( $this->settings( array( 'organisation' => 'Example Ltd' ) ) ) )->render( 2 );

		$this->assertStringContainsString( '<h4>Accessibility statement</h4>', $html );
		$this->assertStringContainsString( '<h5>Tell us about a problem</h5>', $html );
		$this->assertStringNotContainsString( '<h2>', $html );
		$this->assertStringNotContainsString( '<h3>', $html );
	}

	/**
	 * An offset can never push a heading past the last level HTML has.
	 *
	 * @return void
	 */
	public function test_headings_never_run_off_the_end(): void {
		$html = ( new StatementGenerator( $this->settings( array( 'organisation' => 'Example Ltd' ) ) ) )->render( 99 );

		$this->assertStringContainsString( '<h5>Accessibility statement</h5>', $html );
		$this->assertStringContainsString( '<h6>Tell us about a problem</h6>', $html );
		$this->assertStringNotContainsString( '<h7', $html );
	}

	/**
	 * Offsetting the preview never drops the coverage caveat.
	 *
	 * @return void
	 */
	public function test_the_preview_still_carries_the_caveat(): void {
		$html = ( new StatementGenerator( $this->settings( array( 'organisation' => 'Example Ltd' ) ) ) )->render( 2 );

		$this->assertStringContainsString( 'Automated testing finds only some accessibility problems', $html );
		$this->assertStringContainsString( 'Draft', $html );
	}
}
