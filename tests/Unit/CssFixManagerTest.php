<?php
/**
 * Tests for writing style rules on behalf of a browser.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Functions;
use Mockery;
use WOWStudio\AccessibilityKit\Db\IssueRepository;
use WOWStudio\AccessibilityKit\Remediation\CssFixManager;
use WOWStudio\AccessibilityKit\Remediation\CssRules;
use WOWStudio\AccessibilityKit\Remediation\CustomCss;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests the boundary between a browser's proposal and the site's stylesheet.
 *
 * The interface composes these rules by measuring a live page, and a person
 * approves them on screen. None of that is evidence: the request arrives over
 * REST from a page we do not control, so the tests here are written as though
 * the caller is hostile rather than the review screen we shipped.
 *
 * @covers \WOWStudio\AccessibilityKit\Remediation\CssFixManager
 * @covers \WOWStudio\AccessibilityKit\Remediation\CssRules
 */
final class CssFixManagerTest extends TestCase {

	/**
	 * Stand-in for the stored Additional CSS.
	 *
	 * @var string
	 */
	private string $stored = '';

	/**
	 * Mock database handle.
	 *
	 * @var Mockery\MockInterface
	 */
	private $wpdb;

	/**
	 * The row find() will return.
	 *
	 * @var object|null
	 */
	private $row;

