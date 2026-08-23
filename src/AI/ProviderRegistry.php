<?php
/**
 * Available AI providers.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\AI;

use WOWStudio\AccessibilityKit\AI\Providers\Anthropic;
use WOWStudio\AccessibilityKit\AI\Providers\Gemini;
use WOWStudio\AccessibilityKit\AI\Providers\OpenAI;
use WOWStudio\AccessibilityKit\AI\Providers\OpenRouter;

defined( 'ABSPATH' ) || exit;

/**
 * Holds the providers a site can choose between.
 *
 * @since 0.5.0
 */
final class ProviderRegistry {

	/**
	 * Providers keyed by identifier.
	 *
	 * @since 0.5.0
	 * @var array<string, ProviderInterface>
	 */
	private array $providers = array();

	/**
	 * Builds a registry with the bundled providers.
	 *
	 * @since 0.5.0
	 *
	 * @return self
	 */
	public static function with_defaults(): self {
		$registry = new self();

		/**
		 * Filters the AI providers offered to the site.
		 *
		 * @since 0.5.0
		 *
		 * @param ProviderInterface[] $providers Bundled providers.
		 */
		$providers = (array) apply_filters(
			'wsak_ai_providers',
			array( new Anthropic(), new OpenAI(), new Gemini(), new OpenRouter() )
		);

		foreach ( $providers as $provider ) {
			if ( $provider instanceof ProviderInterface ) {
				$registry->providers[ $provider->id() ] = $provider;
			}
		}

		return $registry;
	}

	/**
	 * Returns one provider.
	 *
	 * @since 0.5.0
	 *
	 * @param string $id Provider identifier.
	 * @return ProviderInterface|null
	 */
	public function get( string $id ): ?ProviderInterface {
		return $this->providers[ $id ] ?? null;
	}

	/**
	 * Returns every provider.
	 *
	 * @since 0.5.0
	 *
	 * @return array<string, ProviderInterface>
	 */
	public function all(): array {
		return $this->providers;
	}

	/**
	 * Describes the providers for the settings screen.
	 *
	 * Never includes a key. Only whether one is stored, and a masked hint.
	 *
	 * @since 0.5.0
	 *
	 * @param KeyStore $keys Credential store.
	 * @return array<int, array<string, mixed>>
	 */
	public function describe( KeyStore $keys ): array {
		$described = array();

		foreach ( $this->providers as $provider ) {
			$described[] = array(
				'id'            => $provider->id(),
				'label'         => $provider->label(),
				'key_url'       => $provider->key_url(),
				'models'        => $provider->models(),
				'default_model' => $provider->default_model(),
				'has_key'       => $keys->has( $provider->id() ),
				'key_hint'      => $keys->hint( $provider->id() ),
			);
		}

		return $described;
	}
}
