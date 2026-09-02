<?php
/**
 * Custom opt-in email tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use WOWStudio\AccessibilityKit\Optin\EmailOptin;
use WOWStudio\AccessibilityKit\Support\Capabilities;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests the "use a different email address" option on the opt-in screen.
 *
 * @covers \WOWStudio\AccessibilityKit\Optin\EmailOptin
 */
final class EmailOptinTest extends TestCase {

	/**
	 * Without the Freemius SDK the service registers nothing at all.
	 *
	 * The paid build ships the SDK, but a stripped or partial install may not
	 * have it, and this plugin is built to come up as a working free plugin in
	 * that case rather than fatal on a missing function. Registering the
	 * admin-post handler anyway would leave an endpoint that calls wsak_fs()
	 * and dies.
	 *
	 * @return void
	 */
	public function test_registers_nothing_without_the_sdk(): void {
		$this->assertFalse(
			function_exists( 'wsak_fs' ),
			'The SDK accessor must be absent for this test to mean anything.'
		);

		Actions\expectAdded( 'admin_post_' . EmailOptin::ACTION )->never();

		( new EmailOptin() )->register();

		$this->addToAssertionCount( 1 );
	}

	/**
	 * The form is not rendered for a user who may not run scans.
	 *
	 * It sits on a screen WordPress has already shown them, so the capability
	 * has to be checked here rather than relied on from the page around it.
	 *
	 * @return void
	 */
	public function test_form_is_hidden_without_the_capability(): void {
		Functions\when( 'current_user_can' )->alias(
			static fn( string $capability ): bool => Capabilities::RUN_SCAN !== $capability
		);

		ob_start();
		( new EmailOptin() )->render_form();
		$html = (string) ob_get_clean();

		$this->assertSame( '', $html, 'A user without the capability must see no form.' );
	}

	/**
	 * The rendered form carries a nonce, a label, and the admin-post action.
	 *
	 * @return void
	 */
	public function test_form_is_labelled_and_nonced(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'admin_url' )->alias(
			static fn( string $path = '' ): string => 'https://example.test/wp-admin/' . $path
		);
		Functions\when( 'wp_nonce_field' )->alias(
			static function (): void {
				echo '<input type="hidden" name="_wpnonce" value="x" />';
			}
		);

		ob_start();
		( new EmailOptin() )->render_form();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '_wpnonce', $html, 'The form must be nonced.' );
		$this->assertStringContainsString( 'for="wsak-optin-email"', $html, 'The field must be labelled.' );
		$this->assertStringContainsString( 'type="email"', $html, 'The field must be an email input.' );
		$this->assertStringContainsString( EmailOptin::ACTION, $html, 'The form must target the handler.' );
	}
}
