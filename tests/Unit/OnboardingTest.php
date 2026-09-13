<?php
/**
 * First-run tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Functions;
use WOWStudio\AccessibilityKit\Admin\Onboarding;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests the setup that opens on a fresh install.
 *
 * The behaviour worth pinning down is all about restraint. This used to take
 * over somebody's screen on activation, and the tests were about the cases
 * where it must not; it no longer reaches outside the plugin's own screens at
 * all, and the test that matters now is the one holding it there.
 *
 * @covers \WOWStudio\AccessibilityKit\Admin\Onboarding
 */
final class OnboardingTest extends TestCase {

	/**
	 * Sets up the WordPress functions this class reaches for.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'admin_url' )->alias(
			static fn( string $path = '' ): string => 'https://example.test/wp-admin/' . $path
		);
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_key' )->alias(
			static fn( $value ): string => strtolower( (string) preg_replace( '/[^a-z0-9_-]/i', '', (string) $value ) )
		);
	}

	/**
	 * Clears request state so one test cannot leak into the next.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $_GET[ Onboarding::QUERY_ARG ] );

		parent::tearDown();
	}

	/**
	 * The setup hijacks nothing, and that is the whole point of it.
	 *
	 * There was a redirect on activation once. It fired once, checked the
	 * capability, and skipped bulk activations — and it still went in the
	 * review round for 1.0.1. Guideline 11 asks plugins not to hijack the
	 * admin, and taking over whatever screen somebody was already on is the
	 * plainest reading of that however careful the safeguards around it were.
	 * The dashboard opens on the setup while the setup is undone instead, which
	 * lands them in the same place without reaching outside our own screens.
	 *
	 * Source-reading, because a hook added back here would work perfectly and
	 * fail a review months later, with nothing failing in between to say so.
	 *
	 * @return void
	 */
	public function test_the_setup_hijacks_nothing(): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local source file in a unit test; WordPress is not loaded.
		$source = (string) file_get_contents( __DIR__ . '/../../src/Admin/Onboarding.php' );

		foreach ( array( 'wp_safe_redirect(', 'wp_redirect(', 'add_action(', 'admin_notices' ) as $forbidden ) {
			$this->assertStringNotContainsString(
				$forbidden,
				$source,
				'The setup must not reach outside the plugin\'s own screens.'
			);
		}
	}

	/**
	 * The setup is a view on the dashboard, not a page of its own.
	 *
	 * Not a preference. A WordPress page with no menu entry cannot be opened:
	 * `user_can_access_admin_page()` resolves a page's parent by walking the
	 * menu it was removed from, fails to find it, looks it up in
	 * `$_registered_pages` under an empty parent, misses, and refuses. Anybody
	 * reaching for a tidier URL will find that out the slow way, so it is
	 * written down here as well as in the class.
	 *
	 * @return void
	 */
	public function test_the_setup_lives_on_the_dashboard_page(): void {
		$this->assertStringContainsString( 'page=wowstudio-accessibility-remediation', Onboarding::url() );
		$this->assertStringContainsString( Onboarding::QUERY_ARG . '=1', Onboarding::url() );
	}

	/**
	 * The view is only rendered when it is asked for.
	 *
	 * @return void
	 */
	public function test_it_is_only_requested_when_the_argument_is_present(): void {
		$this->assertFalse( Onboarding::is_requested() );

		$_GET[ Onboarding::QUERY_ARG ] = '1';

		$this->assertTrue( Onboarding::is_requested() );
	}

	/**
	 * Finishing is recorded, and can be taken back.
	 *
	 * @return void
	 */
	public function test_finishing_is_recorded_and_reversible(): void {
		$written = array();

		Functions\when( 'update_option' )->alias(
			static function ( string $name, $value ) use ( &$written ): bool {
				$written[ $name ] = $value;

				return true;
			}
		);

		Onboarding::set_done( true );
		$this->assertTrue( $written[ Onboarding::OPTION ]['done'] );

		Onboarding::set_done( false );
		$this->assertFalse( $written[ Onboarding::OPTION ]['done'] );
	}
}
