<?php
/**
 * Site-wide fix tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Functions;
use WOWStudio\AccessibilityKit\SiteFixes\Fixes\CommentAndSearchLabels;
use WOWStudio\AccessibilityKit\SiteFixes\Fixes\DownloadFileInfo;
use WOWStudio\AccessibilityKit\SiteFixes\Fixes\NewWindowWarning;
use WOWStudio\AccessibilityKit\SiteFixes\ProvidesCss;
use WOWStudio\AccessibilityKit\SiteFixes\RunsInBrowser;
use WOWStudio\AccessibilityKit\SiteFixes\SiteFix;
use WOWStudio\AccessibilityKit\SiteFixes\SiteFixManager;
use WOWStudio\AccessibilityKit\SiteFixes\SiteFixes;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests the site-wide fix layer.
 *
 * The content filters get the most attention here, and deliberately. Everything
 * else in this layer either adds a hook or does not; those two rewrite what
 * visitors read on pages somebody else wrote, on every render, and the failure
 * mode is not "the fix did not work" but "the content is now wrong". So they are
 * tested for what they leave alone at least as hard as for what they change.
 *
 * @covers \WOWStudio\AccessibilityKit\SiteFixes\SiteFixes
 * @covers \WOWStudio\AccessibilityKit\SiteFixes\SiteFixManager
 * @covers \WOWStudio\AccessibilityKit\SiteFixes\Fixes\NewWindowWarning
 * @covers \WOWStudio\AccessibilityKit\SiteFixes\Fixes\DownloadFileInfo
 * @covers \WOWStudio\AccessibilityKit\SiteFixes\Fixes\CommentAndSearchLabels
 * @covers \WOWStudio\AccessibilityKit\SiteFixes\Fixes\ViewportScalable
 * @covers \WOWStudio\AccessibilityKit\SiteFixes\Fixes\StripPositiveTabindex
 * @covers \WOWStudio\AccessibilityKit\SiteFixes\Fixes\StripRedundantTitle
 * @covers \WOWStudio\AccessibilityKit\SiteFixes\Fixes\LabelFormFields
 * @covers \WOWStudio\AccessibilityKit\SiteFixes\Fixes\EmptySearchMessage
 */
final class SiteFixesTest extends TestCase {

	/**
	 * Adds the helpers these fixes reach for.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'wp_strip_all_tags' )->alias(
			static fn( string $text ): string => (string) preg_replace( '#<[^>]*>#', '', $text )
		);

		// No media library under test, so no size is ever found. The size path
		// is exercised through size_format below when one is.
		Functions\when( 'attachment_url_to_postid' )->justReturn( 0 );
		Functions\when( 'size_format' )->justReturn( '1.2 MB' );
	}

	/**
	 * Every built-in fix is registered, with a unique id.
	 *
	 * @return void
	 */
	public function test_the_registry_holds_every_fix_once(): void {
		$fixes = SiteFixes::with_defaults();
		$all   = $fixes->all();

		$this->assertCount( 14, $all );
		$this->assertSame( array_keys( $all ), array_unique( array_keys( $all ) ) );

		foreach ( $all as $id => $fix ) {
			$this->assertInstanceOf( SiteFix::class, $fix );
			$this->assertSame( $id, $fix->id(), 'A fix is filed under an id it does not answer to.' );
			$this->assertNotSame( '', $fix->title() );
			$this->assertNotSame( '', $fix->description() );
		}
	}

	/**
	 * Every fix says what it might collide with.
	 *
	 * These change the whole site for every visitor. A control that does that
	 * without saying what it might disturb is asking somebody to guess.
	 *
	 * @return void
	 */
	public function test_every_fix_carries_a_caveat(): void {
		foreach ( SiteFixes::with_defaults()->all() as $fix ) {
			$this->assertNotSame( '', $fix->caveat(), $fix->id() . ' has nothing to say about what it might disturb.' );
		}
	}

	/**
	 * A fix can be found by the rule its findings come from.
	 *
	 * @return void
	 */
	public function test_fixes_can_be_found_by_rule(): void {
		$fixes = SiteFixes::with_defaults();

		$this->assertNotEmpty( $fixes->for_rule( 'document-title-missing' ) );
		$this->assertNotEmpty( $fixes->for_rule( 'link-opens-new-window' ) );
		$this->assertSame( array(), $fixes->for_rule( 'not-a-rule' ) );
	}

