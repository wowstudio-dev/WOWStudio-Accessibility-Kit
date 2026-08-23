<?php
/**
 * Anthropic provider.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\AI\Providers;

use WOWStudio\AccessibilityKit\AI\ImageContext;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Claude, through the Messages API.
 *
 * @since 0.5.0
 */
final class Anthropic extends AbstractProvider {

	/**
	 * API version header value required on every request.
	 *
	 * @since 0.5.0
	 * @var string
	 */
	private const API_VERSION = '2023-06-01';

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'anthropic';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function label(): string {
		return 'Anthropic (Claude)';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function key_url(): string {
		return 'https://console.anthropic.com/settings/keys';
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
			'claude-opus-5'    => 'Claude Opus 5',
			'claude-sonnet-5'  => 'Claude Sonnet 5',
			'claude-haiku-4-5' => 'Claude Haiku 4.5',
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
		return 'claude-opus-5';
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
			'https://api.anthropic.com/v1/messages',
			array(
				'model'      => '' !== $model ? $model : $this->default_model(),
				'max_tokens' => 300,
				'messages'   => array(
					array(
						'role'    => 'user',
						'content' => array(
							array(
								'type'   => 'image',
								'source' => array(
									'type'       => 'base64',
									'media_type' => $image->mime,
									'data'       => $image->base64,
								),
							),
							array(
								'type' => 'text',
								'text' => $this->prompt( $image ),
							),
						),
					),
				),
			),
			array(
				'x-api-key'         => $api_key,
				'anthropic-version' => self::API_VERSION,
			)
		);

		if ( is_wp_error( $decoded ) ) {
			return $decoded;
		}

		foreach ( (array) ( $decoded['content'] ?? array() ) as $block ) {
			if ( is_array( $block ) && 'text' === ( $block['type'] ?? '' ) ) {
				return $this->tidy( (string) ( $block['text'] ?? '' ) );
			}
		}

		return new WP_Error(
			'wsak_ai_empty',
			__( 'Claude returned no description for this image.', 'wowstudio-accessibility-kit' )
		);
	}
}
