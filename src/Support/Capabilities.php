<?php
/**
 * Custom capabilities.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Defines and assigns the plugin's capabilities.
 *
 * Scanning, applying fixes, and reading reports are separate capabilities so a
 * site can let an editor scan without letting them change site markup. Checking
 * manage_options everywhere would make that impossible.
 *
 * @since 0.1.0
 */
final class Capabilities {

	/**
	 * Change plugin settings, including AI provider keys.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	public const MANAGE_SETTINGS = 'wsak_manage_settings';

	/**
	 * Run an accessibility scan.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	public const RUN_SCAN = 'wsak_run_scan';

	/**
	 * Apply or revert a suggested fix.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	public const APPLY_FIX = 'wsak_apply_fix';

	/**
	 * View scan results and conformance documents.
	 *
	 * @since 0.1.0
	 * @var string
	 */
	public const VIEW_REPORTS = 'wsak_view_reports';

	/**
	 * Returns every capability this plugin defines.
	 *
	 * @since 0.1.0
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array(
			self::MANAGE_SETTINGS,
			self::RUN_SCAN,
			self::APPLY_FIX,
			self::VIEW_REPORTS,
		);
	}

	/**
	 * Returns the default role-to-capability map.
	 *
	 * Editors can scan and read results but cannot change markup or settings.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, string[]> Capabilities keyed by role slug.
	 */
	public static function role_map(): array {
		$map = array(
			'administrator' => self::all(),
			'editor'        => array(
				self::RUN_SCAN,
				self::VIEW_REPORTS,
			),
		);

		/**
		 * Filters which roles receive which plugin capabilities.
		 *
		 * Applied when capabilities are granted and when they are revoked, so a
		 * site that adds capabilities here also has them cleaned up on uninstall.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, string[]> $map Capabilities keyed by role slug.
		 */
		return (array) apply_filters( 'wsak_role_capabilities', $map );
	}

	/**
	 * Grants the plugin capabilities to their default roles.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function grant(): void {
		foreach ( self::role_map() as $role_slug => $capabilities ) {
			$role = get_role( $role_slug );

			if ( ! $role instanceof \WP_Role ) {
				continue;
			}

			foreach ( $capabilities as $capability ) {
				$role->add_cap( $capability );
			}
		}
	}

	/**
	 * Removes the plugin capabilities from every role.
	 *
	 * Called on uninstall, never on deactivation: deactivating a plugin should
	 * not silently rewrite a site's roles.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function revoke(): void {
		$roles = wp_roles();

		foreach ( array_keys( $roles->roles ) as $role_slug ) {
			$role = get_role( $role_slug );

			if ( ! $role instanceof \WP_Role ) {
				continue;
			}

			foreach ( self::all() as $capability ) {
				$role->remove_cap( $capability );
			}
		}
	}
}
