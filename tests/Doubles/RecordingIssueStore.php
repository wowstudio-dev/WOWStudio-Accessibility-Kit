<?php
/**
 * An issue store that records what it was asked to change.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Doubles;

use WOWStudio\AccessibilityKit\Db\IssueRepository;
use WOWStudio\AccessibilityKit\Scanner\IssueStatus;

/**
 * Counts what a site-wide decision reached, without a database.
 */
final class RecordingIssueStore extends IssueRepository {

	/**
	 * Calls to apply_everywhere(), in order.
	 *
	 * @var array<int, array{fingerprint: string, status: string, note: string, user: int}>
	 */
	public array $applied = array();

	/**
	 * {@inheritDoc}
	 *
	 * @param string      $fingerprint Which finding.
	 * @param IssueStatus $status      What was decided.
	 * @param string      $note        Why.
	 * @param int         $user_id     Who decided.
	 * @return int
	 */
	public function apply_everywhere( string $fingerprint, IssueStatus $status, string $note, int $user_id ): int {
		$this->applied[] = array(
			'fingerprint' => $fingerprint,
			'status'      => $status->value,
			'note'        => $note,
			'user'        => $user_id,
		);

		return 8;
	}
}