	/**
	 * Switching a fix on and off round-trips.
	 *
	 * @return void
	 */
	public function test_a_fix_can_be_switched_on_and_off(): void {
		$stored = array();

		// A closure taking $stored by reference, not an arrow function: those
		// capture by value at the point they are written, so it would read an
		// empty array forever however many times update_option was called.
		Functions\when( 'get_option' )->alias(
			static function ( $name, $fallback = false ) use ( &$stored ) {
				return $stored[ $name ] ?? $fallback;
			}
		);
		Functions\when( 'update_option' )->alias(
			static function ( $name, $value ) use ( &$stored ): bool {
				$stored[ $name ] = $value;

				return true;
			}
		);

		$manager = new SiteFixManager();

		$this->assertFalse( $manager->is_enabled( 'skip-link' ) );
		$this->assertTrue( $manager->set( 'skip-link', true ) );
		$this->assertTrue( $manager->is_enabled( 'skip-link' ) );
		$this->assertTrue( $manager->set( 'skip-link', false ) );
		$this->assertFalse( $manager->is_enabled( 'skip-link' ) );

		// An id nothing answers to is refused rather than stored, so a typo in
		// a request cannot leave a permanent ghost in the option.
		$this->assertFalse( $manager->set( 'not-a-fix', true ) );
		$this->assertFalse( $manager->is_enabled( 'not-a-fix' ) );
	}

	/**
	 * The new-tab warning is added, once, inside the link.
	 *
	 * @return void
	 */
	public function test_the_new_tab_warning_is_added_inside_the_link(): void {
		$fix = new NewWindowWarning();

		$out = $fix->annotate( '<p><a href="/x" target="_blank">Our partner</a></p>' );

		$this->assertStringContainsString( 'opens in a new tab', $out );
		$this->assertStringContainsString( 'Our partner<span', $out, 'The warning belongs inside the link, or it is read as loose text after it.' );
		$this->assertStringEndsWith( '</a></p>', $out );
	}

	/**
	 * It leaves alone every link it was not asked about.
	 *
	 * @return void
	 */
	public function test_the_new_tab_warning_leaves_other_content_byte_identical(): void {
		$fix = new NewWindowWarning();

		$untouched = '<p>Some prose with <a href="/a">an ordinary link</a> and an <em>emphasis</em>.</p>';
		$this->assertSame( $untouched, $fix->annotate( $untouched ), 'Content with no new-tab link must come back unchanged.' );

		$already = '<p><a href="/x" target="_blank">Report (opens in a new tab)</a></p>';
		$this->assertSame( $already, $fix->annotate( $already ), 'A link that already says so must not be told twice.' );

		// The cheap gate means content without _blank never reaches the pattern
		// at all, which is what keeps this off most requests.
		$odd = '<p>An unclosed <a href="/a">link and some &amp; entities &lt;here&gt;</p>';
		$this->assertSame( $odd, $fix->annotate( $odd ) );
	}

	/**
	 * Only the matched link changes; the surrounding markup is preserved.
	 *
	 * @return void
	 */
	public function test_the_new_tab_warning_preserves_surrounding_markup(): void {
		$fix = new NewWindowWarning();

		$in  = '<p>Before &amp; after <a href="/x" target="_blank" rel="noopener">Partner</a> and <img src="/a.png" alt="A &quot;quoted&quot; name"> after.</p>';
		$out = $fix->annotate( $in );

		$this->assertStringContainsString( 'Before &amp; after ', $out );
		$this->assertStringContainsString( '<img src="/a.png" alt="A &quot;quoted&quot; name"> after.</p>', $out, 'Anything outside the anchor must be returned exactly as it arrived.' );
	}

	/**
	 * The download note names the format.
	 *
	 * @return void
	 */
	public function test_download_links_are_told_what_they_are(): void {
		$fix = new DownloadFileInfo();

		$out = $fix->annotate( '<p><a href="/files/report.pdf">The annual report</a></p>' );
		$this->assertStringContainsString( 'PDF', $out );
		$this->assertStringContainsString( 'wsak-file-info', $out );

		$out = $fix->annotate( '<p><a href="/files/budget.xlsx">Budget</a></p>' );
		$this->assertStringContainsString( 'Excel', $out );
	}

	/**
	 * It says nothing about links that are not downloads, or already say it.
	 *
	 * @return void
	 */
	public function test_download_note_is_not_added_where_it_does_not_belong(): void {
		$fix = new DownloadFileInfo();

		foreach (
			array(
				'<p><a href="/about">About us</a></p>',
				'<p><a href="/logo.png">Our logo</a></p>',
				'<p><a href="/files/report.pdf">The annual report (PDF)</a></p>',
				'<p><a href="/files/report.pdf">Download the PDF report</a></p>',
			) as $untouched
		) {
			$this->assertSame( $untouched, $fix->annotate( $untouched ), 'Nothing should have been added to: ' . $untouched );
		}
	}

