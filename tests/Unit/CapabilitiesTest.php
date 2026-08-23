<?php
/**
 * Capability tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use WOWStudio\AccessibilityKit\Support\Capabilities;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests the capability definitions and role assignment.
 *
 * @covers \WOWStudio\AccessibilityKit\Support\Capabilities
 */
final class CapabilitiesTest extends TestCase {

	/**
	 * Every capability carries the project prefix.
	 *
	 * @return void
	 */
	public function test_all_capabilities_are_prefixed(): void {
		$this->assertNotEmpty( Capabilities::all() );

		foreach ( Capabilities::all() as $capability ) {
			$this->assertStringStartsWith( 'wsak_', $capability );
		}
	}

	/**
	 * Editors can scan and read, but cannot change markup or settings.
	 *
	 * @return void
	 */
	public function test_editors_cannot_apply_fixes_or_change_settings(): void {
		$map = Capabilities::role_map();

		$this->assertContains( Capabilities::RUN_SCAN, $map['editor'] );
		$this->assertContains( Capabilities::VIEW_REPORTS, $map['editor'] );
		$this->assertNotContains( Capabilities::APPLY_FIX, $map['editor'] );
		$this->assertNotContains( Capabilities::MANAGE_SETTINGS, $map['editor'] );
	}

	/**
	 * Administrators receive every capability.
	 *
	 * @return void
	 */
	public function test_administrators_receive_every_capability(): void {
		$map = Capabilities::role_map();

		$this->assertSame( Capabilities::all(), $map['administrator'] );
	}

	/**
	 * The role map is filterable so a site can retune it.
	 *
	 * @return void
	 */
	public function test_role_map_is_filterable(): void {
		Filters\expectApplied( 'wsak_role_capabilities' )
			->once()
			->andReturn( array( 'shop_manager' => array( Capabilities::RUN_SCAN ) ) );

		$this->assertSame( array( 'shop_manager' => array( Capabilities::RUN_SCAN ) ), Capabilities::role_map() );
	}

	/**
	 * Granting adds each mapped capability to its role.
	 *
	 * @return void
	 */
	public function test_grant_adds_capabilities_to_roles(): void {
		$role = Mockery::mock( 'WP_Role' );
		$role->shouldReceive( 'add_cap' )->times( count( Capabilities::all() ) + 2 );

		Functions\when( 'get_role' )->justReturn( $role );

		Capabilities::grant();

		$this->assertTrue( true );
	}

	/**
	 * A missing role is skipped rather than fatal.
	 *
	 * @return void
	 */
	public function test_grant_skips_missing_roles(): void {
		Functions\when( 'get_role' )->justReturn( null );

		Capabilities::grant();

		$this->assertTrue( true );
	}
}
