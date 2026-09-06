<?php
/**
 * Site-wide fix REST routes.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Rest;

use WOWStudio\AccessibilityKit\Core\Registrable;
use WOWStudio\AccessibilityKit\SiteFixes\SiteFixManager;
use WOWStudio\AccessibilityKit\Support\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Listing the site-wide fixes and switching them on or off.
 *
 * @since 0.19.0
 */
final class SiteFixController implements Registrable {

	/**
	 * The manager.
	 *
	 * @since 0.19.0
	 * @var SiteFixManager
	 */
	private SiteFixManager $fixes;

	/**
	 * Constructor.
	 *
	 * @since 0.19.0
	 *
	 * @param SiteFixManager|null $fixes Manager to use.
	 */
	public function __construct( ?SiteFixManager $fixes = null ) {
		$this->fixes = $fixes ?? new SiteFixManager();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.19.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Declares the routes.
	 *
	 * @since 0.19.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/site-fixes',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'can_view' ),
				),
			)
		);

		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/site-fixes/(?P<id>[a-z0-9-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'toggle' ),
					'permission_callback' => array( $this, 'can_change' ),
					'args'                => array(
						'id'      => array(
							'required' => true,
							'type'     => 'string',
						),
						'enabled' => array(
							'required' => true,
							'type'     => 'boolean',
						),
					),
				),
			)
		);
	}

	/**
	 * Anybody who may read reports may see which fixes are on.
	 *
	 * @since 0.19.0
	 *
	 * @return bool|WP_Error
	 */
	public function can_view() {
		if ( current_user_can( Capabilities::VIEW_REPORTS ) ) {
			return true;
		}

		return new WP_Error(
			'wsak_forbidden',
			__( 'You do not have permission to view accessibility settings.', 'wowstudio-accessibility-kit' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Changing one needs the settings capability, not the fix capability.
	 *
	 * These are site-wide and affect every visitor's experience of every page,
	 * which is a different decision from correcting one finding on one post.
	 *
	 * @since 0.19.0
	 *
	 * @return bool|WP_Error
	 */
	public function can_change() {
		if ( current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			return true;
		}

		return new WP_Error(
			'wsak_forbidden',
			__( 'Site-wide fixes change every page for every visitor, so changing one needs the accessibility settings capability.', 'wowstudio-accessibility-kit' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Returns every fix and whether it is switched on.
	 *
	 * @since 0.19.0
	 *
	 * @return WP_REST_Response
	 */
	public function index(): WP_REST_Response {
		return new WP_REST_Response( array( 'fixes' => $this->fixes->to_array() ) );
	}

	/**
	 * Switches one fix on or off.
	 *
	 * @since 0.19.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function toggle( WP_REST_Request $request ) {
		$id = (string) $request->get_param( 'id' );

		if ( null === $this->fixes->registry()->get( $id ) ) {
			return new WP_Error(
				'wsak_unknown_fix',
				__( 'There is no fix by that name.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 404 )
			);
		}

		$this->fixes->set( $id, (bool) $request->get_param( 'enabled' ) );

		return new WP_REST_Response( array( 'fixes' => $this->fixes->to_array() ) );
	}
}
