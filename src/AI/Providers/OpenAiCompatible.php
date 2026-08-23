<?php
/**
 * Shared behaviour for OpenAI-shaped chat APIs.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\AI\Providers;

use WOWStudio\AccessibilityKit\AI\ImageContext;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * The /chat/completions request shape, which several providers share.
 *
 * @since 0.5.0
 */
abstract class OpenAiCompatible extends AbstractProvider {

	/**
	 * Returns the chat completions endpoint.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	abstract protected function endpoint(): string;

	/**
	 * Returns provider-specific headers beyond authorisation.
	 *
	 * @since 0.5.0
	 *
	 * @return array<string, string>
	 */
	protected function extra_headers(): array {
		return array();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.5.0
	 *
	 * @param ImageContext $image   The image and its minimal context.
	 * @param string       $api_key The user's API key.
	 * @param string       $model   Model identifier.
	 * @return string|WP_Error
	 */
	public function generate_alt_text( ImageContext $image, string $api_key, string $model ) {
		$decoded = $this->post_json(
			$this->endpoint(),
			array(
				'model'      => '' !== $model ? $model : $this->default_model(),
				'max_tokens' => 300,
				'messages'   => array(
					array(
						'role'    => 'user',
						'content' => array(
							array(
								'type' => 'text',
								'text' => $this->prompt( $image ),
							),
							array(
								'type'      => 'image_url',
								'image_url' => array(
									'url' => sprintf( 'data:%s;base64,%s', $image->mime, $image->base64 ),
								),
							),
						),
					),
				),
			),
			array_merge(
				array( 'Authorization' => 'Bearer ' . $api_key ),
				$this->extra_headers()
			)
		);

		if ( is_wp_error( $decoded ) ) {
			return $decoded;
		}

		$text = $decoded['choices'][0]['message']['content'] ?? '';

		if ( ! is_string( $text ) || '' === trim( $text ) ) {
			return new WP_Error(
				'wsak_ai_empty',
				sprintf(
					/* translators: %s: provider name. */
					__( '%s returned no description for this image.', 'wowstudio-accessibility-kit' ),
					$this->label()
				)
			);
		}

		return $this->tidy( $text );
	}
}
