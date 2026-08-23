<?php
/**
 * Shared provider behaviour.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\AI\Providers;

use WOWStudio\AccessibilityKit\AI\ImageContext;
use WOWStudio\AccessibilityKit\AI\ProviderInterface;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * The parts every provider does identically.
 *
 * HTTP goes through wp_remote_post rather than an official vendor SDK. That is
 * deliberate: WordPress sites route outbound requests through their own
 * filters, proxies, and blocking rules, and a vendored SDK would bypass all of
 * them. It would also collide with other plugins vendoring the same library.
 *
 * @since 0.5.0
 */
abstract class AbstractProvider implements ProviderInterface {

	/**
	 * Seconds to wait for a provider.
	 *
	 * @since 0.5.0
	 * @var int
	 */
	protected const TIMEOUT = 60;

	/**
	 * Longest acceptable alt text, in characters.
	 *
	 * Screen readers do not chunk alt text, so an over-long description is read
	 * as one unbroken run. Anything longer than this belongs in the body copy
	 * or a long description, not the alt attribute.
	 *
	 * @since 0.5.0
	 * @var int
	 */
	protected const MAX_ALT_LENGTH = 250;

	/**
	 * Builds the instruction sent with every image.
	 *
	 * @since 0.5.0
	 *
	 * @param ImageContext $image Image and context.
	 * @return string
	 */
	protected function prompt( ImageContext $image ): string {
		$instruction = <<<'PROMPT'
Write alternative text for this image, for someone who cannot see it.

Rules:
- Describe what the image conveys in its context, not every visual detail.
- One sentence, under 125 characters where possible.
- Do not begin with "image of", "picture of", or "graphic of".
- Do not end with a full stop unless the text is a full sentence.
- If the image carries no meaning and is purely decorative, reply with exactly: DECORATIVE
- Reply with the alt text alone. No quotes, no preamble, no explanation.
PROMPT;

		$context = $image->describe();

		if ( '' !== $context ) {
			$instruction .= "\n\nContext:\n" . $context;
		}

		return $instruction;
	}

	/**
	 * Posts JSON and decodes the response.
	 *
	 * @since 0.5.0
	 *
	 * @param string                $url     Endpoint.
	 * @param array<string, mixed>  $body    Request body.
	 * @param array<string, string> $headers Request headers.
	 * @return array<string, mixed>|WP_Error
	 */
	protected function post_json( string $url, array $body, array $headers ) {
		$encoded = wp_json_encode( $body );

		if ( false === $encoded ) {
			return new WP_Error(
				'wsak_ai_encode_failed',
				__( 'The request to the AI provider could not be prepared.', 'wowstudio-accessibility-kit' )
			);
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array_merge( array( 'Content-Type' => 'application/json' ), $headers ),
				'body'    => $encoded,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'wsak_ai_unreachable',
				sprintf(
					/* translators: 1: provider name, 2: underlying error. */
					__( 'Could not reach %1$s: %2$s', 'wowstudio-accessibility-kit' ),
					$this->label(),
					$response->get_error_message()
				)
			);
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'wsak_ai_rejected',
				sprintf(
					/* translators: 1: provider name, 2: HTTP status, 3: provider's message. */
					__( '%1$s refused the request (HTTP %2$d): %3$s', 'wowstudio-accessibility-kit' ),
					$this->label(),
					$code,
					$this->error_message_from( is_array( $decoded ) ? $decoded : array() )
				),
				array( 'status' => 502 )
			);
		}

		if ( ! is_array( $decoded ) ) {
			return new WP_Error(
				'wsak_ai_unreadable',
				sprintf(
					/* translators: %s: provider name. */
					__( 'The response from %s could not be read.', 'wowstudio-accessibility-kit' ),
					$this->label()
				)
			);
		}

		return $decoded;
	}

	/**
	 * Pulls a human-readable message out of a provider error body.
	 *
	 * Providers disagree on the shape, and a raw JSON dump in the interface
	 * helps nobody.
	 *
	 * @since 0.5.0
	 *
	 * @param array<string, mixed> $decoded Decoded error body.
	 * @return string
	 */
	protected function error_message_from( array $decoded ): string {
		$candidates = array(
			$decoded['error']['message'] ?? null,
			$decoded['error']['status'] ?? null,
			$decoded['message'] ?? null,
		);

		foreach ( $candidates as $candidate ) {
			if ( is_string( $candidate ) && '' !== $candidate ) {
				return $candidate;
			}
		}

		return __( 'no reason given', 'wowstudio-accessibility-kit' );
	}

	/**
	 * Cleans up whatever the model returned.
	 *
	 * Models are inconsistent about wrapping quotes and adding preamble however
	 * firmly the prompt asks them not to, so the output is normalised rather
	 * than trusted.
	 *
	 * @since 0.5.0
	 *
	 * @param string $raw Raw model output.
	 * @return string
	 */
	protected function tidy( string $raw ): string {
		$text = trim( $raw );
		$text = (string) preg_replace( '/^(alt text|alt)\s*[:\-]\s*/iu', '', $text );
		$text = trim( $text, " \t\n\r\0\x0B\"'“”‘’" );
		$text = (string) preg_replace( '/\s+/u', ' ', $text );

		if ( mb_strlen( $text, 'UTF-8' ) > self::MAX_ALT_LENGTH ) {
			$text = rtrim( mb_substr( $text, 0, self::MAX_ALT_LENGTH, 'UTF-8' ) ) . '…';
		}

		return $text;
	}
}
