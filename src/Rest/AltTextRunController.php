<?php
/**
 * Bulk alt-text REST routes.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Rest;

use WOWStudio\AccessibilityKit\AI\ProviderRegistry;
use WOWStudio\AccessibilityKit\AI\Settings;
use WOWStudio\AccessibilityKit\AltText\AltTextRun;
use WOWStudio\AccessibilityKit\AltText\MediaIndex;
use WOWStudio\AccessibilityKit\AltText\UsageMeter;
use WOWStudio\AccessibilityKit\Core\Registrable;
use WOWStudio\AccessibilityKit\Support\Capabilities;
use WOWStudio\AccessibilityKit\Support\Plan;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Listing undescribed images, describing them in bulk, and approving the result.
 *
 * @since 0.13.0
 */
final class AltTextRunController implements Registrable {

	/**
	 * Registers the routes.
	 *
	 * @since 0.13.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Declares the routes.
	 *
	 * @since 0.13.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/media',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'media' ),
					'permission_callback' => array( $this, 'can_generate' ),
					'args'                => array(
						'page' => array(
							'type'              => 'integer',
							'default'           => 1,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/alt-text/runs',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'start' ),
					'permission_callback' => array( $this, 'can_generate' ),
					'args'                => array(
						'attachment_ids' => array(
							'required' => true,
							'type'     => 'array',
							'items'    => array( 'type' => 'integer' ),
						),
					),
				),
			)
		);

		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/alt-text/runs/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'show' ),
					'permission_callback' => array( $this, 'can_generate' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'cancel' ),
					'permission_callback' => array( $this, 'can_generate' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/alt-text/(?P<id>\d+)/approve',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'approve' ),
					'permission_callback' => array( $this, 'can_generate' ),
					'args'                => array(
						'id'   => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'text' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/alt-text/(?P<id>\d+)/reject',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reject' ),
					'permission_callback' => array( $this, 'can_generate' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);
	}

	/**
	 * Checks the fix capability.
	 *
	 * @since 0.13.0
	 *
	 * @return bool|WP_Error
	 */
	public function can_generate() {
		if ( current_user_can( Capabilities::APPLY_FIX ) && current_user_can( 'upload_files' ) ) {
			return true;
		}

		return new WP_Error(
			'wsak_forbidden',
			__( 'You do not have permission to change the media library.', 'wowstudio-accessibility-kit' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Lists images that have never been described, with what a run would cost.
	 *
	 * @since 0.13.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function media( WP_REST_Request $request ): WP_REST_Response {
		$listing = ( new MediaIndex() )->undescribed( absint( $request->get_param( 'page' ) ) );

		return new WP_REST_Response( array_merge( $listing, array( 'billing' => $this->billing() ) ) );
	}

	/**
	 * Describes what a run will cost, without inventing a number.
	 *
	 * The obvious thing to put here is a price. We will not: the rate depends on
	 * the provider, the model, the image size and a pricing page that changes
	 * without telling us, and a confident figure that turns out to be wrong
	 * about somebody's money is exactly the kind of authority this product does
	 * not want.
	 *
	 * What can be said exactly is said exactly — one request per image, to this
	 * provider, on this model, billed by them — and the reader is pointed at the
	 * only page that actually knows.
	 *
	 * @since 0.13.0
	 *
	 * @return array<string, mixed>
	 */
	private function billing(): array {
		$settings = ( new Settings() )->all();
		$provider = ( ProviderRegistry::with_defaults() )->get( (string) $settings['provider'] );
		$usage    = new UsageMeter();

		return array(
			'provider'    => null === $provider ? '' : $provider->label(),
			'model'       => (string) $settings['model'],
			'pricing_url' => null === $provider ? '' : $provider->key_url(),
			'unlimited'   => $usage->is_unlimited(),
			'remaining'   => $usage->remaining(),
			'cap'         => $usage->cap(),
			'per_image'   => __( 'Each image is one request to your own provider account, and they bill you for it directly. We never see what it costs, so we will not guess at a figure — check their pricing if you want one before you start.', 'wowstudio-accessibility-kit' ),
		);
	}

	/**
	 * Starts a run.
	 *
	 * @since 0.13.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function start( WP_REST_Request $request ) {
		if ( ! ( new Plan() )->is_pro() ) {
			return ( new Plan() )->refuse( __( 'Describing many images at once', 'wowstudio-accessibility-kit' ) );
		}

		$run = ( new AltTextRun() )->start( (array) $request->get_param( 'attachment_ids' ) );

		if ( is_wp_error( $run ) ) {
			return $run;
		}

		return new WP_REST_Response( $this->describe_run( $run ), 201 );
	}

	/**
	 * Reads a run.
	 *
	 * @since 0.13.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function show( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( $this->describe_run( absint( $request->get_param( 'id' ) ) ) );
	}

	/**
	 * Stops a run.
	 *
	 * @since 0.13.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function cancel( WP_REST_Request $request ): WP_REST_Response {
		$run_id = absint( $request->get_param( 'id' ) );
		( new AltTextRun() )->cancel( $run_id );

		return new WP_REST_Response( $this->describe_run( $run_id ) );
	}

	/**
	 * Writes a reviewed description onto the image.
	 *
	 * @since 0.13.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function approve( WP_REST_Request $request ) {
		$result = ( new AltTextRun() )->approve(
			absint( $request->get_param( 'id' ) ),
			(string) $request->get_param( 'text' )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result );
	}

	/**
	 * Discards a suggestion.
	 *
	 * @since 0.13.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function reject( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			( new AltTextRun() )->reject( absint( $request->get_param( 'id' ) ) )
		);
	}

	/**
	 * Assembles a run's progress and the suggestions waiting in it.
	 *
	 * @since 0.13.0
	 *
	 * @param int $run_id Run to describe.
	 * @return array<string, mixed>
	 */
	private function describe_run( int $run_id ): array {
		$run   = new AltTextRun();
		$index = new MediaIndex();
		$items = array();

		foreach ( $run->ids_in( $run_id ) as $id ) {
			$post = get_post( $id );

			if ( null !== $post ) {
				$items[] = $index->describe( $post );
			}
		}

		return array(
			'run_id'   => $run_id,
			'progress' => $run->progress( $run_id ),
			'items'    => $items,
			'billing'  => $this->billing(),
		);
	}
}
