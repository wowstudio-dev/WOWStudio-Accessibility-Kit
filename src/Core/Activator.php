<?php
/**
 * Activation routine.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Prepares sites when the plugin is activated.
 *
 * The per-site work lives in Installer, which is also reached when a site joins
 * a network or a schema change lands without an activation.
 *
 * @since 0.1.0
 */
final class Activator {

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

		Installer::install_site();

		/**
		 * Fires after the plugin finishes activating on a site.
		 *
		 * @since 0.1.0
		 */
		do_action( 'wsak_activated' );
	}

	/**
	 * Runs activation across a network.
	 *
	 * Very large networks are skipped deliberately: looping thousands of sites
	 * inside an activation request would time out. Those sites install on their
	 * first admin load instead, through Installer::maybe_upgrade().
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
			Installer::install_site();
			restore_current_blog();
		}

		/**
		 * Fires after the plugin finishes activating on a site.
		 *
		 * @since 0.1.0
		 */
		do_action( 'wsak_activated' );
	}
}
