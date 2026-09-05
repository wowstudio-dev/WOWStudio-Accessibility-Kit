<?php
/**
 * Alt-text REST routes.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Rest;

use WOWStudio\AccessibilityKit\AltText\MediaIndex;
use WOWStudio\AccessibilityKit\Core\Registrable;
use WOWStudio\AccessibilityKit\Support\Capabilities;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Listing images that have no description, and writing the ones you type.
 *
 * Nothing here proposes any text. The description of an image depends on why
 * the image is on the page, which is a question about intent that this plugin
 * has no way to answer — so what it offers instead is the part it *can* do
 * well: finding every undescribed image on the site and putting them in one
 * list with a field beside each, rather than making somebody open forty media
 * screens one at a time.
 *
 * What gets written is `_wp_attachment_image_alt`, WordPress's own field. Not
 * an override, not a filter — the real thing. It therefore applies wherever
 * that image appears, is picked up by every theme and plugin without knowing
 * this one exists, and survives this plugin being deleted. A fix that only
 * works while our code is installed is not a fix, it is a dependency.
 *
 * @since 0.16.0
 */
final class AltTextController implements Registrable {

	/**
	 * Registers the routes.
	 *
	 * @since 0.16.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Declares the routes.
	 *
	 * @since 0.16.0
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
					'callback'            => array( $this, 'index' ),
					'permission_callback' => array( $this, 'can_edit_media' ),
					'args'                => array(
						'page'     => array(
							'type'              => 'integer',
							'default'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page' => array(
							'type'              => 'integer',
							'default'           => 25,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);

		register_rest_route(
			ScanController::REST_NAMESPACE,
			'/media/alt',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save' ),
					'permission_callback' => array( $this, 'can_edit_media' ),
					'args'                => array(
						'items' => array(
							'required'    => true,
							'type'        => 'array',
							'description' => __( 'Descriptions to write, one per image.', 'wowstudio-accessibility-kit' ),
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'id'         => array( 'type' => 'integer' ),
									'text'       => array( 'type' => 'string' ),
									'decorative' => array( 'type' => 'boolean' ),
								),
							),
						),
					),
				),
			)
		);
	}

	/**
	 * Requires the plugin's fix capability and core's upload capability.
	 *
	 * Ours alone is not enough: this writes to the media library, which is
	 * shared site-wide, so it must never let somebody change an image's
	 * description who could not already do it from the media screen. Each row
	 * is checked again individually in save(), because `upload_files` says a
	 * user may edit *some* media, not all of it.
	 *
	 * @since 0.16.0
	 *
	 * @return bool|WP_Error
	 */
	public function can_edit_media() {
		if ( ! current_user_can( Capabilities::APPLY_FIX ) ) {
			return new WP_Error(
				'wsak_cannot_apply_fix',
				__( 'You do not have permission to apply fixes.', 'wowstudio-accessibility-kit' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			return new WP_Error(
				'wsak_cannot_edit_media',
				__( 'Describing an image changes the media library, and your account cannot edit media.', 'wowstudio-accessibility-kit' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Returns one page of images that have never been described.
	 *
	 * @since 0.16.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function index( WP_REST_Request $request ): WP_REST_Response {
		$index = new MediaIndex();

		return new WP_REST_Response(
			$index->undescribed(
				max( 1, (int) $request->get_param( 'page' ) ),
				max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) )
			)
		);
	}

	/**
	 * Writes the descriptions that were typed.
	 *
	 * Reports per row rather than failing the whole submission. Somebody who
	 * has just typed twenty descriptions and pressed save should not lose the
	 * nineteen that were fine because the twentieth image was deleted in
	 * another tab.
	 *
	 * @since 0.16.0
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public function save( WP_REST_Request $request ): WP_REST_Response {
		$results = array();

		foreach ( (array) $request->get_param( 'items' ) as $item ) {
			$item = (array) $item;
			$id   = absint( $item['id'] ?? 0 );

			$results[] = array( 'id' => $id ) + $this->save_one(
				$id,
				(string) ( $item['text'] ?? '' ),
				(bool) ( $item['decorative'] ?? false )
			);
		}

		return new WP_REST_Response( array( 'results' => $results ) );
	}

	/**
	 * Writes one description, or says why it did not.
	 *
	 * @since 0.16.0
	 *
	 * @param int    $id         Attachment.
	 * @param string $text       The description.
	 * @param bool   $decorative Whether the image was marked as carrying no meaning.
	 * @return array{saved: bool, alt?: string, code?: string, message?: string}
	 */
	private function save_one( int $id, string $text, bool $decorative ): array {
		$post = $id > 0 ? get_post( $id ) : null;

		if ( ! $post instanceof WP_Post || 'attachment' !== $post->post_type ) {
			return array(
				'saved'   => false,
				'code'    => 'wsak_media_missing',
				'message' => __( 'That image is no longer in the media library.', 'wowstudio-accessibility-kit' ),
			);
		}

		if ( ! current_user_can( 'edit_post', $id ) ) {
			return array(
				'saved'   => false,
				'code'    => 'wsak_media_forbidden',
				'message' => __( 'Your account cannot edit this image.', 'wowstudio-accessibility-kit' ),
			);
		}

		$text = sanitize_text_field( $text );

		/*
		 * An empty description is a real and correct answer — it is how you say
		 * "this image carries no meaning, skip it" to a screen reader. But it
		 * is also what an empty field looks like, and the two must not be
		 * confused: writing one by accident hides a real image from somebody
		 * who needed to know it was there. So it has to be asked for.
		 */
		if ( '' === $text && ! $decorative ) {
			return array(
				'saved'   => false,
				'code'    => 'wsak_alt_empty',
				'message' => __( 'Write a description, or tick “decorative” if this image carries no meaning of its own.', 'wowstudio-accessibility-kit' ),
			);
		}

		$alt = $decorative ? '' : $text;

		update_post_meta( $id, '_wp_attachment_image_alt', $alt );

		/**
		 * Fires after a description is written from the alt-text screen.
		 *
		 * @since 0.16.0
		 *
		 * @param int    $id  Attachment that was described.
		 * @param string $alt What was written. An empty string means decorative.
		 */
		do_action( 'wsak_alt_text_saved', $id, $alt );

		return array(
			'saved' => true,
			'alt'   => $alt,
		);
	}
}
