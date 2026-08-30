<?php
/**
 * REST route registration tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use WOWStudio\AccessibilityKit\Rest\ScanController;
use WOWStudio\AccessibilityKit\Support\Capabilities;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests the routes and, more importantly, that each one is gated.
 *
 * @covers \WOWStudio\AccessibilityKit\Rest\ScanController
 */
final class ScanControllerTest extends TestCase {

	/**
	 * Routes are declared on rest_api_init, not earlier.
	 *
	 * @return void
	 */
	public function test_routes_are_registered_on_rest_api_init(): void {
		Actions\expectAdded( 'rest_api_init' )->once();

		( new ScanController() )->register();

		$this->addToAssertionCount( 1 );
	}

	/**
	 * Every route is registered under our namespace with a permission callback.
	 *
	 * A route registered without a permission callback is public. WordPress
	 * warns about it but still serves it, so this is worth asserting rather
	 * than trusting to review.
	 *
	 * @return void
	 */
	public function test_every_route_is_namespaced_and_gated(): void {
		$registered = array();

		Functions\when( 'register_rest_route' )->alias(
			static function ( string $rest_namespace, string $route, array $handlers ) use ( &$registered ): bool {
				$registered[] = array(
					'namespace' => $rest_namespace,
					'route'     => $route,
					'handlers'  => $handlers,
				);

				return true;
			}
		);

		( new ScanController() )->register_routes();

		$this->assertCount( 5, $registered );

		$routes = array();

		foreach ( $registered as $entry ) {
			$this->assertSame( 'wsak/v1', $entry['namespace'] );
			$routes[] = $entry['route'];

			foreach ( $entry['handlers'] as $handler ) {
				$this->assertArrayHasKey(
					'permission_callback',
					$handler,
					sprintf( 'Route %s has no permission callback and would be public.', $entry['route'] )
				);
				$this->assertIsCallable( $handler['permission_callback'] );
			}
		}

		sort( $routes );

		$this->assertSame(
			array(
				'/coverage',
				'/scan',
				'/scannable',
				'/scans/(?P<id>\d+)',
				'/scans/(?P<id>\d+)/browser',
			),
			$routes
		);
	}

	/**
	 * No argument carries an authorisation check.
	 *
	 * WordPress validates arguments before it runs permission_callback, so any
	 * capability or existence check placed in a validate_callback answers
	 * anonymous callers too. /scan used to check post readability there, which
	 * let anyone on the internet tell an existing draft ("you do not have
	 * permission to read that content") from a post id that was never used
	 * ("that content could not be found") — an oracle for private post ids,
	 * served without authentication. The check now runs in the handler, behind
	 * can_scan().
	 *
	 * Asserting the absence of a callback is blunt, but the ordering is a
	 * property of WordPress rather than of this plugin, and the safe rule is
	 * simply not to authorise anything from an argument validator.
	 *
	 * @return void
	 */
	public function test_no_argument_validator_performs_authorisation(): void {
		$registered = array();

		Functions\when( 'register_rest_route' )->alias(
			static function ( string $rest_namespace, string $route, array $handlers ) use ( &$registered ): bool {
				$registered[] = array(
					'route'    => $route,
					'handlers' => $handlers,
				);

				return true;
			}
		);

		( new ScanController() )->register_routes();

		foreach ( $registered as $entry ) {
			foreach ( $entry['handlers'] as $handler ) {
				foreach ( $handler['args'] ?? array() as $name => $spec ) {
					$this->assertArrayNotHasKey(
						'validate_callback',
						$spec,
						sprintf(
							'Argument "%s" on %s has a validate_callback. Validators run before permission_callback, so anything they decide is decided for anonymous callers too — keep authorisation in the handler.',
							$name,
							$entry['route']
						)
					);
				}
			}
		}

		$this->addToAssertionCount( 1 );
	}

	/**
	 * Running a scan needs the scan capability, not merely being logged in.
	 *
	 * @return void
	 */
	public function test_scanning_requires_the_scan_capability(): void {
		$controller = new ScanController();

		Functions\when( 'current_user_can' )->alias(
			static fn( string $capability ): bool => Capabilities::RUN_SCAN === $capability
		);

		$this->assertTrue( $controller->can_scan() );

		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'rest_authorization_required_code' )->justReturn( 403 );

		$this->assertInstanceOf( \WP_Error::class, $controller->can_scan() );
	}

	/**
	 * Reading reports needs the reports capability.
	 *
	 * @return void
	 */
	public function test_viewing_requires_the_reports_capability(): void {
		$controller = new ScanController();

		Functions\when( 'current_user_can' )->alias(
			static fn( string $capability ): bool => Capabilities::VIEW_REPORTS === $capability
		);

		$this->assertTrue( $controller->can_view() );

		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'rest_authorization_required_code' )->justReturn( 403 );

		$this->assertInstanceOf( \WP_Error::class, $controller->can_view() );
	}
}
