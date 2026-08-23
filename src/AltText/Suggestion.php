<?php
/**
 * A generated alt-text suggestion.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\AltText;

defined( 'ABSPATH' ) || exit;

/**
 * What came back, and how.
 *
 * A suggestion, never an applied change. Nothing here writes to the media
 * library; the person reviewing it decides.
 *
 * @since 0.5.0
 */
final class Suggestion {

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param string $text          The proposed alt text. Empty when decorative.
	 * @param bool   $is_decorative Whether the model judged the image decorative.
	 * @param string $engine        Either "wp-ai-client" or "byok".
	 * @param string $provider      Provider identifier used.
	 * @param string $model         Model identifier used.
	 * @param int    $payload_kb    Roughly how much image data was sent.
	 */
	public function __construct(
		public readonly string $text,
		public readonly bool $is_decorative,
		public readonly string $engine,
		public readonly string $provider,
		public readonly string $model,
		public readonly int $payload_kb
	) {}

	/**
	 * Shapes the suggestion for a REST response.
	 *
	 * @since 0.5.0
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'text'          => $this->text,
			'is_decorative' => $this->is_decorative,
			'engine'        => $this->engine,
			'provider'      => $this->provider,
			'model'         => $this->model,
			'payload_kb'    => $this->payload_kb,
		);
	}
}
