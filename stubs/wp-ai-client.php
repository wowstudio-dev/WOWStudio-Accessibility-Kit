<?php
/**
 * Stubs for the WordPress 7.0 AI Client.
 *
 * Used by PHPStan only. It is never loaded at runtime and never loaded by the
 * test bootstrap, so it cannot make class_exists() lie to the bridge.
 *
 * The bundled WordPress stubs package still describes WordPress 6.9, which
 * predates this API. These signatures were read from
 * wp-includes/php-ai-client/ on a WordPress 7.1 install rather than guessed;
 * they describe only the surface this plugin calls.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WordPress\AiClient\Builders;

/**
 * Fluent prompt builder.
 */
class PromptBuilder {

	/**
	 * Attaches a file to the prompt.
	 *
	 * @param string      $file      Path or URL.
	 * @param string|null $mime_type MIME type.
	 * @return self
	 */
	public function withFile( $file, ?string $mime_type = null ): self {
		return $this;
	}

	/**
	 * Pins the provider.
	 *
	 * @param string $provider_id_or_class_name Provider identifier.
	 * @return self
	 */
	public function usingProvider( string $provider_id_or_class_name ): self {
		return $this;
	}

	/**
	 * Expresses a model preference.
	 *
	 * @param mixed ...$preferred_models Model identifiers.
	 * @return self
	 */
	public function usingModelPreference( ...$preferred_models ): self {
		return $this;
	}

	/**
	 * Caps the response length.
	 *
	 * @param int $max_tokens Token ceiling.
	 * @return self
	 */
	public function usingMaxTokens( int $max_tokens ): self {
		return $this;
	}

	/**
	 * Runs the prompt and returns text.
	 *
	 * @return string
	 */
	public function generateText(): string {
		return '';
	}
}

namespace WordPress\AiClient;

use WordPress\AiClient\Builders\PromptBuilder;

/**
 * Entry point for the WordPress AI client.
 */
class AiClient {

	/**
	 * Reports whether a provider is configured.
	 *
	 * @param mixed $availability_or_id_or_class_name Provider identifier.
	 * @return bool
	 */
	public static function isConfigured( $availability_or_id_or_class_name ): bool {
		return false;
	}

	/**
	 * Starts a prompt.
	 *
	 * @param mixed $prompt   Prompt text.
	 * @param mixed $registry Optional provider registry.
	 * @return PromptBuilder
	 */
	public static function prompt( $prompt = null, $registry = null ): PromptBuilder {
		return new PromptBuilder();
	}
}
