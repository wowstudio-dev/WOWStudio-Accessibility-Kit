<?php
/**
 * Database schema.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Db;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and removes the plugin's tables.
 *
 * The SQL here is written for dbDelta, which parses the statements rather than
 * executing them blindly. It is unusually strict: one column per line, two
 * spaces after PRIMARY KEY, and every index given an explicit name. Reformatting
 * this for readability will silently stop upgrades from applying.
 *
 * @since 0.2.0
 */
final class Schema {

	/**
	 * Schema version. Bump when a table definition changes.
	 *
	 * @since 0.2.0
	 * @var string
	 */
	public const VERSION = '1.4.0';

	/**
	 * Option holding the installed schema version.
	 *
	 * @since 0.2.0
	 * @var string
	 */
	public const VERSION_OPTION = 'wsak_db_version';

	/**
	 * Returns the scans table name, including the site prefix.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public static function scans_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'wsak_scans';
	}

	/**
	 * Returns the issues table name, including the site prefix.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public static function issues_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'wsak_issues';
	}

	/**
	 * Returns the fixes table name, including the site prefix.
	 *
	 * @since 0.6.0
	 *
	 * @return string
	 */
	public static function fixes_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'wsak_fixes';
	}

	/**
	 * Returns every table this plugin owns on the current site.
	 *
	 * @since 0.2.0
	 *
	 * @return string[]
	 */
	public static function tables(): array {
		return array(
			self::scans_table(),
			self::issues_table(),
			self::fixes_table(),
		);
	}

	/**
	 * Creates or updates the tables on the current site.
	 *
	 * Safe to call repeatedly; dbDelta only applies differences.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public static function install(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( self::scans_sql() );
		dbDelta( self::issues_sql() );
		dbDelta( self::fixes_sql() );

		update_option( self::VERSION_OPTION, self::VERSION, true );
	}

	/**
	 * Reports whether the installed schema matches this version.
	 *
	 * @since 0.2.0
	 *
	 * @return bool
	 */
	public static function is_current(): bool {
		return self::VERSION === get_option( self::VERSION_OPTION );
	}

	/**
	 * Drops the tables on the current site.
	 *
	 * Only ever called from uninstall, and only when the site owner opted in.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public static function drop(): void {
		global $wpdb;

		foreach ( self::tables() as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dropping our own tables is the entire point of uninstall cleanup.
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
		}

		delete_option( self::VERSION_OPTION );
	}

	/**
	 * Returns the scans table definition.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	private static function scans_sql(): string {
		global $wpdb;

		$table = self::scans_table();

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scope varchar(20) NOT NULL DEFAULT 'page',
			target_id bigint(20) unsigned NOT NULL DEFAULT 0,
			parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'running',
			score tinyint(3) unsigned DEFAULT NULL,
			browser_pass varchar(20) NOT NULL DEFAULT 'skipped',
			summary longtext NULL,
			started_at datetime NOT NULL,
			finished_at datetime DEFAULT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY scope_target (scope,target_id),
			KEY parent_status (parent_id,status),
			KEY status (status),
			KEY started_at (started_at)
		) {$wpdb->get_charset_collate()};";
	}

	/**
	 * Returns the fixes table definition.
	 *
	 * The markup columns are named before_markup and after_markup because
	 * "before" is a reserved word in MySQL and would need quoting everywhere it
	 * appeared.
	 *
	 * @since 0.6.0
	 *
	 * @return string
	 */
	private static function fixes_sql(): string {
		global $wpdb;

		$table = self::fixes_table();

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			issue_id bigint(20) unsigned NOT NULL DEFAULT 0,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			rule_id varchar(100) NOT NULL DEFAULT '',
			engine varchar(30) NOT NULL DEFAULT '',
			provider varchar(50) NOT NULL DEFAULT '',
			model varchar(100) NOT NULL DEFAULT '',
			before_markup longtext NOT NULL,
			after_markup longtext NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'applied',
			applied_at datetime NOT NULL,
			applied_by bigint(20) unsigned NOT NULL DEFAULT 0,
			reverted_at datetime DEFAULT NULL,
			reverted_by bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY post_status (post_id,status),
			KEY issue_id (issue_id),
			KEY status (status)
		) {$wpdb->get_charset_collate()};";
	}

	/**
	 * Returns the issues table definition.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	private static function issues_sql(): string {
		global $wpdb;

		$table = self::issues_table();

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scan_id bigint(20) unsigned NOT NULL,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			rule_id varchar(100) NOT NULL DEFAULT '',
			wcag_sc varchar(20) NOT NULL DEFAULT '',
			severity varchar(20) NOT NULL DEFAULT 'moderate',
			detection varchar(10) NOT NULL DEFAULT 'auto',
			found_by varchar(10) NOT NULL DEFAULT 'server',
			status varchar(20) NOT NULL DEFAULT 'open',
			selector text NULL,
			context longtext NULL,
			message text NOT NULL,
			note text NULL,
			resolved_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY scan_id (scan_id),
			KEY post_status (post_id,status),
			KEY rule_id (rule_id),
			KEY severity_status (severity,status),
			KEY detection (detection),
			KEY found_by (found_by)
		) {$wpdb->get_charset_collate()};";
	}
}