	/**
	 * Wires the stylesheet, the database, and core's CSS filter.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->stored = '';
		$this->row    = $this->issue_row();

		Functions\when( 'wp_get_custom_css' )->alias( fn(): string => $this->stored );
		Functions\when( 'wp_update_custom_css_post' )->alias(
			function ( string $css ) {
				$this->stored = $css;

				return (object) array( 'ID' => 1 );
			}
		);
		Functions\when( 'is_wp_error' )->alias(
			static fn( $thing ): bool => $thing instanceof \WP_Error
		);
		Functions\when( 'wp_get_theme' )->justReturn(
			new class() {
				/**
				 * Returns a theme header.
				 *
				 * @param string $header Header to read.
				 * @return string
				 */
				public function get( string $header ): string {
					return 'Twenty Twenty-Five' . ( '' === $header ? '' : '' );
				}
			}
		);

		// Stands in for core's inline-style filter. It accepts the properties
		// this feature uses and rejects anything with a url() in it, which is
		// the shape of the real thing for our purposes.
		Functions\when( 'safecss_filter_attr' )->alias(
			static function ( string $css ): string {
				return preg_match( '/url\s*\(|expression|javascript:/i', $css ) ? '' : $css;
			}
		);

		$this->wpdb         = Mockery::mock( 'wpdb' );
		$this->wpdb->prefix = 'wp_';
		$this->wpdb->shouldReceive( 'prepare' )->andReturnUsing(
			static fn( string $sql ): string => $sql
		);
		$this->wpdb->shouldReceive( 'get_row' )->andReturnUsing( fn() => $this->row );
		$this->wpdb->shouldReceive( 'update' )->andReturn( 1 );

		$GLOBALS['wpdb'] = $this->wpdb;
	}

	/**
	 * Builds an issue row.
	 *
	 * @param string $rule_id  Rule the finding names.
	 * @param string $found_by Which pass produced it.
	 * @return object
	 */
	private function issue_row( string $rule_id = 'colour-contrast', string $found_by = 'browser' ): object {
		return (object) array(
			'id'         => 12,
			'scan_id'    => 3,
			'post_id'    => 7,
			'rule_id'    => $rule_id,
			'wcag_sc'    => '1.4.3',
			'severity'   => 'serious',
			'detection'  => 'auto',
			'found_by'   => $found_by,
			'status'     => 'open',
			'selector'   => '/html/body/p',
			'context'    => '<p>Faint</p>',
			'message'    => 'Contrast ratio of 2.1:1.',
			'note'       => '',
			'created_at' => '2026-08-31 00:00:00',
			'updated_at' => '2026-08-31 00:00:00',
		);
	}

	/**
	 * Returns a manager wired to the mocked stylesheet.
	 *
	 * @return CssFixManager
	 */
	private function manager(): CssFixManager {
		return new CssFixManager( new IssueRepository(), new CustomCss() );
	}

	/**
	 * The happy path: a reviewed rule reaches the stylesheet intact.
	 *
	 * @return void
	 */
	public function test_a_reviewed_rule_is_written(): void {
		$result = $this->manager()->apply(
			12,
			'.entry-content a',
			array(
				array(
					'property' => 'color',
					'value'    => '#1a4d8f',
				),
			)
		);

		$this->assertIsArray( $result );
		$this->assertStringContainsString( '.entry-content a {', $this->stored );
		$this->assertStringContainsString( 'color: #1a4d8f;', $this->stored );
		$this->assertStringContainsString( '/* wsak:issue:12 */', $this->stored );
	}

	/**
	 * A finding from the markup pass is not answered with a style rule.
	 *
	 * Papering over a missing label with CSS would change what the page looks
	 * like without changing what it says, and would mark the finding fixed.
	 *
	 * @return void
	 */
	public function test_a_server_finding_is_refused(): void {
		$this->row = $this->issue_row( 'colour-contrast', 'server' );

		$result = $this->manager()->apply(
			12,
			'.entry',
			array(
				array(
					'property' => 'color',
					'value'    => '#000000',
				),
			)
		);

		$error = $this->assertWPError( $result );
		$this->assertSame( 'wsak_not_a_css_issue', $error->get_error_code() );
		$this->assertSame( '', $this->stored );
	}

	/**
	 * A rule that has no CSS answer is refused even from the browser pass.
	 *
	 * @return void
	 */
	public function test_a_rule_with_no_css_answer_is_refused(): void {
		$this->row = $this->issue_row( 'hidden-element-still-focusable' );

		$result = $this->manager()->apply(
			12,
			'.thing',
			array(
				array(
					'property' => 'display',
					'value'    => 'none',
				),
			)
		);

		$error = $this->assertWPError( $result );
		$this->assertSame( 'wsak_no_css_fix', $error->get_error_code() );
		$this->assertSame( '', $this->stored );
	}

	/**
	 * A property outside what the finding is about is refused.
	 *
	 * The declarations arrive from a browser. Without this, a contrast fix could
	 * set `position` or `content` — writing site-wide CSS nobody reviewed
	 * through a route that only ever showed somebody a colour.
	 *
	 * @return void
	 */
	public function test_a_property_the_rule_does_not_cover_is_refused(): void {
		$result = $this->manager()->apply(
			12,
			'.entry',
			array(
				array(
					'property' => 'color',
					'value'    => '#000000',
				),
				array(
					'property' => 'position',
					'value'    => 'fixed',
				),
			)
		);

		$error = $this->assertWPError( $result );
		$this->assertSame( 'wsak_property_not_allowed', $error->get_error_code() );
		$this->assertSame( '', $this->stored, 'Nothing may be written when any declaration is refused.' );
	}

	/**
	 * A value core would not store inline is refused here too.
	 *
	 * @return void
	 */
	public function test_a_value_core_rejects_is_refused(): void {
		$result = $this->manager()->apply(
			12,
			'.entry',
			array(
				array(
					'property' => 'color',
					'value'    => 'url(https://example.com/x.png)',
				),
			)
		);

		$error = $this->assertWPError( $result );
		$this->assertSame( 'wsak_value_not_allowed', $error->get_error_code() );
		$this->assertSame( '', $this->stored );
	}

	/**
	 * Removing a rule takes it out and leaves the others alone.
	 *
	 * @return void
	 */
	public function test_a_rule_can_be_removed(): void {
		$this->manager()->apply(
			12,
			'.entry',
			array(
				array(
					'property' => 'color',
					'value'    => '#1a4d8f',
				),
			)
		);

		$result = $this->manager()->revert( 12 );

		$this->assertIsArray( $result );
		$this->assertStringNotContainsString( '#1a4d8f', $this->stored );
	}

	/**
	 * Removing a rule that is not there says so rather than reporting success.
	 *
	 * Somebody may have deleted it by hand in the Customiser, which is a thing
	 * we told them they were allowed to do.
	 *
	 * @return void
	 */
	public function test_removing_a_rule_that_is_gone_is_reported(): void {
		$result = $this->manager()->revert( 12 );

		$error = $this->assertWPError( $result );
		$this->assertSame( 'wsak_no_such_rule', $error->get_error_code() );
	}

	/**
	 * Selectors that could break out of the rule are refused.
	 *
	 * Each of these would let the caller write CSS that no review screen ever
	 * displayed — closing our rule early, opening an at-rule, or commenting out
	 * the marker that delimits the block we are allowed to touch.
	 *
	 * @dataProvider hostile_selectors
	 *
	 * @param string $selector Selector to refuse.
	 * @return void
	 */
	public function test_a_selector_that_could_escape_is_refused( string $selector ): void {
		$result = $this->manager()->apply(
			12,
			$selector,
			array(
				array(
					'property' => 'color',
					'value'    => '#000000',
				),
			)
		);

		$error = $this->assertWPError( $result, $selector . ' should have been refused.' );
		$this->assertSame( 'wsak_unsafe_selector', $error->get_error_code() );
		$this->assertSame( '', $this->stored );
	}

	/**
	 * Selectors that must not be accepted.
	 *
	 * @return array<string, array{string}>
	 */
	public static function hostile_selectors(): array {
		return array(
			'closes the rule'        => array( '.a { color: red } body' ),
			'ends the declaration'   => array( '.a; body' ),
			'opens an at-rule'       => array( '@import url(evil.css); .a' ),
			'opens a comment'        => array( '.a /* END WOWStudio Accessibility Kit */' ),
			'closes a comment'       => array( '.a */' ),
			'fetches a resource'     => array( '.a[style*="url(x)"]' ),
			'contains a newline'     => array( ".a\n}\nbody" ),
			'is empty'               => array( '   ' ),
			'is a script tag'        => array( '<script>' ),
			'escapes with backslash' => array( '.a\\7b color:red' ),
		);
	}

	/**
	 * The interface and the server agree on what a stylesheet can answer.
	 *
	 * These two lists are maintained in different languages in different files,
	 * and they have to say the same thing. If the interface grows a rule the
	 * server has not been told about, somebody reviews a fix and is refused on
	 * apply; if the server grows one the interface has not, a rule can be
	 * written that no review screen ever displayed. Reading the JavaScript from
	 * here is inelegant and catches both.
	 *
	 * @return void
	 */
	public function test_the_interface_and_the_server_allow_the_same_rules(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a file from this repository in a test; there is no HTTP here.
		$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/src/scanner/repair.js' );

		$this->assertSame(
			1,
			preg_match( '/export const CSS_FIXABLE = \[(.*?)\];/s', $source, $matches ),
			'The interface should declare the rules it offers CSS fixes for.'
		);

		preg_match_all( "/'([a-z-]+)'/", $matches[1], $found );

		$interface = $found[1];
		$server    = array_keys( CssRules::map() );

		sort( $interface );
		sort( $server );

		$this->assertSame( $server, $interface );
	}

	/**
	 * Ordinary selectors are accepted.
	 *
	 * @dataProvider ordinary_selectors
	 *
	 * @param string $selector Selector to accept.
	 * @return void
	 */
	public function test_ordinary_selectors_are_accepted( string $selector ): void {
		$this->assertTrue(
			CssRules::selector_is_safe( $selector ),
			$selector . ' is a selector somebody would reasonably type.'
		);
	}

	/**
	 * Selectors that must keep working.
	 *
	 * @return array<string, array{string}>
	 */
	public static function ordinary_selectors(): array {
		return array(
			'an id'          => array( '#masthead' ),
			'a class'        => array( 'a.entry-link' ),
			'a descendant'   => array( '.entry-content p' ),
			'a child chain'  => array( 'main > article > p:nth-of-type(2)' ),
			'an attribute'   => array( 'input[type="checkbox"]' ),
			'a list'         => array( '.a, .b' ),
			'a pseudo-class' => array( 'a:hover' ),
			'a wildcard'     => array( '.menu *' ),
		);
	}
}
