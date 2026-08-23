<?php
/**
 * AI settings and alt-text REST routes.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Rest;

use WOWStudio\AccessibilityKit\AI\AiClientBridge;
use WOWStudio\AccessibilityKit\AI\KeyStore;
use WOWStudio\AccessibilityKit\AI\ProviderRegistry;
use WOWStudio\AccessibilityKit\AI\Settings;
use WOWStudio\AccessibilityKit\AltText\Generator;
use WOWStudio\AccessibilityKit\AltText\UsageMeter;
use WOWStudio\AccessibilityKit\Core\Registrable;
use WOWStudio\AccessibilityKit\Support\Capabilities;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes AI configuration and alt-text generation.
 *
 * No route here ever returns an API key. Reading configuration tells you
 * whether a key is stored and shows a masked hint; that is all.
 *
 * @since 0.5.0
 */
final class AiController implements Registrable {

	/**
	 * Registers the routes.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Declares the routes.
	 *
	 * @since 0.5.0
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/ai/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'can_manage' ),
					'args'                => array(
						'provider'          => array( 'type' => 'string' ),
						'model'             => array( 'type' => 'string' ),
						'auto_on_upload'    => array( 'type' => 'boolean' ),
						'send_page_context' => array( 'type' => 'boolean' ),
						'api_key'           => array(
							'type'        => 'string',
							'description' => __( 'A key to store for the named provider. Never returned by any route.', 'wowstudio-accessibility-kit' ),
						),
						'key_provider'      => array( 'type' => 'string' ),
					),
				),
			)
		);

		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/alt-text',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'generate' ),
					'permission_callback' => array( $this, 'can_apply' ),
					'args'                => array(
						'attachment_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'post_id'       => array(
							'type'              => 'integer',
							'default'           => 0,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/alt-text/apply',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'apply' ),
					'permission_callback' => array( $this, 'can_apply' ),
					'args'                => array(
						'attachment_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'sanitize_callback' => 'absint',
						),
						'text'          => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Checks the settings capability.
	 *
	 * @since 0.5.0
	 *
	 * @return bool|WP_Error
	 */
	public function can_manage() {
		if ( current_user_can( Capabilities::MANAGE_SETTINGS ) ) {
			return true;
		}

		return new WP_Error(
			'wsak_forbidden',
			__( 'You do not have permission to change accessibility settings.', 'wowstudio-accessibility-kit' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Checks the fix capability.
	 *
	 * @since 0.5.0
	 *
	 * @return bool|WP_Error
	 */
	public function can_apply() {
		if ( current_user_can( Capabilities::APPLY_FIX ) ) {
			return true;
		}

		return new WP_Error(
			'wsak_forbidden',
			__( 'You do not have permission to apply accessibility fixes.', 'wowstudio-accessibility-kit' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	/**
	 * Returns the AI configuration.
	 *
	 * @since 0.5.0
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings(): WP_REST_Response {
		$registry = ProviderRegistry::with_defaults();
		$keys     = new KeyStore();
		$settings = new Settings();
		$usage    = new UsageMeter();
		$bridge   = new AiClientBridge();
		$current  = $settings->all();

		return new WP_REST_Response(
			array(
				'settings'     => $current,
				'providers'    => $registry->describe( $keys ),
				'encryption'   => array(
					'available' => KeyStore::is_available(),
					'note'      => KeyStore::is_available()
						? __( 'Keys are encrypted before they are stored.', 'wowstudio-accessibility-kit' )
						: __( 'This server has no libsodium support, so keys cannot be stored securely. Storing a key is disabled rather than saving it unencrypted.', 'wowstudio-accessibility-kit' ),
				),
				'wp_ai_client' => array(
					'available'  => $bridge->is_available(),
					'configured' => $bridge->is_configured_for( (string) $current['provider'] ),
				),
				'usage'        => array(
					'cap'       => $usage->cap(),
					'used'      => $usage->used_today(),
					'remaining' => $usage->is_unlimited() ? null : $usage->remaining(),
					'unlimited' => $usage->is_unlimited(),
				),
			)
		);
	}

	/**
	 * Updates the AI configuration.
	 *
	 * @since 0.5.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_settings( WP_REST_Request $request ) {
		$settings = new Settings();
		$changes  = array();

		foreach ( array( 'provider', 'model', 'auto_on_upload', 'send_page_context' ) as $field ) {
			if ( null !== $request->get_param( $field ) ) {
				$changes[ $field ] = $request->get_param( $field );
			}
		}

		if ( array() !== $changes ) {
			$settings->update( $changes );
		}

		$api_key = (string) $request->get_param( 'api_key' );

		if ( '' !== $api_key ) {
			if ( ! KeyStore::is_available() ) {
				return new WP_Error(
					'wsak_no_encryption',
					__( 'This server cannot encrypt secrets, so the key was not stored. Ask your host to enable the sodium extension.', 'wowstudio-accessibility-kit' ),
					array( 'status' => 501 )
				);
			}

			$for = (string) $request->get_param( 'key_provider' );
			$for = '' !== $for ? $for : (string) $settings->all()['provider'];

			if ( null === ProviderRegistry::with_defaults()->get( $for ) ) {
				return new WP_Error(
					'wsak_unknown_provider',
					__( 'That AI provider is not one this plugin knows about.', 'wowstudio-accessibility-kit' ),
					array( 'status' => 400 )
				);
			}

			if ( ! ( new KeyStore() )->set( $for, $api_key ) ) {
				return new WP_Error(
					'wsak_key_not_stored',
					__( 'The key could not be stored.', 'wowstudio-accessibility-kit' ),
					array( 'status' => 500 )
				);
			}
		}

		return $this->get_settings();
	}

	/**
	 * Generates a suggestion for one image.
	 *
	 * @since 0.5.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function generate( WP_REST_Request $request ) {
		$generator  = new Generator();
		$suggestion = $generator->for_attachment(
			absint( $request->get_param( 'attachment_id' ) ),
			absint( $request->get_param( 'post_id' ) )
		);

		if ( is_wp_error( $suggestion ) ) {
			return $suggestion;
		}

		$usage = $generator->usage();

		return new WP_REST_Response(
			array(
				'suggestion' => $suggestion->to_array(),
				'usage'      => array(
					'cap'       => $usage->cap(),
					'used'      => $usage->used_today(),
					'remaining' => $usage->is_unlimited() ? null : $usage->remaining(),
					'unlimited' => $usage->is_unlimited(),
				),
			)
		);
	}

	/**
	 * Saves reviewed alt text onto an attachment.
	 *
	 * Separate from generation on purpose. A suggestion is never written to the
	 * media library until a person has read it and asked for it to be saved.
	 *
	 * @since 0.5.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function apply( WP_REST_Request $request ) {
		$attachment_id = absint( $request->get_param( 'attachment_id' ) );

		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			return new WP_Error(
				'wsak_ai_not_attachment',
				__( 'That is not an image in the media library.', 'wowstudio-accessibility-kit' ),
				array( 'status' => 404 )
			);
		}

		if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
			return new WP_Error(
				'wsak_forbidden_attachment',
				__( 'You do not have permission to edit that image.', 'wowstudio-accessibility-kit' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		$text     = (string) $request->get_param( 'text' );
		$previous = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $text );

		/**
		 * Fires after alt text is saved onto an attachment.
		 *
		 * Carries the previous value so an undo can be offered.
		 *
		 * @since 0.5.0
		 *
		 * @param int    $attachment_id Attachment updated.
		 * @param string $text          New alt text.
		 * @param string $previous      Alt text that was replaced.
		 */
		do_action( 'wsak_alt_text_applied', $attachment_id, $text, $previous );

		return new WP_REST_Response(
			array(
				'attachment_id' => $attachment_id,
				'text'          => $text,
				'previous'      => $previous,
			)
		);
	}
}
