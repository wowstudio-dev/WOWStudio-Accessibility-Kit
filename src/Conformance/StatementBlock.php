<?php
/**
 * Statement block and shortcode.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Conformance;

use WOWStudio\AccessibilityKit\Core\Registrable;

defined( 'ABSPATH' ) || exit;

/**
 * Publishes the accessibility statement, as a block and as a shortcode.
 *
 * Both render on the server, from current settings, every time. A block that
 * saved a copy of the markup could keep displaying an approved statement long
 * after the settings behind it had changed, which is precisely the failure the
 * attestation is there to prevent.
 *
 * @since 0.7.0
 */
final class StatementBlock implements Registrable {

	/**
	 * Shortcode tag.
	 *
	 * @since 0.7.0
	 * @var string
	 */
	public const SHORTCODE = 'wsak_accessibility_statement';

	/**
	 * Hooks the block and the shortcode.
	 *
	 * @since 0.7.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'register_block' ) );
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
	}

	/**
	 * Registers the block from its compiled metadata.
	 *
	 * @since 0.7.0
	 *
	 * @return void
	 */
	public function register_block(): void {
		$metadata = WSAK_PATH . 'build/blocks/statement';

		if ( ! file_exists( $metadata . '/block.json' ) ) {
			return;
		}

		register_block_type( $metadata, array( 'render_callback' => array( $this, 'render' ) ) );
	}

	/**
	 * Renders the statement.
	 *
	 * @since 0.7.0
	 *
	 * @return string
	 */
	public function render(): string {
		return ( new StatementGenerator() )->render();
	}
}
