<?php
/**
 * Plugin orchestrator.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Core;

use WOWStudio\AccessibilityKit\Admin\Menu;
use WOWStudio\AccessibilityKit\Conformance\StatementBlock;
use WOWStudio\AccessibilityKit\Remediation\OverrideStore;
use WOWStudio\AccessibilityKit\Scanner\Preview;
use WOWStudio\AccessibilityKit\Jobs\Worker;
use WOWStudio\AccessibilityKit\Rest\CssFixController;
use WOWStudio\AccessibilityKit\SiteFixes\SiteFixManager;
use WOWStudio\AccessibilityKit\Rest\AltTextController;
use WOWStudio\AccessibilityKit\Rest\SiteFixController;
use WOWStudio\AccessibilityKit\Rest\BlockCheckController;
use WOWStudio\AccessibilityKit\Rest\OverviewController;
use WOWStudio\AccessibilityKit\Rest\ReviewController;
use WOWStudio\AccessibilityKit\Rest\RunController;
use WOWStudio\AccessibilityKit\Rest\StatementController;
use WOWStudio\AccessibilityKit\Rest\ScanController;

defined( 'ABSPATH' ) || exit;

/**
 * Boots the plugin's services.
 *
 * Deliberately thin: it owns the service list and nothing else. Behaviour lives
 * in the services themselves.
 *
 * @since 0.1.0
 */
final class Plugin {

	/**
	 * Shared instance.
	 *
	 * @since 0.1.0
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Whether boot() has already run.
	 *
	 * @since 0.1.0
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Registered services, keyed by identifier.
	 *
	 * @since 0.1.0
	 * @var array<string, Registrable>
	 */
	private array $services = array();

	/**
	 * Use instance() instead.
	 *
	 * @since 0.1.0
	 */
	private function __construct() {}

	/**
	 * Returns the shared instance.
	 *
	 * @since 0.1.0
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Registers every service exactly once.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		foreach ( $this->default_services() as $id => $service ) {
			if ( ! $service instanceof Registrable ) {
				continue;
			}

			$this->services[ $id ] = $service;
			$service->register();
		}

		/**
		 * Fires once every plugin service has registered its hooks.
		 *
		 * @since 0.1.0
		 *
		 * @param Plugin $plugin The plugin instance.
		 */
		do_action( 'wsak_booted', $this );
	}

	/**
	 * Returns a registered service, or null when it is not present.
	 *
	 * @since 0.1.0
	 *
	 * @param string $id Service identifier.
	 * @return Registrable|null
	 */
	public function service( string $id ): ?Registrable {
		return $this->services[ $id ] ?? null;
	}

	/**
	 * Builds the service list.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, Registrable>
	 */
	private function default_services(): array {
		/*
		 * Translations are deliberately absent from this list: the
		 * "Domain Path: /languages" header lets WordPress load the bundled
		 * catalogue just in time, and calling load_plugin_textdomain() as well
		 * is what Plugin Check flags as discouraged since WordPress 4.6.
		 * Scanner and REST services are added in later steps.
		 */
		$services = array(
			'installer'      => new Installer(),
			'assets'         => new Assets(),
			'admin.menu'     => new Menu(),
			'rest.scan'      => new ScanController(),
			'rest.fix.css'   => new CssFixController(),
			'rest.media'     => new AltTextController(),
			'rest.sitefixes' => new SiteFixController(),
			'rest.runs'      => new RunController(),
			'rest.review'    => new ReviewController(),
			'rest.blocks'    => new BlockCheckController(),
			'rest.overview'  => new OverviewController(),
			'jobs.worker'    => new Worker(),
			'fixes.site'     => new SiteFixManager(),
			'overrides'      => new OverrideStore(),
			'preview'        => new Preview(),
			'statement'      => new StatementBlock(),
			'rest.statement' => new StatementController(),
		);

		/**
		 * Filters the services the plugin boots.
		 *
		 * @since 0.1.0
		 *
		 * @param array<string, Registrable> $services Services keyed by identifier.
		 */
		return (array) apply_filters( 'wsak_services', $services );
	}
}
