<?php
/**
 * Tests for sorting theme findings by what would fix them.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Functions;
use WOWStudio\AccessibilityKit\Db\Issue;
use WOWStudio\AccessibilityKit\Remediation\ThemeTriage;
use WOWStudio\AccessibilityKit\Scanner\Detection;
use WOWStudio\AccessibilityKit\Scanner\Fingerprint;
use WOWStudio\AccessibilityKit\Scanner\IssueStatus;
use WOWStudio\AccessibilityKit\Scanner\RuleRegistry;
use WOWStudio\AccessibilityKit\Scanner\ScanPass;
use WOWStudio\AccessibilityKit\Scanner\Severity;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests that "this is a setting" is only ever said when it is certain.
 *
 * The setting tier is the best outcome this plugin can offer for a theme fault:
 * a permanent fix, no code, done without us. It is also the one that fails
 * worst when it is wrong. Somebody sent to Appearance → Menus for an image in
 * a footer will look, find nothing, and stop believing the rest of the list.
 *
 * So every case below is about the boundary rather than the happy path.
 *
 * @covers \WOWStudio\AccessibilityKit\Remediation\ThemeTriage
 */
final class ThemeTriageTest extends TestCase {

	/**
	 * Wires the theme and attachment lookups.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'get_theme_mod' )->justReturn( 0 );
		Functions\when( 'get_attached_file' )->justReturn( '' );
		Functions\when( 'get_edit_post_link' )->justReturn( 'http://example.test/wp-admin/post.php?post=9' );
		Functions\when( 'admin_url' )->alias( static fn( string $path ): string => 'http://example.test/wp-admin/' . $path );
		Functions\when( 'wp_is_block_theme' )->justReturn( false );
		Functions\when( 'wp_basename' )->alias( static fn( string $path ): string => basename( $path ) );
	}

	/**
	 * Builds a finding.
	 *
	 * @param string $rule_id Rule.
	 * @param string $context Markup recorded with it.
	 * @return Issue
	 */
	private function issue( string $rule_id, string $context ): Issue {
		return new Issue(
			1,
			1,
			0,
			$rule_id,
			'1.1.1',
			Severity::Critical,
			Detection::Auto,
			ScanPass::Server,
			IssueStatus::Open,
			Fingerprint::of( $rule_id, $context ),
			'/html/body/img',
			$context,
			'message',
			'',
			0,
			'2026-09-01 00:00:00',
			'2026-09-01 00:00:00'
		);
	}

	/**
	 * Says it is a setting for the actual site logo.
	 *
	 * @return void
	 */
	public function test_the_site_logo_is_a_setting(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 9 );
		Functions\when( 'get_attached_file' )->justReturn( '/uploads/acme-logo.png' );

		$out = ( new ThemeTriage() )->triage(
			$this->issue( 'img-alt-missing', '<img src="/uploads/acme-logo.png">' )
		);

