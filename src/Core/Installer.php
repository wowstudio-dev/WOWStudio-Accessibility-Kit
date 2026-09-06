<?php
/**
 * Per-site installation.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Core;

use WOWStudio\AccessibilityKit\Db\Schema;
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
}
