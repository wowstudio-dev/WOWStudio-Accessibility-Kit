<?php
/**
 * Re-checking the site without being asked, and noticing what moved.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Monitoring;

use WOWStudio\AccessibilityKit\Core\Registrable;
use WOWStudio\AccessibilityKit\Jobs\BulkScan;
use WOWStudio\AccessibilityKit\Jobs\Queue;

defined( 'ABSPATH' ) || exit;

/**
 * Runs the scheduled scan, then says what changed.
 *
 * This is the half of "monitor" that the plugin header has been promising and
 * not delivering: until now every score in the interface was the result of
 * somebody remembering to press a button.
 *
 * ## Where the free and paid line falls, and why it falls there
 *
 * Everything here is free, including the schedule, the comparison, and the
 * record of what changed. Detecting that a page started failing something it
 * used to pass *is finding*, and finding is the one thing this product does not
 * gate — telling somebody a barrier appeared on their site only after they pay
 * would put the cost on their disabled visitors.
 *
 * What a paid add-on may sell is being **told without looking**: email digests,
 * Slack, webhooks, custom frequencies, crawling beyond WordPress content. That
 * is delivery rather than detection, it carries real marginal cost, and it is
 * the thing people actually renew for — a scheduled scan you must log in to
 * read is worth much less than a message saying the pricing page dropped twelve
 * points.
 *
 * `wsak_accessibility_changed` below is the seam that makes that possible
 * without this file knowing anything about it.
 *
 * @since 0.21.0
 */
final class Monitor implements Registrable {

	/**
	 * The Action Scheduler hook for a scheduled run.
	 *
	 * @since 0.21.0
	 * @var string
	 */
	public const HOOK = 'wsak_monitor_run';

	/**
	 * The Action Scheduler group, so these can be found and cancelled together.
	 *
	 * @since 0.21.0
	 * @var string
	 */
	public const GROUP = 'wsak-monitoring';

	/**
	 * The setting.
	 *
	 * @since 0.21.0
	 * @var Schedule
	 */
	private Schedule $schedule;

	/**
	 * The comparison engine.
	 *
	 * @since 0.21.0
	 * @var Comparison
	 */
	private Comparison $comparison;

	/**
	 * Constructor.
	 *
	 * @since 0.21.0
	 *
	 * @param Schedule|null   $schedule   The setting.
	 * @param Comparison|null $comparison The comparison engine.
	 */
	public function __construct( ?Schedule $schedule = null, ?Comparison $comparison = null ) {
		$this->schedule   = $schedule ?? new Schedule();
		$this->comparison = $comparison ?? new Comparison();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.21.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( 'wsak_bulk_scan_finished', array( $this, 'report_changes' ), 10, 1 );

		// Kept in step with the setting on every admin request rather than only
		// when the setting is saved. A scheduled action can vanish — a database
		// restored from backup, Action Scheduler's tables rebuilt, the plugin
		// deactivated and reactivated — and a monitoring feature that quietly
		// stopped monitoring is the worst way for this to fail.
		add_action( 'admin_init', array( $this, 'sync_schedule' ) );
		add_action( 'wsak_monitoring_frequency_changed', array( $this, 'sync_schedule' ) );
	}

	/**
	 * Makes the scheduled action match the stored setting.
	 *
	 * @since 0.21.0
	 *
	 * @return void
	 */
	public function sync_schedule(): void {
		if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		$scheduled = as_next_scheduled_action( self::HOOK, array(), self::GROUP );

		if ( ! $this->schedule->is_on() ) {
			if ( false !== $scheduled ) {
				as_unschedule_all_actions( self::HOOK, array(), self::GROUP );
			}

			return;
		}

		if ( false !== $scheduled ) {
			return;
		}

		as_schedule_recurring_action(
			time() + $this->schedule->interval(),
			$this->schedule->interval(),
			self::HOOK,
			array(),
			self::GROUP
		);
	}

	/**
	 * Starts a scheduled run over the most recently updated content.
	 *
	 * @since 0.21.0
	 *
	 * @return void
	 */
	public function run(): void {
		if ( ! $this->schedule->is_on() ) {
			return;
		}

		$post_ids = get_posts(
			array(
				'post_type'        => $this->post_types(),
				'post_status'      => 'publish',
				'numberposts'      => $this->schedule->page_limit(),
				'orderby'          => 'modified',
				'order'            => 'DESC',
				'fields'           => 'ids',
				'suppress_filters' => false,
			)
		);

		$post_ids = array_map( 'intval', (array) $post_ids );

		if ( array() === $post_ids ) {
			return;
		}

		/*
		 * Started with user 0. Nobody pressed this button, so attributing the
		 * run to whoever happened to be logged in when the cron fired would put
		 * a name against work they did not do.
		 */
		( new BulkScan() )->start( $post_ids, 0 );
	}

	/**
	 * Works out what moved, and tells anything listening.
	 *
	 * @since 0.21.0
	 *
	 * @param mixed $run_id The run that finished.
	 * @return void
	 */
	public function report_changes( $run_id = 0 ): void {
		$changes = $this->comparison->recent( 50 );

		$regressions = array_values(
			array_filter( $changes, static fn( Change $change ): bool => $change->regressed() )
		);

		/**
		 * Fires after a run finishes, with what changed since the scan before.
		 *
		 * This is the seam a paid add-on attaches its alerting to: everything
		 * needed to send a digest or a webhook is here, and nothing in the free
		 * plugin sends anything anywhere. Both arrays are Change objects.
		 *
		 * @since 0.21.0
		 *
		 * @param Change[] $regressions Pages that gained findings.
		 * @param Change[] $changes     Every page that moved, regressed or not.
		 * @param int      $run_id      The run that produced this, or 0.
		 */
		do_action( 'wsak_accessibility_changed', $regressions, $changes, (int) $run_id );
	}

	/**
	 * Returns the post types a scheduled run covers.
	 *
	 * @since 0.21.0
	 *
	 * @return string[]
	 */
	private function post_types(): array {
		$types = array_values( get_post_types( array( 'public' => true ), 'names' ) );
		$types = array_diff( $types, array( 'attachment' ) );

		/**
		 * Filters the post types a scheduled run covers.
		 *
		 * @since 0.21.0
		 *
		 * @param string[] $types Post type names.
		 */
		return (array) apply_filters( 'wsak_monitoring_post_types', array_values( $types ) );
	}

	/**
	 * Returns when the next scheduled run is due, or null.
	 *
	 * @since 0.21.0
	 *
	 * @return int|null Unix timestamp.
	 */
	public function next_run(): ?int {
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			return null;
		}

		$next = as_next_scheduled_action( self::HOOK, array(), self::GROUP );

		return is_int( $next ) ? $next : null;
	}

	/**
	 * Reports whether a scheduler is available to run this at all.
	 *
	 * @since 0.21.0
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return ( new Queue() )->is_available() && function_exists( 'as_schedule_recurring_action' );
	}
}
