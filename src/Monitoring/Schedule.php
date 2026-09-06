<?php
/**
 * How often the site re-checks itself.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Monitoring;

defined( 'ABSPATH' ) || exit;

/**
 * The monitoring setting, and nothing else.
 *
 * Kept apart from the thing that acts on it so that "what has the site owner
 * asked for" can be read and tested without a scheduler in the room.
 *
 * Off by default, and that is a deliberate choice rather than caution. Turning
 * a background job on for somebody who did not ask for one means their host
 * starts doing work they did not budget for, on a schedule they did not choose,
 * possibly on a plan billed by the CPU-second.
 *
 * @since 0.21.0
 */
final class Schedule {

	/**
	 * Where the setting is stored.
	 *
	 * @since 0.21.0
	 * @var string
	 */
	public const OPTION = 'wsak_monitoring';

	/**
	 * The frequencies on offer, and how many seconds each is.
	 *
	 * Weekly and monthly, and nothing more frequent. Daily was considered and
	 * left out: a full site scan is real work, most sites do not change daily,
	 * and a check that runs more often than the content changes produces a
	 * stream of "nothing happened" that people stop reading — which is the way
	 * a monitoring feature fails in practice.
	 *
	 * @since 0.21.0
	 * @var array<string, int>
	 */
	private const INTERVALS = array(
		'weekly'  => 7 * DAY_IN_SECONDS,
		'monthly' => 30 * DAY_IN_SECONDS,
	);

	/**
	 * Returns the chosen frequency, or 'off'.
	 *
	 * @since 0.21.0
	 *
	 * @return string
	 */
	public function frequency(): string {
		$stored = get_option( self::OPTION, array() );
		$value  = is_array( $stored ) ? (string) ( $stored['frequency'] ?? 'off' ) : 'off';

		return isset( self::INTERVALS[ $value ] ) ? $value : 'off';
	}

	/**
	 * Reports whether scheduled re-checking is switched on.
	 *
	 * @since 0.21.0
	 *
	 * @return bool
	 */
	public function is_on(): bool {
		return 'off' !== $this->frequency();
	}

	/**
	 * Returns the interval in seconds, or zero when off.
	 *
	 * @since 0.21.0
	 *
	 * @return int
	 */
	public function interval(): int {
		return self::INTERVALS[ $this->frequency() ] ?? 0;
	}

	/**
	 * Returns the frequencies a site may choose from.
	 *
	 * @since 0.21.0
	 *
	 * @return array<string, string>
	 */
	public static function choices(): array {
		return array(
			'off'     => __( 'Off', 'wowstudio-accessibility-kit' ),
			'weekly'  => __( 'Every week', 'wowstudio-accessibility-kit' ),
			'monthly' => __( 'Every month', 'wowstudio-accessibility-kit' ),
		);
	}

	/**
	 * Stores a frequency, rejecting anything not on offer.
	 *
	 * @since 0.21.0
	 *
	 * @param string $frequency One of the keys from choices().
	 * @return bool Whether the setting now says what was asked for.
	 */
	public function set( string $frequency ): bool {
		if ( 'off' !== $frequency && ! isset( self::INTERVALS[ $frequency ] ) ) {
			return false;
		}

		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$stored['frequency'] = $frequency;

		update_option( self::OPTION, $stored, true );

		/**
		 * Fires after the monitoring frequency changes.
		 *
		 * @since 0.21.0
		 *
		 * @param string $frequency The new frequency.
		 */
		do_action( 'wsak_monitoring_frequency_changed', $frequency );

		return $this->frequency() === $frequency;
	}

	/**
	 * Returns how many pages one scheduled run may cover.
	 *
	 * Capped, and the cap is the honest part of this feature. A background scan
	 * of a large site is a lot of work to start without being asked twice, so a
	 * run covers the most recently updated pages rather than everything, and
	 * the interface says so rather than implying whole-site coverage it does
	 * not have.
	 *
	 * @since 0.21.0
	 *
	 * @return int
	 */
	public function page_limit(): int {
		/**
		 * Filters how many pages a scheduled run covers.
		 *
		 * @since 0.21.0
		 *
		 * @param int $limit Maximum pages per run.
		 */
		return max( 1, (int) apply_filters( 'wsak_monitoring_page_limit', 50 ) );
	}
}
