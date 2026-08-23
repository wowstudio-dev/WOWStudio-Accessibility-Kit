<?php
/**
 * Credential storage tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Functions;
use WOWStudio\AccessibilityKit\AI\KeyStore;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests that API keys are encrypted and never leak.
 *
 * @covers \WOWStudio\AccessibilityKit\AI\KeyStore
 */
final class KeyStoreTest extends TestCase {

	/**
	 * Stand-in for the options table.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = array();

	/**
	 * Wires get_option and update_option to an in-memory store.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->options = array();

		Functions\when( 'get_option' )->alias(
			fn( string $name, $fallback = false ) => $this->options[ $name ] ?? $fallback
		);
		Functions\when( 'update_option' )->alias(
			function ( string $name, $value ): bool {
				$this->options[ $name ] = $value;

				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			function ( string $name ): bool {
				unset( $this->options[ $name ] );

				return true;
			}
		);
		Functions\when( 'wp_salt' )->justReturn( 'a-stable-test-salt-value-0123456789' );
	}

	/**
	 * A stored key comes back exactly as it went in.
	 *
	 * @return void
	 */
	public function test_a_key_round_trips(): void {
		$store = new KeyStore();

		$this->assertTrue( $store->set( 'anthropic', 'sk-ant-secret-value' ) );
		$this->assertSame( 'sk-ant-secret-value', $store->get( 'anthropic' ) );
		$this->assertTrue( $store->has( 'anthropic' ) );
	}

	/**
	 * The stored value contains no trace of the plaintext.
	 *
	 * The whole point of the class. If this fails, the key is sitting in the
	 * database in the clear.
	 *
	 * @return void
	 */
	public function test_the_plaintext_never_reaches_the_database(): void {
		$store = new KeyStore();
		$store->set( 'openai', 'sk-plaintext-must-not-appear' );

		$stored = wp_json_encode( $this->options[ KeyStore::OPTION ] );

		$this->assertIsString( $stored );
		$this->assertStringNotContainsString( 'sk-plaintext-must-not-appear', $stored );
		$this->assertStringNotContainsString( 'plaintext-must-not-appear', $stored );
	}

	/**
	 * The same key stored twice produces different ciphertext.
	 *
	 * A fresh nonce per write is what stops the stored value being a fingerprint
	 * of the key.
	 *
	 * @return void
	 */
	public function test_repeated_writes_produce_different_ciphertext(): void {
		$store = new KeyStore();

		$store->set( 'openai', 'same-key' );
		$first = $this->options[ KeyStore::OPTION ]['openai']['cipher'];

		$store->set( 'openai', 'same-key' );
		$second = $this->options[ KeyStore::OPTION ]['openai']['cipher'];

		$this->assertNotSame( $first, $second );
	}

	/**
	 * The hint identifies a key without revealing it.
	 *
	 * @return void
	 */
	public function test_the_hint_masks_all_but_the_last_four(): void {
		$store = new KeyStore();
		$store->set( 'gemini', 'abcdefghijklmnop' );

		$this->assertSame( '••••mnop', $store->hint( 'gemini' ) );
	}

	/**
	 * Storing an empty value removes the key rather than storing nothing.
	 *
	 * @return void
	 */
	public function test_storing_an_empty_key_forgets_it(): void {
		$store = new KeyStore();
		$store->set( 'openai', 'a-key' );

		$this->assertTrue( $store->set( 'openai', '   ' ) );
		$this->assertFalse( $store->has( 'openai' ) );
	}

	/**
	 * Ciphertext from a different salt does not decrypt.
	 *
	 * Rotating the site salts should invalidate stored secrets, not silently
	 * return corrupt values.
	 *
	 * @return void
	 */
	public function test_a_rotated_salt_invalidates_stored_keys(): void {
		$store = new KeyStore();
		$store->set( 'anthropic', 'sk-ant-value' );

		Functions\when( 'wp_salt' )->justReturn( 'a-completely-different-salt-value' );

		$this->assertSame( '', $store->get( 'anthropic' ) );
		$this->assertFalse( $store->has( 'anthropic' ) );
	}

	/**
	 * Forgetting a key removes it and leaves the others alone.
	 *
	 * @return void
	 */
	public function test_forgetting_one_key_leaves_the_rest(): void {
		$store = new KeyStore();
		$store->set( 'openai', 'one' );
		$store->set( 'gemini', 'two' );

		$store->forget( 'openai' );

		$this->assertFalse( $store->has( 'openai' ) );
		$this->assertSame( 'two', $store->get( 'gemini' ) );
	}
}
