<?php
/**
 * Schema tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Mockery;
use WOWStudio\AccessibilityKit\Db\Schema;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests table naming and teardown.
 *
 * @covers \WOWStudio\AccessibilityKit\Db\Schema
 */
final class SchemaTest extends TestCase {

	/**
	 * Installs a mock $wpdb.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$wpdb            = Mockery::mock( 'wpdb' );
		$wpdb->prefix    = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
	}

	/**
	 * Table names carry the site prefix and the plugin prefix.
	 *
	 * @return void
	 */
	public function test_table_names_are_prefixed(): void {
		$this->assertSame( 'wp_wsak_scans', Schema::scans_table() );
		$this->assertSame( 'wp_wsak_issues', Schema::issues_table() );
	}

	/**
	 * Multisite subsites get their own tables, not the main site's.
	 *
	 * @return void
	 */
	public function test_table_names_follow_the_active_site_prefix(): void {
		$GLOBALS['wpdb']->prefix = 'wp_7_';

		$this->assertSame( 'wp_7_wsak_scans', Schema::scans_table() );
		$this->assertSame( 'wp_7_wsak_issues', Schema::issues_table() );
	}

	/**
	 * Every table the plugin owns is listed, so uninstall cannot miss one.
	 *
	 * A table added without being listed here would survive uninstall and be
	 * left orphaned in the database, which is the failure this guards against.
	 *
	 * @return void
	 */
	public function test_tables_lists_every_table(): void {
		$this->assertSame(
			array( 'wp_wsak_scans', 'wp_wsak_issues', 'wp_wsak_fixes', 'wp_wsak_decisions' ),
			Schema::tables()
		);
	}

	/**
	 * The fixes table carries the site prefix too.
	 *
	 * @return void
	 */
	public function test_fixes_table_is_prefixed(): void {
		$this->assertSame( 'wp_wsak_fixes', Schema::fixes_table() );
	}
}
