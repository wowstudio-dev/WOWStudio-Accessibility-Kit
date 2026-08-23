<?php
/**
 * OpenAI provider.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\AI\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * OpenAI's chat completions API.
 *
 * @since 0.5.0
 */
final class OpenAI extends OpenAiCompatible {

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'openai';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function label(): string {
		return 'OpenAI';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function key_url(): string {
		return 'https://platform.openai.com/api-keys';
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
			'gpt-4o'      => 'GPT-4o',
			'gpt-4o-mini' => 'GPT-4o mini',
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
		return 'gpt-4o-mini';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	protected function endpoint(): string {
		return 'https://api.openai.com/v1/chat/completions';
	}
}
