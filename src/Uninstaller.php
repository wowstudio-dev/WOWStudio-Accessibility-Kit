<?php
/**
 * Uninstall cleanup.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit;

use WOWStudio\AccessibilityKit\Core\Installer;
use WOWStudio\AccessibilityKit\Db\Schema;
use WOWStudio\AccessibilityKit\Remediation\CustomCss;
use WOWStudio\AccessibilityKit\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Removes the plugin's data, if the site owner asked for that.
 *
 * Reached through an uninstall hook rather than an uninstall.php file. The two
 * are not interchangeable: WordPress runs uninstall.php *instead of* the
 * uninstall hooks (wp-admin/includes/plugin.php), so adding that file back
 * would silently stop this class ever being reached.
 *
 * That is also why there is no WP_UNINSTALL_PLUGIN guard here. Core defines
 * that constant only in the uninstall.php branch; on the hook path it is
 * absent, so the guard would have made this class exit immediately and quietly
 * delete nothing at all.
 *
 * @since 0.1.0
 */
final class Uninstaller {

	/**
	 * Prefix shared by every option and transient the plugin creates.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	private const PREFIX = 'wsak_';

	/**
	 * Runs cleanup for one site or, on multisite, for every site.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function run(): void {
		$autoloader = __DIR__ . '/../vendor/autoload.php';

		if ( file_exists( $autoloader ) ) {
			require_once $autoloader;
		}

		if ( ! is_multisite() ) {
			self::clean_site();

			return;
		}

		if ( wp_is_large_network() ) {
			return;
		}

		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			self::clean_site();
			restore_current_blog();
		}
	}

	/**
	 * Removes the plugin's data from the current site.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private static function clean_site(): void {
		if ( ! self::owner_opted_in() ) {
			return;
		}

		if ( class_exists( Capabilities::class ) ) {
			Capabilities::revoke();
		}

		if ( class_exists( Schema::class ) ) {
			Schema::drop();
		}

		delete_option( Installer::SETTINGS_OPTION );
		delete_option( Installer::VERSION_OPTION );
		delete_option( Installer::DECISIONS_MIGRATED_OPTION );

		self::delete_transients();
		self::delete_post_meta();
		self::remove_managed_css();
	}

	/**
	 * Takes our rules out of the site's Additional CSS, and nothing else.
	 *
	 * Only reached when the owner opted in to data removal. Even then this
	 * removes the block we wrote and leaves every other line exactly as it was:
	 * Additional CSS is the site's own stylesheet, which happens to contain some
	 * of our work, not a place we own. Uninstalling a plugin should not be able
	 * to take somebody's brand colours with it.
	 *
	 * Without the opt-in the CSS stays entirely. Applied fixes are real changes
	 * the owner approved, and removing the plugin is not a reason to silently
	 * undo them and regress the site.
	 *
	 * @since 0.11.0
	 *
	 * @return void
	 */
	private static function remove_managed_css(): void {
		if ( ! class_exists( CustomCss::class ) || ! function_exists( 'wp_get_custom_css' ) ) {
			return;
		}

		$css = new CustomCss();

		foreach ( array_keys( $css->rules() ) as $issue_id ) {
			$css->remove( (int) $issue_id );
		}
	}

	/**
	 * Reports whether the site owner opted in to data deletion.
	 *
	 * Defaults to false. An unreadable or malformed setting also means false:
	 * when in doubt, keep the user's data.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	private static function owner_opted_in(): bool {
		$settings = get_option( Installer::SETTINGS_OPTION, array() );

		if ( ! is_array( $settings ) ) {
			return false;
		}

		return ! empty( $settings['delete_data_on_uninstall'] );
	}

	/**
	 * Deletes the plugin's transients.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private static function delete_transients(): void {
		global $wpdb;

		$like = $wpdb->esc_like( '_transient_' . self::PREFIX ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off uninstall cleanup; no API covers wildcard option deletion.
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );

		foreach ( (array) $names as $name ) {
			delete_transient( substr( (string) $name, strlen( '_transient_' ) ) );
		}
	}

	/**
	 * Deletes the plugin's per-post scan cache.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private static function delete_post_meta(): void {
		global $wpdb;

		$like = $wpdb->esc_like( '_' . self::PREFIX ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off uninstall cleanup; delete_post_meta_by_key() cannot match a prefix.
		$keys = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $like ) );

		foreach ( (array) $keys as $key ) {
			delete_post_meta_by_key( (string) $key );
		}
	}
}
