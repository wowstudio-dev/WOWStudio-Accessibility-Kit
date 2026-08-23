<?php
/**
 * Page source tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use WOWStudio\AccessibilityKit\Scanner\PageMarkup;
use WOWStudio\AccessibilityKit\Scanner\PageSource;
use WOWStudio\AccessibilityKit\Tests\TestCase;
use WP_Error;

/**
 * Tests how markup is obtained, and what happens when it cannot be.
 *
 * @covers \WOWStudio\AccessibilityKit\Scanner\PageSource
 * @covers \WOWStudio\AccessibilityKit\Scanner\PageMarkup
 */
final class PageSourceTest extends TestCase {

	/**
	 * Stubs the WordPress functions the fetcher touches.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/page/' );
		Functions\when( 'home_url' )->justReturn( 'https://example.test/' );
		Functions\when( 'is_wp_error' )->alias( static fn( $thing ): bool => $thing instanceof WP_Error );
		Functions\when( 'wp_remote_retrieve_response_code' )->alias( static fn( array $r ): int => $r['response']['code'] ?? 0 );
		Functions\when( 'wp_remote_retrieve_body' )->alias( static fn( array $r ): string => $r['body'] ?? '' );
		Functions\when( 'get_post' )->justReturn( (object) array( 'post_content' => '<p>Just the content.</p>' ) );
	}

	/**
	 * Builds a fake HTTP response.
	 *
	 * @param int    $code HTTP status.
	 * @param string $body Response body.
	 * @return array<string, mixed>
	 */
	private function response( int $code, string $body ): array {
		return array(
			'response' => array( 'code' => $code ),
			'body'     => $body,
		);
	}

	/**
	 * A successful fetch is reported as a whole page.
	 *
	 * @return void
	 */
	public function test_successful_fetch_is_a_full_page(): void {
		Functions\when( 'wp_remote_get' )->justReturn( $this->response( 200, '<html><body>Hi</body></html>' ) );

		$markup = ( new PageSource() )->for_post( 1 );

		$this->assertInstanceOf( PageMarkup::class, $markup );
		$this->assertTrue( $markup->from_loopback );
		$this->assertSame( '', $markup->notice );
	}

	/**
	 * A blocked loopback request degrades to the post content, and says so.
	 *
	 * This is the case most hosts hit. The scan must still happen, and the
	 * caller must be told coverage was reduced.
	 *
	 * @return void
	 */
	public function test_blocked_loopback_falls_back_and_explains_itself(): void {
		Functions\when( 'wp_remote_get' )->justReturn( new WP_Error( 'http_request_failed', 'Connection refused' ) );

		$markup = ( new PageSource() )->for_post( 1 );

		$this->assertInstanceOf( PageMarkup::class, $markup );
		$this->assertFalse( $markup->from_loopback );
		$this->assertStringContainsString( 'Just the content.', $markup->html );
		$this->assertNotSame( '', $markup->notice, 'A reduced scan must never be silent.' );
		$this->assertStringContainsString( 'Connection refused', $markup->notice );
	}

	/**
	 * An error status also falls back rather than failing the scan.
	 *
	 * @return void
	 */
	public function test_error_status_falls_back(): void {
		Functions\when( 'wp_remote_get' )->justReturn( $this->response( 503, 'Service unavailable' ) );

		$markup = ( new PageSource() )->for_post( 1 );

		$this->assertInstanceOf( PageMarkup::class, $markup );
		$this->assertFalse( $markup->from_loopback );
		$this->assertStringContainsString( '503', $markup->notice );
	}

	/**
	 * An empty body falls back too.
	 *
	 * @return void
	 */
	public function test_empty_body_falls_back(): void {
		Functions\when( 'wp_remote_get' )->justReturn( $this->response( 200, "   \n " ) );

		$markup = ( new PageSource() )->for_post( 1 );

		$this->assertInstanceOf( PageMarkup::class, $markup );
		$this->assertFalse( $markup->from_loopback );
	}

	/**
	 * A site can insist on full-page scans instead of a reduced fallback.
	 *
	 * @return void
	 */
	public function test_fallback_can_be_refused(): void {
		Functions\when( 'wp_remote_get' )->justReturn( new WP_Error( 'http_request_failed', 'Connection refused' ) );
		Filters\expectApplied( 'wsak_allow_content_fallback' )->andReturn( false );

		$result = ( new PageSource() )->for_post( 1 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'wsak_fetch_failed', $result->get_error_code() );
	}

	/**
	 * Supplied markup short-circuits the request entirely.
	 *
	 * @return void
	 */
	public function test_supplied_markup_short_circuits_the_fetch(): void {
		Functions\expect( 'wp_remote_get' )->never();
		Filters\expectApplied( 'wsak_page_html' )->andReturn( '<html><body>Supplied</body></html>' );

		$markup = ( new PageSource() )->for_post( 1 );

		$this->assertInstanceOf( PageMarkup::class, $markup );
		$this->assertTrue( $markup->from_loopback );
		$this->assertStringContainsString( 'Supplied', $markup->html );
	}
}
