<?php
/**
 * Shared test case.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests;

use Brain\Monkey;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case wiring Brain Monkey up and down.
 */
abstract class TestCase extends PHPUnitTestCase {

	/**
	 * Starts Brain Monkey and stubs the WordPress helpers the plugin uses.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Monkey\Functions\stubTranslationFunctions();
		Monkey\Functions\stubEscapeFunctions();

		// WordPress helpers the scanner uses that Brain Monkey does not stub.
		Monkey\Functions\when( 'wp_basename' )->alias(
			static fn( string $path ): string => basename( str_replace( '\\', '/', $path ) )
		);

		/*
		 * Missing this one cost an afternoon. The engine catches a throwing
		 * rule so that one bad check cannot lose the findings of the other
		 * twenty-three — which is right in production and means an
		 * undefined-function fatal in a rule looks, from the outside, exactly
		 * like a rule that found nothing. Two new rules were doing that, and
		 * the only thing that noticed was EngineTest asserting how many rules
		 * ran. Keep that assertion.
		 */
		Monkey\Functions\when( 'wp_parse_url' )->alias(
			/**
			 * Stands in for WordPress's wp_parse_url().
			 *
			 * @param string $url       URL to parse.
			 * @param int    $component Component to return, or -1 for all.
			 * @return array<string, mixed>|string|int|false|null
			 */
			static function ( string $url, int $component = -1 ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Standing in for the WordPress function; this is what it wraps.
				return -1 === $component ? parse_url( $url ) : parse_url( $url, $component );
			}
		);
	}

	/**
	 * Asserts that a call refused, and hands back the refusal to inspect.
	 *
	 * @param mixed  $result  Whatever the call returned.
	 * @param string $message Optional failure message.
	 * @return \WP_Error The refusal.
	 */
	protected function assertWPError( $result, string $message = '' ): \WP_Error {
		$this->assertInstanceOf( \WP_Error::class, $result, $message );

		return $result;
	}

	/**
	 * Tears Brain Monkey down.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
