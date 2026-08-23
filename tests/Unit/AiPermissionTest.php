<?php
/**
 * Site-level AI switch tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Functions;
use WOWStudio\AccessibilityKit\AI\AiClientBridge;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests that the site's own AI switch is obeyed.
 *
 * @covers \WOWStudio\AccessibilityKit\AI\AiClientBridge
 */
final class AiPermissionTest extends TestCase {

	/**
	 * A site that switched AI off is obeyed.
	 *
	 * WordPress 7.0 gives site owners and hosts a way to turn AI off. Ignoring
	 * it would mean this plugin calling a paid provider on a site that has
	 * explicitly said not to.
	 *
	 * @return void
	 */
	public function test_a_disabled_site_is_obeyed(): void {
		Functions\when( 'wp_supports_ai' )->justReturn( false );

		$this->assertFalse( ( new AiClientBridge() )->site_permits_ai() );
	}

	/**
	 * A site that permits AI is permitted.
	 *
	 * @return void
	 */
	public function test_an_enabled_site_is_permitted(): void {
		Functions\when( 'wp_supports_ai' )->justReturn( true );

		$this->assertTrue( ( new AiClientBridge() )->site_permits_ai() );
	}

	/**
	 * Older WordPress has no switch, so there is nothing to disobey.
	 *
	 * The plugin supports WordPress 6.6, where wp_supports_ai() does not exist.
	 * Treating its absence as "disabled" would break AI on every site below 7.0.
	 *
	 * Runs isolated: Brain Monkey cannot un-define a function it has stubbed, so
	 * once another test in this process has stubbed wp_supports_ai(),
	 * function_exists() stays true and the absence case becomes untestable.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @return void
	 */
	public function test_older_wordpress_without_the_switch_is_permitted(): void {
		$this->assertTrue( ( new AiClientBridge() )->site_permits_ai() );
	}

	/**
	 * A disabled site cannot reach the WordPress AI client either.
	 *
	 * @return void
	 */
	public function test_a_disabled_site_is_not_configured_for_any_provider(): void {
		Functions\when( 'wp_supports_ai' )->justReturn( false );

		$this->assertFalse( ( new AiClientBridge() )->is_configured_for( 'anthropic' ) );
	}
}
