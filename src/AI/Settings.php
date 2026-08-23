<?php
/**
 * AI settings.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\AI;

use WOWStudio\AccessibilityKit\Core\Installer;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the AI configuration.
 *
 * Kept separate from the credentials, which live encrypted in KeyStore. These
 * settings are safe to read over REST; the keys never are.
 *
 * @since 0.5.0
 */
final class Settings {

	/**
	 * Post meta marking content that must never be sent to a provider.
	 *
	 * @since 0.5.0
	 * @var string
	 */
	public const SKIP_META = '_wsak_skip_ai';

	/**
	 * Returns the current AI settings, with defaults filled in.
	 *
	 * @since 0.5.0
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$settings = get_option( Installer::SETTINGS_OPTION, array() );
		$ai       = is_array( $settings ) && isset( $settings['ai'] ) && is_array( $settings['ai'] )
			? $settings['ai']
			: array();

		return array(
			'provider'          => isset( $ai['provider'] ) ? (string) $ai['provider'] : '',
			'model'             => isset( $ai['model'] ) ? (string) $ai['model'] : '',
			// Default off. Generating alt text costs the user money at their
			// provider, so it never starts happening without them asking.
			'auto_on_upload'    => ! empty( $ai['auto_on_upload'] ),
			// Default on: send the picture and little else.
			'send_page_context' => ! isset( $ai['send_page_context'] ) || ! empty( $ai['send_page_context'] ),
		);
	}

	/**
	 * Saves AI settings, merging over what is already stored.
	 *
	 * @since 0.5.0
	 *
	 * @param array<string, mixed> $changes Values to change.
	 * @return array<string, mixed> The settings as they now stand.
	 */
	public function update( array $changes ): array {
		$settings = get_option( Installer::SETTINGS_OPTION, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$current = $this->all();

		foreach ( array( 'provider', 'model' ) as $field ) {
			if ( array_key_exists( $field, $changes ) ) {
				$current[ $field ] = sanitize_text_field( (string) $changes[ $field ] );
			}
		}

		foreach ( array( 'auto_on_upload', 'send_page_context' ) as $field ) {
			if ( array_key_exists( $field, $changes ) ) {
				$current[ $field ] = (bool) $changes[ $field ];
			}
		}

		$settings['ai'] = $current;

		update_option( Installer::SETTINGS_OPTION, $settings, false );

		return $current;
	}

	/**
	 * Returns the provider the site has chosen, if it is usable.
	 *
	 * @since 0.5.0
	 *
	 * @param ProviderRegistry $registry Available providers.
	 * @return ProviderInterface|null
	 */
	public function active_provider( ProviderRegistry $registry ): ?ProviderInterface {
		$chosen = $this->all()['provider'];

		return '' === $chosen ? null : $registry->get( $chosen );
	}

	/**
	 * Reports whether a post has opted out of AI processing.
	 *
	 * @since 0.5.0
	 *
	 * @param int $post_id Post to check.
	 * @return bool
	 */
	public function is_opted_out( int $post_id ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}

		return '1' === (string) get_post_meta( $post_id, self::SKIP_META, true );
	}
}