		$this->assertSame( ThemeTriage::TIER_SETTING, $out['tier'] );
		$this->assertStringContainsString( 'post.php', $out['url'] );
	}

	/**
	 * Recognises the logo through a resized variant.
	 *
	 * WordPress serves whichever size fits, so the markup rarely carries the
	 * original file name. Matching only the exact name would send every logo to
	 * the hand-off pile.
	 *
	 * @return void
	 */
	public function test_a_resized_logo_is_still_the_logo(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 9 );
		Functions\when( 'get_attached_file' )->justReturn( '/uploads/acme-logo.png' );

		$out = ( new ThemeTriage() )->triage(
			$this->issue( 'img-alt-missing', '<img src="/uploads/acme-logo-300x100.png">' )
		);

		$this->assertSame( ThemeTriage::TIER_SETTING, $out['tier'] );
	}

	/**
	 * A different image is not the logo, however tempting.
	 *
	 * The failure this test exists for: a plausible-looking guess that sends
	 * somebody to the media library entry for the wrong file.
	 *
	 * @return void
	 */
	public function test_another_image_is_not_the_logo(): void {
		Functions\when( 'get_theme_mod' )->justReturn( 9 );
		Functions\when( 'get_attached_file' )->justReturn( '/uploads/acme-logo.png' );

		$out = ( new ThemeTriage() )->triage(
			$this->issue( 'img-alt-missing', '<img src="/uploads/hero-photo.jpg">' )
		);

		$this->assertSame( ThemeTriage::TIER_HANDOFF, $out['tier'] );
	}

	/**
	 * With no logo set, nothing is a logo.
	 *
	 * @return void
	 */
	public function test_no_logo_set_means_no_logo_match(): void {
		$out = ( new ThemeTriage() )->triage(
			$this->issue( 'img-alt-missing', '<img src="/uploads/anything.png">' )
		);

		$this->assertSame( ThemeTriage::TIER_HANDOFF, $out['tier'] );
	}

	/**
	 * A menu item goes to the screen this theme actually uses.
	 *
	 * Appearance → Menus is not merely the wrong screen on a block theme, it is
	 * frequently not registered at all — a link that 404s is worse than none.
	 *
	 * @return void
	 */
	public function test_menu_items_go_to_the_right_screen_for_the_theme(): void {
		$triage = new ThemeTriage();
		$issue  = $this->issue( 'link-name-missing', '<a class="menu-item" href="/x"></a>' );

		$this->assertStringContainsString( 'nav-menus.php', $triage->triage( $issue )['url'] );

		Functions\when( 'wp_is_block_theme' )->justReturn( true );

		$block = $triage->triage(
			$this->issue( 'link-name-missing', '<a class="wp-block-navigation-item__content" href="/x"></a>' )
		);

		$this->assertStringContainsString( 'site-editor.php', $block['url'] );
	}

	/**
	 * Anything unrecognised is handed over, with the change written out.
	 *
	 * @return void
	 */
	public function test_everything_else_is_handed_over_with_a_correction(): void {
		$out = ( new ThemeTriage() )->triage(
			$this->issue( 'html-lang-missing', '<html>' )
		);

		$this->assertSame( ThemeTriage::TIER_HANDOFF, $out['tier'] );
		$this->assertStringContainsString( 'language_attributes', $out['snippet'] );
		$this->assertSame( '', $out['url'], 'A hand-off has no screen to send anybody to.' );
	}

	/**
	 * The snippet is code or it is nothing.
	 *
	 * It used to be neither. Where no correction was listed the method fell
	 * through to the rule's description — a paragraph of English — and the
	 * panel set it in the dark monospaced block kept for markup, beneath a line
	 * telling the reader to send the change below to their developer. It was
	 * not a change: it was the explanation from further up the same card,
	 * reworded, typeset as though it were something you could paste. On a real
	 * finding it came out as one unwrapped line two thousand pixels wide.
	 *
	 * The text is still worth showing. It comes out as `guidance` and reads as
	 * the prose it is.
	 *
	 * @return void
	 */
	public function test_prose_is_never_passed_off_as_a_snippet(): void {
		$rule = RuleRegistry::with_defaults()->descriptor( 'heading-level-skipped' );

		$this->assertNotNull( $rule, 'The rule this test is about has been renamed or removed.' );

		$out = ( new ThemeTriage() )->triage( $this->issue( 'heading-level-skipped', '<h4>Speakers</h4>' ), $rule );

		$this->assertSame( '', $out['snippet'], 'There is no general correction for this rule.' );
		$this->assertSame( $rule->description(), $out['guidance'] );
		$this->assertStringNotContainsString(
			'the change below',
			$out['instruction'],
			'Nothing is below: the card must not point at a snippet it has not got.'
		);
	}

	/**
	 * Where there is a correction, it is pointed at.
	 *
	 * @return void
	 */
	public function test_a_real_correction_is_pointed_at_and_stands_alone(): void {
		$out = ( new ThemeTriage() )->triage( $this->issue( 'html-lang-missing', '<html>' ) );

		$this->assertStringContainsString( 'language_attributes', $out['snippet'] );
		$this->assertSame( '', $out['guidance'], 'The snippet says it; saying it twice is padding.' );
		$this->assertStringContainsString( 'the change below', $out['instruction'] );
	}

	/**
	 * A hand-off always says something about what to change.
	 *
	 * @return void
	 */
	public function test_every_handoff_carries_a_correction(): void {
		$rules = array(
			'landmark-main-missing',
			'link-name-missing',
			'button-name-missing',
			'form-control-label-missing',
			'iframe-title-missing',
			'table-headers-missing',
		);

		foreach ( $rules as $rule ) {
			$out = ( new ThemeTriage() )->triage( $this->issue( $rule, '<div></div>' ) );

			$this->assertSame( ThemeTriage::TIER_HANDOFF, $out['tier'], $rule );
			$this->assertNotSame( '', trim( $out['snippet'] ), $rule . ' is handed over with nothing to do about it.' );
		}
	}
}
