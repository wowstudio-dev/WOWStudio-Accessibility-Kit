<?php
/**
 * Daily alt-text generation allowance.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\AltText;

defined( 'ABSPATH' ) || exit;

/**
 * Counts generations against the daily cap.
 *
 * The cap separates the free tier from Pro, so it is enforced here, in the
 * generation path, rather than in the interface. A cap that only exists in the
 * UI is not a cap.
 *
 * @since 0.5.0
 */
final class UsageMeter {

	/**
	 * Option holding recent daily counts.
	 *
	 * @since 0.5.0
	 * @var string
	 */
	public const OPTION = 'wsak_alt_text_usage';

	/**
	 * Free allowance per day.
	 *
	 * @since 0.5.0
	 * @var int
	 */
	public const FREE_DAILY_CAP = 20;

	/**
	 * Days of history kept.
	 *
	 * @since 0.5.0
	 * @var int
	 */
	private const RETAIN_DAYS = 7;

	/**
	 * Returns today's cap.
	 *
	 * Pro is uncapped. The check is by capability rather than by plan name so a
	 * change of plan slug in Freemius cannot silently cap paying customers.
	 *
	 * @since 0.5.0
	 *
	 * @return int Zero or fewer means unlimited.
	 */
	public function cap(): int {
		if ( function_exists( 'wsak_can_use_premium_code' ) && wsak_can_use_premium_code() ) {
			return 0;
		}

		/**
		 * Filters the free daily alt-text allowance.
		 *
		 * @since 0.5.0
		 *
		 * @param int $cap Images per day. Zero or fewer means unlimited.
		 */
		return (int) apply_filters( 'wsak_alt_text_daily_cap', self::FREE_DAILY_CAP );
	}

	/**
	 * Reports whether the allowance is unlimited.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	public function is_unlimited(): bool {
		return $this->cap() <= 0;
	}

	/**
	 * Returns how many have been generated today.
	 *
	 * @since 0.5.0
	 *
	 * @return int
	 */
	public function used_today(): int {
		$usage = $this->usage();

		return (int) ( $usage[ $this->today() ] ?? 0 );
	}

	/**
	 * Returns how many remain today.
	 *
	 * @since 0.5.0
	 *
	 * @return int PHP_INT_MAX when unlimited.
	 */
	public function remaining(): int {
		if ( $this->is_unlimited() ) {
			return PHP_INT_MAX;
		}

		return max( 0, $this->cap() - $this->used_today() );
	}

	/**
	 * Reports whether another generation is allowed.
	 *
	 * @since 0.5.0
	 *
	 * @param int $count How many are about to run.
	 * @return bool
	 */
	public function allows( int $count = 1 ): bool {
		return $this->is_unlimited() || $this->remaining() >= $count;
	}

	/**
	 * Records successful generations.
	 *
	 * Only called after a provider actually answered. A failed call costs the
	 * user nothing and must not consume their allowance.
	 *
	 * @since 0.5.0
	 *
	 * @param int $count How many succeeded.
	 * @return void
	 */
	public function record( int $count = 1 ): void {
		if ( $count < 1 ) {
			return;
		}

		$usage           = $this->usage();
		$today           = $this->today();
		$usage[ $today ] = (int) ( $usage[ $today ] ?? 0 ) + $count;

		update_option( self::OPTION, $this->prune( $usage ), false );
	}

	/**
	 * Returns the stored counts.
	 *
	 * @since 0.5.0
	 *
	 * @return array<string, int>
	 */
	private function usage(): array {
		$usage = get_option( self::OPTION, array() );

		return is_array( $usage ) ? $usage : array();
	}

	/**
	 * Drops counts older than the retention window.
	 *
	 * @since 0.5.0
	 *
	 * @param array<string, int> $usage Stored counts.
	 * @return array<string, int>
	 */
	private function prune( array $usage ): array {
		$cutoff = gmdate( 'Y-m-d', time() - ( self::RETAIN_DAYS * DAY_IN_SECONDS ) );

		foreach ( array_keys( $usage ) as $day ) {
			if ( (string) $day < $cutoff ) {
				unset( $usage[ $day ] );
			}
		}

		return $usage;
	}

	/**
	 * Returns today's key.
	 *
	 * UTC, so the reset time does not move when a site changes timezone.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	private function today(): string {
		return gmdate( 'Y-m-d' );
	}
}
