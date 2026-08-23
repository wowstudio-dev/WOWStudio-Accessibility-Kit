<?php
/**
 * AI provider contract.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\AI;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * One bring-your-own-key AI provider.
 *
 * @since 0.5.0
 */
interface ProviderInterface {

	/**
	 * Returns the stable provider identifier.
	 *
	 * Stored in settings and used as the credential key, so it must not change.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function id(): string;

	/**
	 * Returns the provider's display name.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function label(): string;

	/**
	 * Returns where the user gets an API key.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function key_url(): string;

	/**
	 * Returns the selectable models, keyed by model identifier.
	 *
	 * @since 0.5.0
	 *
	 * @return array<string, string>
	 */
	public function models(): array;

	/**
	 * Returns the model used when the user has not chosen one.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function default_model(): string;

	/**
	 * Generates text from a prompt.
	 *
	 * @since 0.6.0
	 *
	 * @param string $prompt  The instruction.
	 * @param string $api_key The user's API key.
	 * @param string $model   Model identifier.
	 * @return string|WP_Error The generated text, or an error to show the user.
	 */
	public function generate_text( string $prompt, string $api_key, string $model );

	/**
	 * Describes an image.
	 *
	 * @since 0.5.0
	 *
	 * @param ImageContext $image   The image and its minimal context.
	 * @param string       $api_key The user's API key.
	 * @param string       $model   Model identifier.
	 * @return string|WP_Error The alt text, or an error to show the user.
	 */
	public function generate_alt_text( ImageContext $image, string $api_key, string $model );
}
