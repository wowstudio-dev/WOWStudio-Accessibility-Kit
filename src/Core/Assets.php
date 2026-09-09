<?php
/**
 * Admin asset loading.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Core;

use WOWStudio\AccessibilityKit\Admin\Menu;
use WOWStudio\AccessibilityKit\Admin\Onboarding;
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
	 * The admin screen the app is being mounted on.
	 *
	 * Set when the assets are enqueued and read when the boot data is built,
	 * which happens later in the same request.
	 *
	 * @since 0.29.0
	 * @var string
	 */
	private string $hook = '';

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
		if ( '' === $this->view_for( $hook_suffix ) ) {
			return;
		}

		$this->hook = $hook_suffix;

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
			// Seeds the header, which says when anything was last checked on
			// every screen rather than only on the report. Nothing here scans
			// on its own, so "never" is a normal answer and the one that most
			// needs saying.
			'lastScan'     => $this->last_scan(),
			// Which of the four admin screens this is. The app is one bundle
			// mounted on all of them, so without this it has no way of knowing
			// which one somebody asked for.
			'screen'       => $this->view_for( $this->hook ),
			'screens'      => $this->screen_urls(),
			// Whether anybody has been through the setup here. The dashboard's
			// empty state offers it when they have not; the setup itself uses
			// it to know whether this is a first run or a revisit.
			'onboarded'    => Onboarding::is_done(),
			// Whether this request asked for the setup. It is a view on the
			// dashboard rather than a screen of its own — see the note on
			// Onboarding::QUERY_ARG for why WordPress leaves no better option.
			'welcome'      => Onboarding::is_requested(),
			'capabilities' => array(
				'runScan'     => current_user_can( Capabilities::RUN_SCAN ),
				'applyFix'    => current_user_can( Capabilities::APPLY_FIX ),
				'viewReports' => current_user_can( Capabilities::VIEW_REPORTS ),
				'manage'      => current_user_can( Capabilities::MANAGE_SETTINGS ),
			),
		);
	}

	/**
	 * Returns the view a WordPress admin hook corresponds to.
	 *
	 * @since 0.29.0
	 *
	 * @param string $hook_suffix Current admin screen.
	 * @return string Empty when the screen is not one of ours.
	 */
	private function view_for( string $hook_suffix ): string {
		foreach ( Menu::views() as $slug => $view ) {
			$hook = Menu::SLUG === $slug
				? 'toplevel_page_' . $slug
				: get_plugin_page_hookname( $slug, Menu::SLUG );

			if ( $hook === $hook_suffix ) {
				return $view;
			}
		}

		return '';
	}

	/**
	 * Returns where each screen lives, so the app can link between them.
	 *
	 * Built here rather than in the browser: the admin URL and the slugs are
	 * the server's to know, and a link assembled from guesses is a link that
	 * breaks the first time somebody moves the admin.
	 *
	 * @since 0.29.0
	 *
	 * @return array<string, string> URLs keyed by view.
	 */
	private function screen_urls(): array {
		$urls = array();

		foreach ( Menu::views() as $slug => $view ) {
			$urls[ $view ] = admin_url( 'admin.php?page=' . $slug );
		}

		// Not one of the menu's screens, but somewhere the app links to.
		$urls['welcome'] = Onboarding::url();

		return $urls;
	}

	/**
	 * Returns when a page was last checked, in the site's own format.
	 *
	 * @since 0.29.0
	 *
	 * @return string Empty when nothing has been scanned.
	 */
	private function last_scan(): string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own table.
		$finished = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT finished_at FROM {$wpdb->prefix}wsak_scans
				 WHERE status = %s AND finished_at IS NOT NULL
				 ORDER BY finished_at DESC
				 LIMIT 1",
				'complete'
			)
		);

		if ( null === $finished ) {
			return '';
		}

		$stamp = strtotime( (string) $finished . ' UTC' );

		return (string) wp_date(
			get_option( 'date_format' ) . ', ' . get_option( 'time_format' ),
			false === $stamp ? null : $stamp
		);
	}
}
