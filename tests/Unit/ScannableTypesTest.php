<?php
/**
 * Tests for which content the plugin works on.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use WOWStudio\AccessibilityKit\Db\ContentIndex;
use WOWStudio\AccessibilityKit\Support\ScannableTypes;
use WOWStudio\AccessibilityKit\Tests\Doubles\FakeScanStore;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests the allowlist of scannable post types.
 *
 * @covers \WOWStudio\AccessibilityKit\Support\ScannableTypes
 */
final class ScannableTypesTest extends TestCase {

	/**
	 * Pretends every named type is registered unless a test says otherwise.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'post_type_exists' )->justReturn( true );
	}

	/**
	 * Posts and pages, and nothing else.
	 *
	 * @return void
	 */
	public function test_the_default_is_posts_and_pages(): void {
		$this->assertSame( array( 'page', 'post' ), ScannableTypes::names() );
	}

	/**
	 * Templates, patterns and page-builder libraries are all out.
	 *
	 * Named individually because each got in by a different route. A block
	 * theme registers template types; the pattern editor registers another;
	 * Elementor's library registers itself public and labels itself "My
	 * Templates", which is how it came to sit in the content picker beside
	 * Posts and Pages as though it were somewhere a visitor could go.
	 *
	 * @dataProvider not_pages
	 *
	 * @param string $type A post type that is not a document.
	 * @return void
	 */
	public function test_things_that_are_not_pages_are_excluded( string $type ): void {
		$this->assertFalse( ScannableTypes::includes( $type ) );
	}

	/**
	 * Post types that hold something other than a page somebody can open.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function not_pages(): array {
		return array(
			'block theme templates' => array( 'wp_template' ),
			'block theme parts'     => array( 'wp_template_part' ),
			'patterns'              => array( 'wp_block' ),
			'navigation menus'      => array( 'wp_navigation' ),
			'global styles'         => array( 'wp_global_styles' ),
			'an Elementor template' => array( 'elementor_library' ),
			'a custom post type'    => array( 'product' ),
			'attachments'           => array( 'attachment' ),
		);
	}

	/**
	 * A site that knows better can say so.
	 *
	 * The filter is the whole of the escape hatch, and it has to widen as well
	 * as narrow: plenty of custom post types are pages in all but name, and the
	 * default is conservative rather than correct.
	 *
	 * @return void
	 */
	public function test_a_filter_can_widen_the_list(): void {
		Filters\expectApplied( 'wsak_post_types' )
			->andReturn( array( 'page', 'post', 'product' ) );

		$this->assertTrue( ScannableTypes::includes( 'product' ) );
	}

	/**
	 * A name nothing registered is dropped rather than passed to a query.
	 *
	 * `post_type` with an unrecognised value matches nothing, so a typo in a
	 * filter would look exactly like a site with no content.
	 *
	 * @return void
	 */
	public function test_unregistered_names_are_dropped(): void {
		Functions\when( 'post_type_exists' )->alias(
			static fn( string $type ): bool => 'page' === $type
		);

		Filters\expectApplied( 'wsak_post_types' )
			->andReturn( array( 'page', 'pgae' ) );

		$this->assertSame( array( 'page' ), ScannableTypes::names() );
	}

	/**
	 * Duplicates collapse.
	 *
	 * @return void
	 */
	public function test_duplicates_collapse(): void {
		Filters\expectApplied( 'wsak_post_types' )
			->andReturn( array( 'page', 'page', 'post' ) );

		$this->assertSame( array( 'page', 'post' ), ScannableTypes::names() );
	}

	/**
	 * A post is covered when its type is.
	 *
	 * @return void
	 */
	public function test_a_post_is_covered_by_its_type(): void {
		Functions\when( 'get_post_type' )->justReturn( 'page' );
		$this->assertTrue( ScannableTypes::covers( 12 ) );

		Functions\when( 'get_post_type' )->justReturn( 'wp_block' );
		$this->assertFalse( ScannableTypes::covers( 12 ) );
	}

	/**
	 * A post id that resolves to nothing is not covered.
	 *
	 * @return void
	 */
	public function test_a_missing_post_is_not_covered(): void {
		Functions\when( 'get_post_type' )->justReturn( false );

		$this->assertFalse( ScannableTypes::covers( 999 ) );
	}

	/**
	 * Asking for a type nobody was offered returns nothing.
	 *
	 * The picker only shows posts and pages, but the type arrives from the
	 * request rather than from the picker, so a restriction enforced only in
	 * the interface is a restriction on which buttons happen to be on screen.
	 *
	 * @return void
	 */
	public function test_the_listing_refuses_a_type_that_is_not_offered(): void {
		$listing = ( new ContentIndex( new FakeScanStore() ) )->items( 'elementor_library' );

		$this->assertSame( array(), $listing['items'] );
		$this->assertSame( 0, $listing['total'] );
	}

	/**
	 * Every gate that decides what gets scanned reads this one list.
	 *
	 * Source-reading, because the failure is silent in both directions: a gate
	 * that stops consulting the list lets templates back into a picker with
	 * nothing to say they have returned, and one that never consulted it was
	 * never restricted at all. There is no error either way.
	 *
	 * @dataProvider gates
	 *
	 * @param string $file  Path relative to the plugin root.
	 * @param string $why   What this file would let through without it.
	 * @return void
	 */
	public function test_every_gate_reads_the_same_list( string $file, string $why ): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local source file in a unit test; WordPress is not loaded.
		$source = (string) file_get_contents( __DIR__ . '/../../' . $file );

		$this->assertStringContainsString( 'ScannableTypes::', $source, $why );
		$this->assertStringNotContainsString(
			"get_post_types( array( 'public' => true )",
			$source,
			$file . ' still walks every public post type.'
		);
	}

	/**
	 * The places that decide what may be scanned.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function gates(): array {
		return array(
			'the content picker'    => array(
				'src/Db/ContentIndex.php',
				'The bulk picker would offer every public type again.',
			),
			'the single-page route' => array(
				'src/Rest/ScanController.php',
				'A scan of any post id would be accepted.',
			),
			'the bulk route'        => array(
				'src/Rest/RunController.php',
				'A run could be started over ids the picker never offered.',
			),
			'the admin column'      => array(
				'src/Admin/ContentColumns.php',
				'A column reading "not checked" would appear on list tables nothing checks.',
			),
			'the theme scan'        => array(
				'src/Scanner/TemplateScan.php',
				'The theme profile would be taken from a template or a pattern.',
			),
			'the coverage figure'   => array(
				'src/Rest/OverviewController.php',
				'"8 of 40 scanned" would count content no scan will ever reach.',
			),
			'the command line'      => array(
				'src/Cli/Command.php',
				'wp wsak scan would be a way around the whole thing.',
			),
		);
	}
}
