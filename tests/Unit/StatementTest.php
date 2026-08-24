<?php
/**
 * Accessibility statement tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Functions;
use WOWStudio\AccessibilityKit\Conformance\ConformanceStatus;
use WOWStudio\AccessibilityKit\Conformance\StatementGenerator;
use WOWStudio\AccessibilityKit\Conformance\StatementSettings;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests the statement, and above all the sign-off rules around it.
 *
 * @covers \WOWStudio\AccessibilityKit\Conformance\StatementSettings
 * @covers \WOWStudio\AccessibilityKit\Conformance\StatementGenerator
 * @covers \WOWStudio\AccessibilityKit\Conformance\ConformanceStatus
 */
final class StatementTest extends TestCase {

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
	 * Fills in everything a complete statement needs.
	 *
	 * @return StatementSettings
	 */
	private function completed(): StatementSettings {
		$settings = new StatementSettings();
		$settings->update(
			array(
				'organisation'      => 'Example Ltd',
				'known_limitations' => "Some PDFs are not tagged.\nOne video has no captions.",
				'feedback_email'    => 'access@example.test',
			)
		);

		return $settings;
	}

	/**
	 * An unsigned statement renders as a draft.
	 *
	 * @return void
	 */
	public function test_an_unsigned_statement_is_marked_draft(): void {
		$html = ( new StatementGenerator( $this->completed() ) )->render();

		$this->assertStringContainsString( 'Draft', $html );
		$this->assertStringContainsString( 'nobody has confirmed', $html );
	}

	/**
	 * Signing off removes the draft notice and names the person.
	 *
	 * @return void
	 */
	public function test_signing_off_removes_the_draft_notice(): void {
		$settings = $this->completed();
		$settings->attest( 'Ada Lovelace', 'Head of Digital' );

		$html = ( new StatementGenerator( $settings ) )->render();

		$this->assertStringNotContainsString( 'Draft —', $html );
		$this->assertStringContainsString( 'Ada Lovelace', $html );
		$this->assertStringContainsString( 'Head of Digital', $html );
	}

	/**
	 * Changing the wording withdraws the sign-off.
	 *
	 * The invariant the whole design rests on. An approved statement must never
	 * be able to say something its approver never read.
	 *
	 * @return void
	 */
	public function test_editing_the_statement_withdraws_the_sign_off(): void {
		$settings = $this->completed();
		$settings->attest( 'Ada Lovelace', 'Head of Digital' );

		$this->assertTrue( $settings->all()['attested'] );

		$settings->update( array( 'organisation' => 'Example Limited' ) );

		$this->assertFalse( $settings->all()['attested'] );
		$this->assertSame( '', $settings->all()['attested_by'] );
		$this->assertStringContainsString( 'Draft', ( new StatementGenerator( $settings ) )->render() );
	}

	/**
	 * Saving the same values again does not withdraw the sign-off.
	 *
	 * A no-op save from a settings screen must not quietly unpublish somebody's
	 * finished statement.
	 *
	 * @return void
	 */
	public function test_saving_unchanged_values_keeps_the_sign_off(): void {
		$settings = $this->completed();
		$settings->attest( 'Ada Lovelace', '' );

		$settings->update( array( 'organisation' => 'Example Ltd' ) );

		$this->assertTrue( $settings->all()['attested'] );
	}

	/**
	 * A statement with no contact route is reported as unfinished.
	 *
	 * @return void
	 */
	public function test_a_statement_without_a_contact_route_is_incomplete(): void {
		$settings = new StatementSettings();
		$settings->update(
			array(
				'organisation'      => 'Example Ltd',
				'feedback_email'    => '',
				'known_limitations' => 'Something.',
			)
		);

		$missing = ( new StatementGenerator( $settings ) )->missing();

		$this->assertNotEmpty( $missing );
		$this->assertStringContainsString( 'report a problem', implode( ' ', $missing ) );
	}

	/**
	 * Claiming partial conformance without saying what falls short is incomplete.
	 *
	 * @return void
	 */
	public function test_partial_conformance_needs_known_problems(): void {
		$settings = new StatementSettings();
		$settings->update(
			array(
				'organisation'      => 'Example Ltd',
				'feedback_email'    => 'a@example.test',
				'status'            => ConformanceStatus::Partial->value,
				'known_limitations' => '',
			)
		);

		$this->assertNotEmpty( ( new StatementGenerator( $settings ) )->missing() );
	}

	/**
	 * The route for reporting a problem is always rendered.
	 *
	 * European rules expect it, and it is the part a person in difficulty
	 * actually needs.
	 *
	 * @return void
	 */
	public function test_the_feedback_route_is_always_present(): void {
		$html = ( new StatementGenerator( $this->completed() ) )->render();

		$this->assertStringContainsString( 'Tell us about a problem', $html );
		$this->assertStringContainsString( 'access@example.test', $html );
		$this->assertStringContainsString( 'working day', $html );
	}

	/**
	 * The limits of automated testing are stated, whatever the settings say.
	 *
	 * @return void
	 */
	public function test_the_coverage_caveat_cannot_be_switched_off(): void {
		$settings = $this->completed();
		$settings->update( array( 'status' => ConformanceStatus::Full->value ) );
		$settings->attest( 'Ada Lovelace', '' );

		$html = ( new StatementGenerator( $settings ) )->render();

		$this->assertStringContainsString( 'Automated testing finds only some', $html );
		$this->assertStringContainsString( 'need a person', $html );
	}

	/**
	 * Every conformance claim is attributed to the organisation.
	 *
	 * The plugin states nothing about the site itself. It writes down what the
	 * owner says, and says whose words they are.
	 *
	 * @return void
	 */
	public function test_conformance_claims_are_attributed_to_the_organisation(): void {
		foreach ( ConformanceStatus::cases() as $status ) {
			$sentence = $status->sentence( 'Example Ltd', 'WCAG 2.2 level AA' );

			$this->assertStringStartsWith( 'Example Ltd considers', $sentence );
		}
	}

	/**
	 * Known problems are rendered as a list.
	 *
	 * @return void
	 */
	public function test_known_problems_become_a_list(): void {
		$html = ( new StatementGenerator( $this->completed() ) )->render();

		$this->assertStringContainsString( '<li>Some PDFs are not tagged.</li>', $html );
		$this->assertStringContainsString( '<li>One video has no captions.</li>', $html );
	}
}
