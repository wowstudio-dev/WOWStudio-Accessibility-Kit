<?php
/**
 * Plugin orchestrator tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use ReflectionProperty;
use WOWStudio\AccessibilityKit\Core\Plugin;
use WOWStudio\AccessibilityKit\Core\Registrable;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests service registration and the boot lifecycle.
 *
 * @covers \WOWStudio\AccessibilityKit\Core\Plugin
 */
final class PluginTest extends TestCase {

	/**
	 * Resets the singleton so each test starts clean.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$instance = new ReflectionProperty( Plugin::class, 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );
	}

	/**
	 * Returns the same shared object on every call.
	 *
	 * @return void
	 */
	public function test_instance_is_shared(): void {
		$this->assertSame( Plugin::instance(), Plugin::instance() );
	}

	/**
	 * Booting twice must not register hooks twice.
	 *
	 * @return void
	 */
	public function test_boot_is_idempotent(): void {
		$service = $this->spy_service();

		Filters\expectApplied( 'wsak_services' )->once()->andReturn( array( 'spy' => $service ) );

		Plugin::instance()->boot();
		Plugin::instance()->boot();

		$this->assertSame( 1, $service->registered );
	}

	/**
	 * Registered services are retrievable by identifier.
	 *
	 * @return void
	 */
	public function test_service_lookup(): void {
		$service = $this->spy_service();

		Filters\expectApplied( 'wsak_services' )->andReturn( array( 'spy' => $service ) );

		$plugin = Plugin::instance();
		$plugin->boot();

		$this->assertSame( $service, $plugin->service( 'spy' ) );
		$this->assertNull( $plugin->service( 'nope' ) );
	}

	/**
	 * Anything in the service list that is not Registrable is ignored.
	 *
	 * @return void
	 */
	public function test_non_registrable_entries_are_ignored(): void {
		Filters\expectApplied( 'wsak_services' )->andReturn( array( 'bogus' => new \stdClass() ) );

		$plugin = Plugin::instance();
		$plugin->boot();

		$this->assertNull( $plugin->service( 'bogus' ) );
	}

	/**
	 * Boot announces itself so extensions have a reliable entry point.
	 *
	 * @return void
	 */
	public function test_boot_fires_the_booted_action(): void {
		Filters\expectApplied( 'wsak_services' )->andReturn( array() );

		Plugin::instance()->boot();

		$this->assertSame( 1, Actions\did( 'wsak_booted' ) );
	}

	/**
	 * Builds a service that counts its own registrations.
	 *
	 * @return Registrable&object{registered:int}
	 */
	private function spy_service(): object {
		return new class() implements Registrable {

			/**
			 * Times register() was called.
			 *
			 * @var int
			 */
			public int $registered = 0;

			/**
			 * Counts the call.
			 *
			 * @return void
			 */
			public function register(): void {
				++$this->registered;
			}
		};
	}
}
