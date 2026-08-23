<?php
/**
 * Activation routine.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Core;

use WOWStudio\AccessibilityKit\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Prepares a site the first time the plugin is activated.
 *
 * @since 0.1.0
 */
final class Activator {

	/**
	 * Option holding the plugin settings.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	public const SETTINGS_OPTION = 'wsak_settings';

	/**
	 * Option holding the installed plugin version.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	public const VERSION_OPTION = 'wsak_version';

	/**
	 * Runs activation for one site or, on a network activation, for every site.
	 *
	 * @since 0.1.0
	 *
	 * @param bool $network_wide Whether the plugin was activated network-wide.
	 * @return void
	 */
	public static function activate( bool $network_wide = false ): void {
		if ( $network_wide && is_multisite() ) {
			self::activate_network();

			return;
		}

		self::activate_site();
	}

	/**
	 * Runs activation across a network.
	 *
	 * Very large networks are skipped deliberately: looping thousands of sites
	 * inside an activation request would time out. Those sites are set up on
	 * first admin load instead.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private static function activate_network(): void {
		if ( wp_is_large_network() ) {
			return;
		}

		$site_ids = get_sites(
			array(
				'fields'   => 'ids',
				'number'   => 0,
				'spam'     => 0,
				'deleted'  => 0,
				'archived' => 0,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			self::activate_site();
			restore_current_blog();
		}
	}

	/**
	 * Runs activation for the current site.
	 *
	 * Written to be safe to run more than once.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function activate_site(): void {
		Capabilities::grant();

		$settings = get_option( self::SETTINGS_OPTION, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		// Data is kept on uninstall unless the site owner opts in. Losing a scan
		// history to an accidental uninstall is not a recoverable mistake.
		if ( ! array_key_exists( 'delete_data_on_uninstall', $settings ) ) {
			$settings['delete_data_on_uninstall'] = false;
		}

		update_option( self::SETTINGS_OPTION, $settings, false );
		update_option( self::VERSION_OPTION, WSAK_VERSION, true );

		/**
		 * Fires after the plugin finishes activating on a site.
		 *
		 * @since 0.1.0
		 */
		do_action( 'wsak_activated' );
	}
}
