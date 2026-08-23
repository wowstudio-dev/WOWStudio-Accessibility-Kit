<?php
/**
 * Daily allowance tests.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use WOWStudio\AccessibilityKit\AltText\UsageMeter;
use WOWStudio\AccessibilityKit\Tests\TestCase;

/**
 * Tests the free-tier cap, which is the Free/Pro boundary.
 *
 * @covers \WOWStudio\AccessibilityKit\AltText\UsageMeter
 */
final class UsageMeterTest extends TestCase {

	/**
	 * Stand-in for the options table.
	 *
	 * @var array<string, mixed>
	 */
	private array $options = array();

	/**
	 * Wires the option functions to memory.
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
	}

	/**
	 * A fresh site gets the documented free allowance.
	 *
	 * @return void
	 */
	public function test_free_sites_get_the_documented_cap(): void {
		$meter = new UsageMeter();

		$this->assertSame( 20, $meter->cap() );
		$this->assertSame( 20, UsageMeter::FREE_DAILY_CAP );
		$this->assertSame( 20, $meter->remaining() );
		$this->assertFalse( $meter->is_unlimited() );
	}

	/**
	 * Recording reduces what remains.
	 *
	 * @return void
	 */
	public function test_recording_reduces_the_remainder(): void {
		$meter = new UsageMeter();
		$meter->record( 3 );

		$this->assertSame( 3, $meter->used_today() );
		$this->assertSame( 17, $meter->remaining() );
	}

	/**
	 * The cap actually stops further generation.
	 *
	 * @return void
	 */
	public function test_the_cap_blocks_further_generation(): void {
		$meter = new UsageMeter();
		$meter->record( 20 );

		$this->assertSame( 0, $meter->remaining() );
		$this->assertFalse( $meter->allows( 1 ) );
	}

	/**
	 * A batch larger than what remains is refused as a whole.
	 *
	 * @return void
	 */
	public function test_a_batch_beyond_the_remainder_is_refused(): void {
		$meter = new UsageMeter();
		$meter->record( 18 );

		$this->assertTrue( $meter->allows( 2 ) );
		$this->assertFalse( $meter->allows( 3 ) );
	}

	/**
	 * Recording nothing is a no-op, so a failed call costs no allowance.
	 *
	 * @return void
	 */
	public function test_recording_zero_costs_nothing(): void {
		$meter = new UsageMeter();
		$meter->record( 0 );
		$meter->record( -5 );

		$this->assertSame( 0, $meter->used_today() );
	}

	/**
	 * A site can retune the cap through the documented filter.
	 *
	 * @return void
	 */
	public function test_the_cap_is_filterable(): void {
		Filters\expectApplied( 'wsak_alt_text_daily_cap' )->andReturn( 5 );

		$this->assertSame( 5, ( new UsageMeter() )->cap() );
	}

	/**
	 * A cap of zero or less means unlimited.
	 *
	 * @return void
	 */
	public function test_a_zero_cap_means_unlimited(): void {
		Filters\expectApplied( 'wsak_alt_text_daily_cap' )->andReturn( 0 );

		$meter = new UsageMeter();

		$this->assertTrue( $meter->is_unlimited() );
		$this->assertTrue( $meter->allows( 1000 ) );
	}

	/**
	 * Yesterday's usage does not count against today.
	 *
	 * @return void
	 */
	public function test_yesterdays_usage_does_not_count_today(): void {
		$this->options[ UsageMeter::OPTION ] = array(
			gmdate( 'Y-m-d', time() - DAY_IN_SECONDS ) => 20,
		);

		$meter = new UsageMeter();

		$this->assertSame( 0, $meter->used_today() );
		$this->assertTrue( $meter->allows( 1 ) );
	}
}
