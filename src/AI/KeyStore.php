<?php
/**
 * Encrypted API credential storage.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Stores provider API keys encrypted at rest.
 *
 * These are the user's own credentials for a paid third-party account. Losing
 * them costs someone real money, so a few rules hold throughout:
 *
 *   - Keys are encrypted with libsodium before they touch the database.
 *   - If libsodium is unavailable, storage is refused. It is never silently
 *     downgraded to plaintext, because a user who was told their key is
 *     encrypted would have no way to discover otherwise.
 *   - Nothing here ever returns a key to a REST response, and nothing logs one.
 *     Only has() and hint() are safe to expose.
 *
 * The encryption key is derived from wp_salt(), which means keys do not survive
 * a salt rotation. That is the correct trade-off: a rotated salt should
 * invalidate stored secrets, and the user can paste the key again.
 *
 * @since 0.5.0
 */
final class KeyStore {

	/**
	 * Option holding the encrypted credentials.
	 *
	 * @since 0.5.0
	 * @var string
	 */
	public const OPTION = 'wsak_credentials';

	/**
	 * Reports whether the platform can encrypt at all.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		return function_exists( 'sodium_crypto_secretbox' )
			&& function_exists( 'sodium_crypto_generichash' )
			&& defined( 'SODIUM_CRYPTO_SECRETBOX_KEYBYTES' );
	}

	/**
	 * Stores a key for a provider.
	 *
	 * @since 0.5.0
	 *
	 * @param string $provider Provider identifier.
	 * @param string $key      Plain-text API key.
	 * @return bool True when stored.
	 */
	public function set( string $provider, string $key ): bool {
		if ( ! self::is_available() ) {
			return false;
		}

		$key = trim( $key );

		if ( '' === $key ) {
			return $this->forget( $provider );
		}

		$cipher = $this->encrypt( $key );

		if ( '' === $cipher ) {
			return false;
		}

		$stored              = $this->all();
		$stored[ $provider ] = array(
			'cipher' => $cipher,
			'hint'   => $this->hint_for( $key ),
			'stored' => gmdate( 'Y-m-d H:i:s' ),
		);

		// Autoload is off: credentials have no business being loaded into memory
		// on every request, including front-end ones.
		return update_option( self::OPTION, $stored, false );
	}

	/**
	 * Returns the decrypted key for a provider.
	 *
	 * The only method that yields a key. Callers must pass it straight to the
	 * provider and never store, echo, or log it.
	 *
	 * @since 0.5.0
	 *
	 * @param string $provider Provider identifier.
	 * @return string Empty when absent or undecryptable.
	 */
	public function get( string $provider ): string {
		if ( ! self::is_available() ) {
			return '';
		}

		$stored = $this->all();
		$cipher = $stored[ $provider ]['cipher'] ?? '';

		return is_string( $cipher ) && '' !== $cipher ? $this->decrypt( $cipher ) : '';
	}

	/**
	 * Reports whether a provider has a usable key.
	 *
	 * @since 0.5.0
	 *
	 * @param string $provider Provider identifier.
	 * @return bool
	 */
	public function has( string $provider ): bool {
		return '' !== $this->get( $provider );
	}

	/**
	 * Returns a masked hint for display.
	 *
	 * Enough to recognise which key is stored, never enough to use it.
	 *
	 * @since 0.5.0
	 *
	 * @param string $provider Provider identifier.
	 * @return string
	 */
	public function hint( string $provider ): string {
		$stored = $this->all();
		$hint   = $stored[ $provider ]['hint'] ?? '';

		return is_string( $hint ) ? $hint : '';
	}

	/**
	 * Removes a provider's key.
	 *
	 * @since 0.5.0
	 *
	 * @param string $provider Provider identifier.
	 * @return bool
	 */
	public function forget( string $provider ): bool {
		$stored = $this->all();

		if ( ! isset( $stored[ $provider ] ) ) {
			return true;
		}

		unset( $stored[ $provider ] );

		return update_option( self::OPTION, $stored, false );
	}

	/**
	 * Removes every stored key.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	public function forget_all(): bool {
		return delete_option( self::OPTION );
	}

	/**
	 * Returns the raw stored structure.
	 *
	 * @since 0.5.0
	 *
	 * @return array<string, array<string, string>>
	 */
	private function all(): array {
		$stored = get_option( self::OPTION, array() );

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Builds a display hint from a key.
	 *
	 * @since 0.5.0
	 *
	 * @param string $key Plain-text key.
	 * @return string
	 */
	private function hint_for( string $key ): string {
		$length = mb_strlen( $key, 'UTF-8' );

		if ( $length <= 4 ) {
			return str_repeat( '•', $length );
		}

		return '••••' . mb_substr( $key, -4, null, 'UTF-8' );
	}

	/**
	 * Derives the symmetric encryption key.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	private function encryption_key(): string {
		/**
		 * Filters the material the encryption key is derived from.
		 *
		 * A site that keeps secrets outside the database can point this at its
		 * own source. Changing it makes existing stored keys undecryptable, and
		 * they will have to be entered again.
		 *
		 * @since 0.5.0
		 *
		 * @param string $material Key material.
		 */
		$material = (string) apply_filters( 'wsak_encryption_material', wp_salt( 'secure_auth' ) );

		return sodium_crypto_generichash( $material, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	}

	/**
	 * Encrypts a value.
	 *
	 * The nonce is random per write and stored alongside the ciphertext, which
	 * is what stops two identical keys producing identical stored values.
	 *
	 * @since 0.5.0
	 *
	 * @param string $plain Value to encrypt.
	 * @return string Base64 of nonce and ciphertext, or empty on failure.
	 */
	private function encrypt( string $plain ): string {
		try {
			$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$secret = $this->encryption_key();
			$cipher = sodium_crypto_secretbox( $plain, $nonce, $secret );

			sodium_memzero( $secret );

			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Encoding ciphertext for storage in a text column, not obfuscating code.
			return base64_encode( $nonce . $cipher );
		} catch ( \Throwable $error ) {
			// Deliberately no logging: the message could carry the plaintext.
			return '';
		}
	}

	/**
	 * Decrypts a stored value.
	 *
	 * @since 0.5.0
	 *
	 * @param string $stored Base64 of nonce and ciphertext.
	 * @return string Empty when the value cannot be decrypted.
	 */
	private function decrypt( string $stored ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding our own ciphertext, not executing decoded input.
		$raw = base64_decode( $stored, true );

		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}

		try {
			$nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$secret = $this->encryption_key();
			$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, $secret );

			sodium_memzero( $secret );

			return false === $plain ? '' : $plain;
		} catch ( \Throwable $error ) {
			return '';
		}
	}
}