	/**
	 * The search field is named only when it has no name.
	 *
	 * @return void
	 */
	public function test_the_search_field_is_named_when_it_is_not(): void {
		$fix = new CommentAndSearchLabels();

		$bare = '<form role="search"><input type="search" name="s" value=""><button>Go</button></form>';
		$this->assertStringContainsString( 'aria-label=', $fix->label_search_field( $bare ) );

		$labelled = '<form role="search"><label for="s">Search</label><input type="search" name="s" id="s"></form>';
		$this->assertSame( $labelled, $fix->label_search_field( $labelled ), 'A form that already has a label must not get a second name.' );

		$aria = '<form role="search"><input type="search" name="s" aria-label="Search"></form>';
		$this->assertSame( $aria, $fix->label_search_field( $aria ) );
	}

	/**
	 * CSS survives being printed, combinators and all.
	 *
	 * The regression this guards shipped for exactly one afternoon. The
	 * stylesheet was run through esc_html() on the way into the style element,
	 * which looks like the careful thing to do and is wrong: a style element is
	 * raw text, entities inside it are never decoded, and `p > a` came out as
	 * `p &gt; a`. Every rule using a child combinator stopped matching, so the
	 * underline fix was a switch that turned on and did nothing.
	 *
	 * @return void
	 */
	public function test_printed_css_keeps_its_selectors(): void {
		$manager = new SiteFixManager();

		$method = new \ReflectionMethod( SiteFixManager::class, 'safe_css' );
		$method->setAccessible( true );

		$printed = $method->invoke( $manager, 'p > a[href]:has(> img) { color: red; }' );

		$this->assertStringContainsString( 'p > a[href]', $printed, 'The child combinator must survive.' );
		$this->assertStringNotContainsString( '&gt;', $printed );

		// And the one sequence that could end the element early is still gone.
		$this->assertStringNotContainsString( '</style', $method->invoke( $manager, 'a {} </style><script>x</script>' ) );
	}

	/**
	 * Exactly the fixes that need the browser are marked as needing it.
	 *
	 * The marker decides whether a script is served to every visitor, so a fix
	 * gaining or losing it should be a deliberate act rather than something
	 * that happens because a class was copied.
	 *
	 * @return void
	 */
	public function test_only_the_dom_fixes_run_in_the_browser(): void {
		$in_browser = array();

		foreach ( SiteFixes::with_defaults()->all() as $fix ) {
			if ( $fix instanceof RunsInBrowser ) {
				$in_browser[] = $fix->id();
			}
		}

		sort( $in_browser );

		$this->assertSame(
			array(
				'empty-search-message',
				'label-form-fields',
				'strip-positive-tabindex',
				'strip-redundant-title',
				'viewport-scalable',
			),
			$in_browser
		);
	}

	/**
	 * Every browser fix says it needs JavaScript.
	 *
	 * These do nothing at all for a reader with script off, which is a
	 * different kind of limitation from the rest of the layer and one somebody
	 * has to be told about before switching one on.
	 *
	 * @return void
	 */
	public function test_browser_fixes_admit_they_need_javascript(): void {
		foreach ( SiteFixes::with_defaults()->all() as $fix ) {
			if ( ! $fix instanceof RunsInBrowser ) {
				continue;
			}

			$this->assertStringContainsStringIgnoringCase(
				'javascript',
				$fix->caveat(),
				$fix->id() . ' runs in the browser and does not say so.'
			);
		}
	}

	/**
	 * The front-end script exists, and answers to the ids the fixes use.
	 *
	 * Source-reading, like the JavaScript tests elsewhere in this project: the
	 * failure it guards is a fix id changing on one side of the boundary only,
	 * which leaves a switch that turns on and does nothing — and there is no
	 * error anywhere to notice.
	 *
	 * @return void
	 */
	public function test_the_front_end_script_knows_every_browser_fix(): void {
		$path = __DIR__ . '/../../assets/front/site-fixes.js';

		$this->assertFileExists( $path );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local source file in a unit test; WordPress is not loaded.
		$script = (string) file_get_contents( $path );

		foreach ( SiteFixes::with_defaults()->all() as $fix ) {
			if ( ! $fix instanceof RunsInBrowser ) {
				continue;
			}

			$this->assertStringContainsString(
				"'" . $fix->id() . "'",
				$script,
				$fix->id() . ' is switched on in PHP and never mentioned in the script.'
			);
		}
	}

	/**
	 * Every CSS fix returns something, and nothing that closes the tag.
	 *
	 * @return void
	 */
	public function test_css_fixes_return_usable_rules(): void {
		foreach ( SiteFixes::with_defaults()->all() as $fix ) {
			if ( ! $fix instanceof ProvidesCss ) {
				continue;
			}

			$css = $fix->css();

			$this->assertNotSame( '', trim( $css ), $fix->id() . ' declares CSS and returns none.' );
			$this->assertStringNotContainsString( '</style', $css, $fix->id() . ' could close the style element it is printed into.' );
		}
	}
}
