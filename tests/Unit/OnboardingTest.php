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
 * The behaviour worth pinning down is all about restraint: this thing takes
 * over somebody's screen, so every case where it must not do that is a case
 * worth a test.
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
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'wp_doing_cron' )->justReturn( false );
		Functions\when( 'is_network_admin' )->justReturn( false );
		Functions\when( 'current_user_can' )->justReturn( true );
		unset( $_GET['activate-multi'] );
	}

	/**
	 * Clears request state so one test cannot leak into the next.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		unset( $_GET['activate-multi'], $_GET[ Onboarding::QUERY_ARG ] );

		parent::tearDown();
	}

	/**
	 * Activating a fresh install arms the redirect.
	 *
	 * @return void
	 */
	public function test_activation_arms_the_redirect(): void {
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\expect( 'set_transient' )
			->once()
			->with( Onboarding::REDIRECT_TRANSIENT, 1, 300 );

		Onboarding::note_activation();

		$this->addToAssertionCount( 1 );
	}

	/**
	 * Reactivating after the setup was finished does not arm it again.
	 *
	 * Deactivating and reactivating a plugin is something people do while
	 * debugging something else entirely. Being marched back through a setup
	 * they have already completed, every time, would be its own small insult.
	 *
	 * @return void
	 */
	public function test_activation_after_setup_arms_nothing(): void {
		Functions\when( 'get_option' )->justReturn( array( 'done' => true ) );
		Functions\expect( 'set_transient' )->never();

		Onboarding::note_activation();

		$this->addToAssertionCount( 1 );
	}

	/**
	 * With nothing armed, nothing happens.
	 *
	 * @return void
	 */
	public function test_no_flag_means_no_redirect(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\expect( 'delete_transient' )->never();

		$this->assertFalse( ( new Onboarding() )->should_open_setup() );
	}

	/**
	 * The first admin request after activation opens the setup.
	 *
	 * @return void
	 */
	public function test_the_first_request_after_activation_opens_the_setup(): void {
		Functions\when( 'get_transient' )->justReturn( 1 );
		Functions\when( 'delete_transient' )->justReturn( true );

		$this->assertTrue( ( new Onboarding() )->should_open_setup() );
	}

	/**
	 * The flag is spent whether or not it is acted on.
	 *
	 * Every early return below is a case where redirecting would be wrong for
	 * this request. None of them is a reason to keep the flag and ambush a
	 * later one.
	 *
	 * @dataProvider reasons_not_to_redirect
	 *
	 * @param callable $arrange Sets up the case.
	 * @return void
	 */
	public function test_the_flag_is_spent_even_when_it_is_not_acted_on( callable $arrange ): void {
		Functions\when( 'get_transient' )->justReturn( 1 );
		Functions\expect( 'delete_transient' )
			->once()
			->with( Onboarding::REDIRECT_TRANSIENT );

		$arrange();

		$this->assertFalse( ( new Onboarding() )->should_open_setup() );
	}

	/**
	 * Cases where taking over the screen would be wrong.
	 *
	 * @return array<string, array{0: callable}>
	 */
	public static function reasons_not_to_redirect(): array {
		return array(
			'during an AJAX request' => array(
				static function (): void {
					Functions\when( 'wp_doing_ajax' )->justReturn( true );
				},
			),
			'during cron'            => array(
				static function (): void {
					Functions\when( 'wp_doing_cron' )->justReturn( true );
				},
			),
			'in the network admin'   => array(
				static function (): void {
					Functions\when( 'is_network_admin' )->justReturn( true );
				},
			),
			// WordPress is mid-loop over several plugins and the ones after
			// this still have to be activated.
			'activating in bulk'     => array(
				static function (): void {
					$_GET['activate-multi'] = '1';
				},
			),
			'without the capability' => array(
				static function (): void {
					Functions\when( 'current_user_can' )->justReturn( false );
				},
			),
		);
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
		$this->assertStringContainsString( 'page=wowstudio-accessibility-kit', Onboarding::url() );
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
