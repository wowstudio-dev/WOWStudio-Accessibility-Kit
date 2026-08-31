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
