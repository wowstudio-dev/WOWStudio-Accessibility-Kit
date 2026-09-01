<?php
/**
 * An in-memory stand-in for scan storage.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Doubles;

use WOWStudio\AccessibilityKit\Db\Scan;
use WOWStudio\AccessibilityKit\Db\ScanRepository;
use WOWStudio\AccessibilityKit\Scanner\BrowserPassStatus;
use WOWStudio\AccessibilityKit\Scanner\ScanScope;
use WOWStudio\AccessibilityKit\Scanner\ScanStatus;

/**
 * Scan rows in an array, with the transitions a run depends on.
 *
 * `claim()` is conditional here exactly as it is in SQL, because "the second
 * delivery of the same job does nothing" is the property being tested and a
 * double that claimed unconditionally would pass a test the real code fails.
 */
final class FakeScanStore extends ScanRepository {

	/**
	 * Rows, keyed by id.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $rows = array();

	/**
	 * Next id to hand out.
	 *
	 * @var int
	 */
	private int $next = 1;

	/**
	 * {@inheritDoc}
	 *
	 * @param ScanScope $scope     Scope.
	 * @param int       $target_id Target.
	 * @param int       $user_id   User.
	 * @return int
	 */
	public function start( ScanScope $scope, int $target_id = 0, int $user_id = 0 ): int {
		return $this->insert( $scope, $target_id, 0, ScanStatus::Running );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param ScanScope $scope     Scope.
	 * @param int       $target_id Target.
	 * @param int       $user_id   User.
	 * @param int       $parent_id Run.
	 * @return int
	 */
	public function queue( ScanScope $scope, int $target_id, int $user_id, int $parent_id ): int {
		return $this->insert( $scope, $target_id, $parent_id, ScanStatus::Queued );
	}

	/**
	 * Adds a row.
	 *
	 * @param ScanScope  $scope     Scope.
	 * @param int        $target_id Target.
	 * @param int        $parent_id Run.
	 * @param ScanStatus $status    Starting status.
	 * @return int
	 */
	private function insert( ScanScope $scope, int $target_id, int $parent_id, ScanStatus $status ): int {
		$id = $this->next;
		++$this->next;

		$this->rows[ $id ] = array(
			'id'        => $id,
			'scope'     => $scope->value,
			'target_id' => $target_id,
			'parent_id' => $parent_id,
			'status'    => $status->value,
			'reason'    => '',
		);

		return $id;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $scan_id Scan.
	 * @return bool
	 */
	public function claim( int $scan_id ): bool {
		if ( ( $this->rows[ $scan_id ]['status'] ?? '' ) !== ScanStatus::Queued->value ) {
			return false;
		}

		$this->rows[ $scan_id ]['status'] = ScanStatus::Running->value;

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int                  $scan_id      Scan.
	 * @param int|null             $score        Score.
	 * @param array<string, mixed> $summary      Summary.
	 * @param BrowserPassStatus    $browser_pass Browser pass.
	 * @return bool
	 */
	public function complete( int $scan_id, ?int $score, array $summary = array(), BrowserPassStatus $browser_pass = BrowserPassStatus::Skipped ): bool {
		$this->rows[ $scan_id ]['status'] = ScanStatus::Complete->value;

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int    $scan_id Scan.
	 * @param string $reason  Why.
	 * @return bool
	 */
	public function fail( int $scan_id, string $reason = '' ): bool {
		$this->rows[ $scan_id ]['status'] = ScanStatus::Failed->value;
		$this->rows[ $scan_id ]['reason'] = $reason;

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int        $scan_id Scan.
	 * @param ScanStatus $status  Status.
	 * @return bool
	 */
	public function set_status( int $scan_id, ScanStatus $status ): bool {
		$this->rows[ $scan_id ]['status'] = $status->value;

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $parent_id Run.
	 * @return int
	 */
	public function cancel_queued( int $parent_id ): int {
		$stopped = 0;

		foreach ( $this->rows as $id => $row ) {
			if ( $row['parent_id'] === $parent_id && ScanStatus::Queued->value === $row['status'] ) {
				$this->rows[ $id ]['status'] = ScanStatus::Cancelled->value;
				++$stopped;
			}
		}

		return $stopped;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $parent_id Run.
	 * @return array<string, int>
	 */
	public function count_by_status( int $parent_id ): array {
		$counts = array();

		foreach ( $this->rows as $row ) {
			if ( $row['parent_id'] !== $parent_id ) {
				continue;
			}

			$counts[ $row['status'] ] = ( $counts[ $row['status'] ] ?? 0 ) + 1;
		}

		return $counts;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $id Scan.
	 * @return Scan|null
	 */
	public function find( int $id ): ?Scan {
		if ( ! isset( $this->rows[ $id ] ) ) {
			return null;
		}

		$row = $this->rows[ $id ];

		return new Scan(
			$row['id'],
			ScanScope::from( $row['scope'] ),
			$row['target_id'],
			$row['parent_id'],
			ScanStatus::from( $row['status'] ),
			null,
			BrowserPassStatus::Skipped,
			array(),
			'2026-09-01 00:00:00',
			null,
			0
		);
	}
}
