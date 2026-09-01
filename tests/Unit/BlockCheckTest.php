<?php
/**
 * Tests for checking blocks while somebody is still writing them.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Functions;
use WOWStudio\AccessibilityKit\Rest\BlockCheckController;
use WOWStudio\AccessibilityKit\Tests\TestCase;
use WP_REST_Request;

/**
 * Tests the live editor check.
 *
 * Two properties matter more than the findings themselves. It must attribute a
 * finding to the block that produced it, because a panel that points at the
 * wrong block is worse than one that points at nothing. And it must write
 * nothing: this runs while somebody types, and a scan record per keystroke
 * would be nonsense — worse, a coverage badge that moved while somebody wrote
 * would be claiming something nobody had checked.
 *
 * @covers \WOWStudio\AccessibilityKit\Rest\BlockCheckController
 */
final class BlockCheckTest extends TestCase {

	/**
	 * Wires the helpers this controller reaches for.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'wp_get_theme' )->justReturn(
			new class() {
				/**
				 * Returns a theme header.
				 *
				 * @param string $header Header to read.
				 * @return string
				 */
				public function get( string $header ): string {
					return 'Test Theme';
				}

				/**
				 * Returns the stylesheet directory name.
				 *
				 * @return string
				 */
				public function get_stylesheet(): string {
					return 'test-theme';
				}
			}
		);
	}

	/**
	 * Runs a check over the given blocks.
	 *
	 * @param array<int, array{id: string, html: string}> $blocks Blocks to check.
	 * @return array<string, mixed>
	 */
	private function check( array $blocks ): array {
		$request = new WP_REST_Request( 'POST', '/wsak/v1/check-blocks' );
		$request->set_param( 'blocks', $blocks );

		return ( new BlockCheckController() )->check( $request )->get_data();
	}

	/**
	 * A finding is reported against the block that produced it.
	 *
	 * @return void
	 */
	public function test_findings_are_attributed_to_their_own_block(): void {
		$data = $this->check(
			array(
				array(
					'id'   => 'clean',
					'html' => '<p>An ordinary paragraph.</p>',
				),
				array(
					'id'   => 'image',
					'html' => '<img src="/cat.jpg">',
				),
			)
		);

		$this->assertCount( 1, $data['blocks'], 'Only the block with a problem should appear.' );
		$this->assertSame( 'image', $data['blocks'][0]['id'] );
		$this->assertSame( 'img-alt-missing', $data['blocks'][0]['findings'][0]['rule_id'] );
	}

	/**
	 * Blocks with nothing wrong are left out entirely.
	 *
	 * @return void
	 */
	public function test_clean_blocks_are_omitted(): void {
		$data = $this->check(
			array(
				array(
					'id'   => 'a',
					'html' => '<p>Nothing wrong here.</p>',
				),
			)
		);

		$this->assertSame( array(), $data['blocks'] );
		$this->assertSame( 0, $data['total'] );
	}

	/**
	 * Every reply carries what this check could not see.
	 *
	 * The panel goes quiet when it finds nothing, and quiet is easily read as
	 * "your page is fine". It is not: it is "this checked what it can see from
	 * inside the editor".
	 *
	 * @return void
	 */
	public function test_every_reply_states_its_own_scope(): void {
		$clean = $this->check(
			array(
				array(
					'id'   => 'a',
					'html' => '<p>Fine.</p>',
				),
			)
		);
		$dirty = $this->check(
			array(
				array(
					'id'   => 'b',
					'html' => '<img src="/x.jpg">',
				),
			)
		);

		foreach ( array( $clean, $dirty ) as $data ) {
			$this->assertNotSame( '', trim( $data['scope'] ) );
			$this->assertStringContainsString( 'Colour', $data['scope'] );
		}
	}

	/**
	 * Findings carry the plain-language layer, not just the rule name.
	 *
	 * @return void
	 */
	public function test_findings_carry_their_consequence_and_band(): void {
		$finding = $this->check(
			array(
				array(
					'id'   => 'image',
					'html' => '<img src="/cat.jpg">',
				),
			)
		)['blocks'][0]['findings'][0];

		$this->assertNotSame( '', $finding['consequence'] );
		$this->assertStringNotContainsStringIgnoringCase( 'alt attribute', $finding['consequence'] );
		$this->assertSame( 'review', $finding['band'] );
		$this->assertNotSame( '', $finding['fix']['summary'] );
	}

	/**
	 * Blocks with no markup, or absurd amounts of it, are skipped.
	 *
	 * The request comes from a browser on every pause in typing. A block big
	 * enough to be somebody's whole imported page should not be parsed on each
	 * one.
	 *
	 * @return void
	 */
	public function test_empty_and_oversized_blocks_are_skipped(): void {
		$data = $this->check(
			array(
				array(
					'id'   => 'empty',
					'html' => '   ',
				),
				array(
					'id'   => 'huge',
					'html' => '<img src="/x.jpg">' . str_repeat( 'x', 70000 ),
				),
				array(
					'id'   => 'fine',
					'html' => '<img src="/y.jpg">',
				),
			)
		);

		$this->assertCount( 1, $data['blocks'] );
		$this->assertSame( 'fine', $data['blocks'][0]['id'] );
	}

	/**
	 * A block without an id is not reported under an empty one.
	 *
	 * @return void
	 */
	public function test_a_block_with_no_id_is_skipped(): void {
		$data = $this->check(
			array(
				array(
					'id'   => '',
					'html' => '<img src="/x.jpg">',
				),
			)
		);

		$this->assertSame( array(), $data['blocks'] );
	}
}
