<?php
/**
 * Admin asset loading.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Core;

use WOWStudio\AccessibilityKit\Admin\Menu;
use WOWStudio\AccessibilityKit\Rest\ScanController;
use WOWStudio\AccessibilityKit\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the admin app, and only where it is used.
 *
 * @since 0.4.0
 */
final class Assets implements Registrable {

	/**
	 * Script and style handle.
	 *
	 * @since 0.4.0
	 * @var string
	 */
	private const HANDLE = 'wsak-admin';

	/**
	 * Script handle for the block editor panel.
	 *
	 * @since 0.14.0
	 * @var string
	 */
	private const EDITOR_HANDLE = 'wsak-editor';

	/**
	 * Hooks asset loading.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );

		/*
		 * enqueue_block_editor_assets rather than admin_enqueue_scripts with a
		 * screen check. It fires for the post editor, the site editor and any
		 * other screen that mounts the block editor, which is exactly the set
		 * this panel belongs on and a set that would otherwise have to be
		 * guessed at from hook suffixes.
		 */
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor' ) );
	}

	/**
	 * Enqueues the built app on the plugin screen.
	 *
	 * Loads the accessibility panel into the block editor.
	 *
	 * @since 0.14.0
	 *
	 * @return void
	 */
	public function enqueue_editor(): void {
		// The panel edits content, so it is offered to people who may edit
		// content and checked again by the route it calls. Somebody who can
		// open the editor but not run a scan simply does not see it.
		if ( ! current_user_can( Capabilities::RUN_SCAN ) ) {
			return;
		}

		$asset_path = WSAK_PATH . 'build/editor.asset.php';

		if ( ! file_exists( $asset_path ) ) {
			return;
		}

		$asset = require $asset_path;

		wp_enqueue_script(
			self::EDITOR_HANDLE,
			WSAK_URL . 'build/editor.js',
			$asset['dependencies'] ?? array(),
			$asset['version'] ?? WSAK_VERSION,
			true
		);

		wp_set_script_translations( self::EDITOR_HANDLE, 'wowstudio-accessibility-kit', WSAK_PATH . 'languages' );

		wp_enqueue_style(
			self::EDITOR_HANDLE,
			WSAK_URL . 'build/editor.css',
			array(),
			$asset['version'] ?? WSAK_VERSION
		);

		wp_style_add_data( self::EDITOR_HANDLE, 'rtl', 'replace' );
	}

	/**
	 * Loads the admin app on its own screen.
	 *
	 * Loading a 200KB admin bundle on every screen of somebody's site to serve
	 * one page of our own would be rude, so this checks the hook first.
	 *
	 * @since 0.4.0
	 *
	 * @param string $hook_suffix Current admin screen.
	 * @return void
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( 'toplevel_page_' . Menu::SLUG !== $hook_suffix ) {
			return;
		}

		$asset_path = WSAK_PATH . 'build/index.asset.php';

		if ( ! file_exists( $asset_path ) ) {
			add_action( 'admin_notices', array( $this, 'render_missing_build_notice' ) );

			return;
		}

		$asset = require $asset_path;

		wp_enqueue_script(
			self::HANDLE,
			WSAK_URL . 'build/index.js',
			$asset['dependencies'] ?? array(),
			$asset['version'] ?? WSAK_VERSION,
			true
		);

		wp_set_script_translations( self::HANDLE, 'wowstudio-accessibility-kit', WSAK_PATH . 'languages' );

		wp_enqueue_style(
			self::HANDLE,
			WSAK_URL . 'build/style-index.css',
			array( 'wp-components' ),
			$asset['version'] ?? WSAK_VERSION
		);

		// wp-scripts emits style-index-rtl.css; this tells WordPress to swap to
		// it on right-to-left locales.
		wp_style_add_data( self::HANDLE, 'rtl', 'replace' );

		wp_add_inline_script(
			self::HANDLE,
			'window.wsakSettings = ' . wp_json_encode( $this->settings() ) . ';',
			'before'
		);
	}

	/**
	 * Warns that the app has not been built.
	 *
	 * Without this the screen is simply blank, which is a miserable thing to
	 * debug.
	 *
	 * @since 0.4.0
	 *
	 * @return void
	 */
	public function render_missing_build_notice(): void {
		?>
		<div class="notice notice-error">
			<p>
				<?php esc_html_e( 'The Accessibility Kit interface has not been built. Run "npm install && npm run build" in the plugin directory.', 'wowstudio-accessibility-kit' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Returns the data the app needs at boot.
	 *
	 * Capabilities are included so the interface can hide actions the person
	 * cannot take. The REST routes enforce them regardless: this is for a
	 * coherent interface, never for security.
	 *
	 * @since 0.4.0
	 *
	 * @return array<string, mixed>
	 */
	private function settings(): array {
		return array(
			'namespace'    => ScanController::REST_NAMESPACE,
			'adminUrl'     => admin_url(),
			'version'      => WSAK_VERSION,
			'capabilities' => array(
				'runScan'     => current_user_can( Capabilities::RUN_SCAN ),
				'applyFix'    => current_user_can( Capabilities::APPLY_FIX ),
				'viewReports' => current_user_can( Capabilities::VIEW_REPORTS ),
				'manage'      => current_user_can( Capabilities::MANAGE_SETTINGS ),
			),
		);
	}
}
