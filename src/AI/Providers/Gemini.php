<?php
/**
 * Google Gemini provider.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\AI\Providers;

use WOWStudio\AccessibilityKit\AI\ImageContext;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Google's Generative Language API.
 *
 * @since 0.5.0
 */
final class Gemini extends AbstractProvider {

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'gemini';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function label(): string {
		return 'Google Gemini';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function key_url(): string {
		return 'https://aistudio.google.com/app/apikey';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.5.0
	 *
	 * @return array<string, string>
	 */
	public function models(): array {
		return array(
			'gemini-2.0-flash'      => 'Gemini 2.0 Flash',
			'gemini-2.0-flash-lite' => 'Gemini 2.0 Flash Lite',
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function default_model(): string {
		return 'gemini-2.0-flash';
	}

	/**
	 * {@inheritDoc}
	 *
	 * Gemini takes the key as a header rather than a bearer token, and nests
	 * the image beside the prompt as inline data.
	 *
	 * @since 0.5.0
	 *
	 * @param ImageContext $image   The image and its minimal context.
	 * @param string       $api_key The user's API key.
	 * @param string       $model   Model identifier.
	 * @return string|WP_Error
	 */
	public function generate_alt_text( ImageContext $image, string $api_key, string $model ) {
		$model = '' !== $model ? $model : $this->default_model();

		$decoded = $this->post_json(
			sprintf( 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent', rawurlencode( $model ) ),
			array(
				'contents'         => array(
					array(
						'parts' => array(
							array( 'text' => $this->prompt( $image ) ),
							array(
								'inline_data' => array(
									'mime_type' => $image->mime,
									'data'      => $image->base64,
								),
							),
						),
					),
				),
				'generationConfig' => array( 'maxOutputTokens' => 300 ),
			),
			array( 'x-goog-api-key' => $api_key )
		);

		if ( is_wp_error( $decoded ) ) {
			return $decoded;
		}

		$text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';

		if ( ! is_string( $text ) || '' === trim( $text ) ) {
			return new WP_Error(
				'wsak_ai_empty',
				__( 'Gemini returned no description for this image.', 'wowstudio-accessibility-kit' )
			);
		}

		return $this->tidy( $text );
	}
}
