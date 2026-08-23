<?php
/**
 * Uninstall cleanup.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit;

use WOWStudio\AccessibilityKit\Core\Activator;
use WOWStudio\AccessibilityKit\Support\Capabilities;

defined( 'ABSPATH' ) || exit;
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Removes the plugin's data, if the site owner asked for that.
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

		delete_option( Activator::SETTINGS_OPTION );
		delete_option( Activator::VERSION_OPTION );

		self::delete_transients();
		self::delete_post_meta();
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
		$settings = get_option( Activator::SETTINGS_OPTION, array() );

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
