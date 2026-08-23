<?php
/**
 * OpenRouter provider.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\AI\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * OpenRouter, which fronts many models behind the OpenAI request shape.
 *
 * @since 0.5.0
 */
final class OpenRouter extends OpenAiCompatible {

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'openrouter';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function label(): string {
		return 'OpenRouter';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function key_url(): string {
		return 'https://openrouter.ai/keys';
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
			'anthropic/claude-opus-5'     => 'Claude Opus 5',
			'openai/gpt-4o-mini'          => 'GPT-4o mini',
			'google/gemini-2.0-flash-001' => 'Gemini 2.0 Flash',
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
		return 'anthropic/claude-opus-5';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	protected function endpoint(): string {
		return 'https://openrouter.ai/api/v1/chat/completions';
	}

	/**
	 * {@inheritDoc}
	 *
	 * OpenRouter asks callers to identify themselves so usage is attributable.
	 *
	 * @since 0.5.0
	 *
	 * @return array<string, string>
	 */
	protected function extra_headers(): array {
		return array(
			'HTTP-Referer' => home_url( '/' ),
			'X-Title'      => 'WOWStudio Accessibility Kit',
		);
	}
}
