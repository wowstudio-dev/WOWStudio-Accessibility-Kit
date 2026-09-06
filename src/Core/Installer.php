<?php
/**
 * Per-site installation.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Core;

use WOWStudio\AccessibilityKit\Db\DecisionRepository;
use WOWStudio\AccessibilityKit\Db\IssueRepository;
use WOWStudio\AccessibilityKit\Db\Schema;
use WOWStudio\AccessibilityKit\Scanner\Fingerprint;
use WOWStudio\AccessibilityKit\Scanner\IssueStatus;
use WOWStudio\AccessibilityKit\SiteFixes\Fixes\PageTitle;
use WOWStudio\AccessibilityKit\SiteFixes\SiteFixManager;
use WOWStudio\AccessibilityKit\Support\Capabilities;
use WP_Site;

defined( 'ABSPATH' ) || exit;

/**
 * Brings a single site up to date: capabilities, tables, and settings.
 *
 * Activation is not the only moment this has to happen. A site added to a
 * network after the plugin was activated never runs the activation hook, and an
 * update that changes the schema has to apply itself without one either. Both
 * are handled here so the two paths cannot drift apart.
 *
 * @since 0.2.0
 */
final class Installer implements Registrable {

	/**
	 * Option holding the plugin settings.
	 *
	 * @since 0.2.0
	 * @var string
	 */
	public const SETTINGS_OPTION = 'wsak_settings';

	/**
	 * Option holding the installed plugin version.
	 *
	 * @since 0.2.0
	 * @var string
	 */
	public const VERSION_OPTION = 'wsak_version';

	/**
	 * Option marking that existing dismissals have been made durable.
	 *
	 * @since 0.29.0
	 * @var string
	 */
	public const DECISIONS_MIGRATED_OPTION = 'wsak_decisions_migrated';

	/**
	 * Hooks the paths that need installation outside activation.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_initialize_site', array( $this, 'on_new_site' ), 20 );
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
	}

	/**
	 * Installs onto a site newly added to a network.
	 *
	 * @since 0.2.0
	 *
	 * @param WP_Site $site The new site.
	 * @return void
	 */
	public function on_new_site( WP_Site $site ): void {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active_for_network( WSAK_BASENAME ) ) {
			return;
		}

		switch_to_blog( (int) $site->blog_id );
		self::install_site();
		restore_current_blog();
	}

	/**
	 * Applies a pending schema change.
	 *
	 * Runs on admin_init rather than on every request: an upgrade check on the
	 * front end would put an option read in front of every page view for no
	 * benefit, since nothing writes to these tables from the front end.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function maybe_upgrade(): void {
		if ( Schema::is_current() ) {
			return;
		}

		self::install_site();
	}

	/**
	 * Installs or updates everything this plugin owns on the current site.
	 *
	 * Safe to run repeatedly.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public static function install_site(): void {
		Capabilities::grant();
		Schema::install();

		$settings = get_option( self::SETTINGS_OPTION, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		// Data is kept on uninstall unless the site owner opts in. Losing a scan
		// history to an accidental uninstall is not a recoverable mistake.
		if ( ! array_key_exists( 'delete_data_on_uninstall', $settings ) ) {
			$settings['delete_data_on_uninstall'] = false;
		}

		self::migrate_title_tag_fix();
		self::migrate_decisions();

		update_option( self::SETTINGS_OPTION, $settings, false );
		update_option( self::VERSION_OPTION, WSAK_VERSION, true );

		/**
		 * Fires after the plugin finishes installing or updating on a site.
		 *
		 * @since 0.2.0
		 */
		do_action( 'wsak_installed' );
	}

	/**
	 * Carries the standalone title fix into the site-fixes set.
	 *
	 * Before 0.19.0 the document-title fix was its own class with its own
	 * option. It is now one of the site-wide fixes, and a site that switched it
	 * on must not silently lose it on upgrade — a page that had a title
	 * yesterday and does not today is a regression this plugin caused.
	 *
	 * The old option is deleted once carried across, so this cannot run twice
	 * and cannot resurrect a fix somebody has since switched off.
	 *
	 * @since 0.19.0
	 *
	 * @return void
	 */
	private static function migrate_title_tag_fix(): void {
		if ( ! get_option( PageTitle::LEGACY_OPTION, false ) ) {
			return;
		}

		$enabled = get_option( SiteFixManager::OPTION, array() );
		$enabled = is_array( $enabled ) ? $enabled : array();

		if ( ! in_array( 'page-title', $enabled, true ) ) {
			$enabled[] = 'page-title';
			sort( $enabled );
			update_option( SiteFixManager::OPTION, $enabled, true );
		}

		delete_option( PageTitle::LEGACY_OPTION );
	}

	/**
	 * Rescues existing dismissals, then clears out superseded findings.
	 *
	 * Before 0.29.0 a dismissal lived only on the issue row the scan happened to
	 * be holding, so it was lost the next time the page was scanned, and every
	 * scan's findings were kept forever — which is why the site-wide counts
	 * could report several times more open findings than a site actually had.
	 *
	 * The order here is the whole point and must not be reversed. Judgements
	 * are copied into the decisions table *first*, because the pruning
	 * immediately afterwards deletes the rows they were written on. Doing it
	 * the other way round would silently destroy the reasoning somebody
	 * recorded, which is the one thing in this table that cannot be
	 * regenerated by scanning again.
	 *
	 * Only dismissals are carried across. A row marked fixed is not a judgement
	 * to preserve — if the fault is still there, the next scan should say so.
	 *
	 * @since 0.29.0
	 *
	 * @return void
	 */
	private static function migrate_decisions(): void {
		global $wpdb;

		if ( (bool) get_option( self::DECISIONS_MIGRATED_OPTION, false ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time upgrade over the plugin's own table.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT post_id, rule_id, context, note, resolved_by FROM %i WHERE status = %s',
				Schema::issues_table(),
				IssueStatus::Ignored->value
			)
		);

		$decisions = new DecisionRepository();

		foreach ( (array) $rows as $row ) {
			$rule_id = (string) $row->rule_id;

			$decisions->record(
				Fingerprint::of( $rule_id, (string) $row->context ),
				(int) $row->post_id,
				$rule_id,
				IssueStatus::Ignored,
				(string) $row->note,
				(int) $row->resolved_by
			);
		}

		( new IssueRepository() )->prune_all_superseded();

		update_option( self::DECISIONS_MIGRATED_OPTION, true, true );
	}
}
