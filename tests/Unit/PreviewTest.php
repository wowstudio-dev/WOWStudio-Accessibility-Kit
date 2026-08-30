<?php
/**
 * Inspector preview tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Functions;
use WOWStudio\AccessibilityKit\Scanner\Preview;
use WOWStudio\AccessibilityKit\Support\Capabilities;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests the frame the inspector shows.
 *
 * Two properties are being guarded. The first is correctness: the preview must
 * render the same document the scanner analysed, or every positional selector
 * lands on the wrong element. The second is that this cannot become a way for
 * an anonymous visitor to change what the site serves them.
 *
 * @covers \WOWStudio\AccessibilityKit\Scanner\Preview
 */
final class PreviewTest extends TestCase {

	/**
	 * Clears the request between cases.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $_GET[ Preview::QUERY_ARG ] );

		parent::tearDown();
	}

	/**
	 * Signs in, or out when given zero.
	 *
	 * @param bool $logged_in Whether a user is present.
	 * @param bool $can_scan  Whether that user may run scans.
	 * @return void
	 */
	private function sign_in( bool $logged_in, bool $can_scan = true ): void {
		Functions\when( 'is_user_logged_in' )->justReturn( $logged_in );
		Functions\when( 'current_user_can' )->alias(
			static fn( string $capability ): bool => $can_scan && Capabilities::RUN_SCAN === $capability
		);
	}

	/**
	 * The preview URL is the permalink plus the marker.
	 *
	 * @return void
	 */
	public function test_preview_url_marks_the_permalink(): void {
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/hello/' );
		Functions\when( 'add_query_arg' )->alias(
			static fn( string $key, string $value, string $url ): string => $url . '?' . $key . '=' . $value
		);

		$this->assertSame(
			'https://example.test/hello/?' . Preview::QUERY_ARG . '=1',
			Preview::url_for( 1 )
		);
	}

	/**
	 * A post with no permalink yields no preview rather than a broken URL.
	 *
	 * @return void
	 */
	public function test_a_post_without_a_permalink_has_no_preview(): void {
		Functions\when( 'get_permalink' )->justReturn( false );

		$this->assertSame( '', Preview::url_for( 99 ) );
	}

	/**
	 * The admin bar is removed for the user doing the scanning.
	 *
	 * The whole reason this class exists. Left in place, the bar inserts an
	 * element at the top of the body that the scanned document did not contain,
	 * shifting every positional selector after it by one.
	 *
	 * @return void
	 */
	public function test_the_admin_bar_is_hidden_on_a_preview_request(): void {
		$this->sign_in( true );
		$_GET[ Preview::QUERY_ARG ] = '1';

		$this->assertFalse( ( new Preview() )->maybe_hide_admin_bar( true ) );
	}

	/**
	 * Ordinary admin browsing is untouched.
	 *
	 * @return void
	 */
	public function test_the_admin_bar_survives_a_normal_request(): void {
		$this->sign_in( true );

		$this->assertTrue( ( new Preview() )->maybe_hide_admin_bar( true ) );
	}

	/**
	 * An anonymous visitor cannot change what the site serves them.
	 *
	 * The marker is a query argument, so anyone can append it. Gating on the
	 * capability as well means doing so is inert: a visitor gets exactly the
	 * page they would have got anyway. Without this the argument would be a
	 * free lever on output for unauthenticated callers, and a way to multiply
	 * cache variants.
	 *
	 * @return void
	 */
	public function test_an_anonymous_visitor_cannot_trigger_the_preview(): void {
		$this->sign_in( false );
		$_GET[ Preview::QUERY_ARG ] = '1';

		$this->assertFalse( Preview::is_preview_request() );
		$this->assertTrue( ( new Preview() )->maybe_hide_admin_bar( true ) );
	}

	/**
	 * A logged-in user without the scan capability is equally inert.
	 *
	 * @return void
	 */
	public function test_a_user_without_the_scan_capability_cannot_trigger_it(): void {
		$this->sign_in( true, false );
		$_GET[ Preview::QUERY_ARG ] = '1';

		$this->assertFalse( Preview::is_preview_request() );
		$this->assertTrue( ( new Preview() )->maybe_hide_admin_bar( true ) );
	}
}
