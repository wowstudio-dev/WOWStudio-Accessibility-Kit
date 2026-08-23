<?php
/**
 * WordPress AI Client bridge.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\AI;

use Throwable;
use WordPress\AiClient\AiClient;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Uses WordPress's own AI Client when the site has one configured.
 *
 * WordPress 7.0 ships a provider-agnostic AI client, and a site that has
 * already configured credentials there should not have to enter them again
 * here. When it is unavailable or unconfigured, generation falls back to the
 * bring-your-own-key providers in this plugin.
 *
 * Which path ran is always reported back to the caller, so the settings screen
 * can say which one a site is actually using rather than leaving it a mystery.
 *
 * @since 0.5.0
 */
final class AiClientBridge {

	/**
	 * Reports whether WordPress ships an AI client at all.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return class_exists( AiClient::class );
	}

	/**
	 * Reports whether the AI client is configured for a provider.
	 *
	 * Guarded: the client is young, and a signature change should degrade to
	 * bring-your-own-key rather than fatal a site.
	 *
	 * @since 0.5.0
	 *
	 * @param string $provider_id Provider identifier.
	 * @return bool
	 */
	public function is_configured_for( string $provider_id ): bool {
		if ( ! $this->is_available() || '' === $provider_id ) {
			return false;
		}

		try {
			return (bool) AiClient::isConfigured( $provider_id );
		} catch ( Throwable $error ) {
			return false;
		}
	}

	/**
	 * Describes an image through the WordPress AI client.
	 *
	 * @since 0.5.0
	 *
	 * @param ImageContext $image       Image and its minimal context.
	 * @param string       $prompt      Instruction to send with the image.
	 * @param string       $provider_id Provider identifier.
	 * @param string       $model       Model identifier, or empty for the site default.
	 * @return string|WP_Error
	 */
	public function generate_alt_text( ImageContext $image, string $prompt, string $provider_id, string $model ) {
		if ( '' === $image->path || ! is_readable( $image->path ) ) {
			return new WP_Error(
				'wsak_ai_no_file',
				__( 'The image file could not be read.', 'wowstudio-accessibility-kit' )
			);
		}

		try {
			$builder = AiClient::prompt( $prompt )
				->withFile( $image->path, $image->mime )
				->usingProvider( $provider_id )
				->usingMaxTokens( 300 );

			if ( '' !== $model ) {
				$builder = $builder->usingModelPreference( $model );
			}

			$text = (string) $builder->generateText();
		} catch ( Throwable $error ) {
			return new WP_Error(
				'wsak_ai_client_failed',
				sprintf(
					/* translators: %s: the underlying error message. */
					__( 'The WordPress AI client could not describe this image: %s', 'wowstudio-accessibility-kit' ),
					$error->getMessage()
				)
			);
		}

		if ( '' === trim( $text ) ) {
			return new WP_Error(
				'wsak_ai_empty',
				__( 'The WordPress AI client returned no description for this image.', 'wowstudio-accessibility-kit' )
			);
		}

		return $text;
	}
}
