<?php
/**
 * Tests for establishing loopback once.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Functions;
use WOWStudio\AccessibilityKit\Scanner\LoopbackProbe;
use WOWStudio\AccessibilityKit\Tests\TestCase;
use WP_Error;

/**
 * Tests the one fact a bulk run must not learn a hundred times.
 *
 * @covers \WOWStudio\AccessibilityKit\Scanner\LoopbackProbe
 */
final class LoopbackProbeTest extends TestCase {

	/**
	 * Stand-in for the transient store.
	 *
	 * @var array<string, mixed>
	 */
	private array $store = array();

	/**
	 * How many HTTP requests were attempted.
	 *
	 * @var int
	 */
	private int $requests = 0;

	/**
	 * What the next request should return.
	 *
	 * @var mixed
	 */
	private $response;

	/**
	 * Wires the transient store and the HTTP layer.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->store    = array();
		$this->requests = 0;
		$this->response = array( 'response' => array( 'code' => 200 ) );

		Functions\when( 'home_url' )->justReturn( 'https://example.test/' );
		Functions\when( 'is_wp_error' )->alias( static fn( $thing ): bool => $thing instanceof WP_Error );
		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			static fn( $r ): int => is_array( $r ) ? (int) ( $r['response']['code'] ?? 0 ) : 0
		);
		Functions\when( 'get_transient' )->alias( fn( string $key ) => $this->store[ $key ] ?? false );
		Functions\when( 'set_transient' )->alias(
			function ( string $key, $value ): bool {
				$this->store[ $key ] = $value;

				return true;
			}
		);
		Functions\when( 'delete_transient' )->alias(
			function ( string $key ): bool {
				unset( $this->store[ $key ] );

				return true;
			}
		);
		Functions\when( 'wp_remote_get' )->alias(
			function () {
				++$this->requests;

				return $this->response;
			}
		);
	}

	/**
	 * A working site is asked once and remembered.
	 *
	 * @return void
	 */
	public function test_a_working_site_is_asked_once(): void {
		$probe = new LoopbackProbe();

		$this->assertTrue( $probe->works() );
		$this->assertTrue( $probe->works() );
		$this->assertTrue( $probe->works() );

		$this->assertSame( 1, $this->requests, 'The answer must be cached, not re-fetched.' );
	}

	/**
	 * A blocked site is asked once too.
	 *
	 * This is the case the class exists for. Before it, a hundred-page run made
	 * a hundred requests that each waited out the full timeout, to learn the
	 * same thing every time.
	 *
	 * @return void
	 */
	public function test_a_blocked_site_is_asked_once(): void {
		$this->response = new WP_Error( 'http_request_failed', 'cURL error 7' );

		$probe = new LoopbackProbe();

		$this->assertFalse( $probe->works() );
		$this->assertFalse( $probe->works() );

		$this->assertSame( 1, $this->requests );
	}

	/**
	 * A redirect still proves the request completed.
	 *
	 * The question is whether the site can reach itself, not what it says when
	 * it does. Treating a 301 as failure would report loopback broken on every
	 * site that redirects to www or to https.
	 *
	 * @return void
	 */
	public function test_a_redirect_counts_as_reachable(): void {
		$this->response = array( 'response' => array( 'code' => 301 ) );

		$this->assertTrue( ( new LoopbackProbe() )->works() );
	}

	/**
	 * A server error does not.
	 *
	 * @return void
	 */
	public function test_a_server_error_counts_as_blocked(): void {
		$this->response = array( 'response' => array( 'code' => 500 ) );

		$this->assertFalse( ( new LoopbackProbe() )->works() );
	}

	/**
	 * Before anything has been established, known() says so rather than guessing.
	 *
	 * The interface uses this to decide what to tell somebody before they press
	 * a button, and it must not make an HTTP request to find out.
	 *
	 * @return void
	 */
	public function test_an_unknown_verdict_reads_as_unknown(): void {
		$probe = new LoopbackProbe();

		$this->assertNull( $probe->known() );
		$this->assertSame( 0, $this->requests );

		$probe->works();

		$this->assertTrue( $probe->known() );
		$this->assertSame( 1, $this->requests );
	}

	/**
	 * Forgetting makes the next question ask again.
	 *
	 * A host that starts permitting loopback should not have to wait for a
	 * cache to lapse before it is believed.
	 *
	 * @return void
	 */
	public function test_a_verdict_can_be_retested(): void {
		$probe = new LoopbackProbe();
		$probe->works();

		$probe->forget();

		$this->assertNull( $probe->known() );

		$probe->works();

		$this->assertSame( 2, $this->requests );
	}
}
