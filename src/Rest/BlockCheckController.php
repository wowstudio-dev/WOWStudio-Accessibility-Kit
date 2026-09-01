<?php
/**
 * Checking blocks while somebody is still writing them.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Rest;

use WOWStudio\AccessibilityKit\Core\Registrable;
use WOWStudio\AccessibilityKit\Remediation\WorkList;
use WOWStudio\AccessibilityKit\Scanner\Engine;
use WOWStudio\AccessibilityKit\Scanner\TemplateScan;
use WOWStudio\AccessibilityKit\Support\Capabilities;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Checks blocks as they are written, without recording anything.
 *
 * The editor already holds the content, structured, in the browser. So there is
 * nothing to fetch: no permalink, no preview nonce, no loopback request — which
 * matters most on exactly the hosts where loopback fails.
 *
 * Blocks arrive individually and findings go back keyed to the block that
 * produced them. That is the whole reason for the shape: attributing a finding
 * to a block by matching markup afterwards is a heuristic, and asking about one
 * block at a time makes it a fact.
 *
 * Nothing here is stored. This is a live assistant, not a scan — recording a row
 * for every keystroke would be nonsense, and a coverage badge that moved while
 * somebody typed would be claiming something nobody had checked. The badge only
 * changes when a real scan runs.
 *
 * @since 0.14.0
 */
final class BlockCheckController implements Registrable {

	/**
	 * The most blocks one request may carry.
	 *
	 * @since 0.14.0
	 * @var int
	 */
	private const MAX_BLOCKS = 400;

	/**
	 * The most markup one block may carry.
	 *
	 * @since 0.14.0
	 * @var int
	 */
	private const MAX_BLOCK_BYTES = 60000;

	/**
	 * Registers the route.
	 *
	 * @since 0.14.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Declares the route.
	 *
	 * @since 0.14.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/check-blocks',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'check' ),
					'permission_callback' => array( $this, 'can_scan' ),
					'args'                => array(
						'blocks' => array(
							'required' => true,
							'type'     => 'array',
							'items'    => array(
								'type'       => 'object',
								'properties' => array(
									'id'   => array( 'type' => 'string' ),
									'html' => array( 'type' => 'string' ),
								),
							),
						),
					),
				),
			)
		);
	}

	/**
	 * Checks the scan capability.
	 *
	 * @since 0.14.0
	 *
	 * @return bool|WP_Error
	 */
	public function can_scan() {
		if ( current_user_can( Capabilities::RUN_SCAN ) ) {
			return true;
		}

		return new WP_Error(
			'wsak_forbidden',
			__( 'You do not have permission to run accessibility checks.', 'wowstudio-accessibility-kit' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Checks each block and returns what it found, keyed by block.
	 *
	 * @since 0.14.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function check( WP_REST_Request $request ): WP_REST_Response {
		$blocks   = array_slice( (array) $request->get_param( 'blocks' ), 0, self::MAX_BLOCKS );
		$engine   = new Engine();
		$registry = $engine->registry();
		$work     = new WorkList( $registry->descriptors() );

		// The same profile a content-only scan uses, so the heading rules judge
		// a block against what the theme puts above it rather than against
		// nothing. Read once for the whole request.
		$profile = ( new TemplateScan() )->profile();

		$results = array();
		$total   = 0;

		foreach ( $blocks as $block ) {
			$id   = (string) ( $block['id'] ?? '' );
			$html = (string) ( $block['html'] ?? '' );

			if ( '' === $id || '' === trim( $html ) || strlen( $html ) > self::MAX_BLOCK_BYTES ) {
				continue;
			}

			$result = $engine->scan( $html, $profile );

			if ( null === $result || array() === $result->findings ) {
				continue;
			}

			$found = array();

			foreach ( $result->findings as $finding ) {
				$rule = $registry->descriptor( $finding->rule_id );

				$found[] = array(
					'rule_id'         => $finding->rule_id,
					'rule_title'      => null === $rule ? $finding->rule_id : $rule->title(),
					'consequence'     => null === $rule ? '' : $rule->consequence(),
					'band'            => $work->band_for( $finding->rule_id )->value,
					'fix'             => null === $rule ? array() : $rule->fix_plan()->to_array(),
					'severity'        => $finding->severity->value,
					'severity_label'  => $finding->severity->label(),
					'detection'       => $finding->detection->value,
					'detection_label' => $finding->detection->label(),
					'wcag_sc'         => $finding->wcag_sc,
					'message'         => $finding->message,
					'context'         => $finding->context,
				);
			}

			$total    += count( $found );
			$results[] = array(
				'id'       => $id,
				'findings' => $work->order( $found ),
			);
		}

		return new WP_REST_Response(
			array(
				'blocks' => $results,
				'total'  => $total,
				// Said with every reply, because a panel that goes quiet is
				// easily read as "this page is fine" rather than "this checked
				// what it can see from here".
				'scope'  => __( 'This checks the blocks you are editing. Colour, text size and layout need the page open in a browser, and the theme around your content is checked separately.', 'wowstudio-accessibility-kit' ),
			)
		);
	}
}
