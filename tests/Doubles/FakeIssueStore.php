<?php
/**
 * An in-memory stand-in for issue storage.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Doubles;

use WOWStudio\AccessibilityKit\Db\Issue;
use WOWStudio\AccessibilityKit\Db\IssueRepository;
use WOWStudio\AccessibilityKit\Scanner\Detection;
use WOWStudio\AccessibilityKit\Scanner\IssueStatus;
use WOWStudio\AccessibilityKit\Scanner\ScanPass;
use WOWStudio\AccessibilityKit\Scanner\Severity;

/**
 * One finding, held in properties, with the transitions review depends on.
 */
final class FakeIssueStore extends IssueRepository {

	/**
	 * Whether find() should come up empty.
	 *
	 * @var bool
	 */
	public bool $missing = false;

	/**
	 * Current status.
	 *
	 * @var IssueStatus
	 */
	public IssueStatus $status = IssueStatus::Open;

	/**
	 * Current note.
	 *
	 * @var string
	 */
	public string $note = '';

	/**
	 * Who last decided.
	 *
	 * @var int
	 */
	public int $resolved_by = 0;

	/**
	 * {@inheritDoc}
	 *
	 * @param int $id Issue.
	 * @return Issue|null
	 */
	public function find( int $id ): ?Issue {
		if ( $this->missing ) {
			return null;
		}

		return new Issue(
			$id,
			1,
			42,
			'table-headers-missing',
			'1.3.1',
			Severity::Serious,
			Detection::Manual,
			ScanPass::Server,
			$this->status,
			'/html/body/table',
			'<table></table>',
			'This table has no th elements.',
			$this->note,
			$this->resolved_by,
			'2026-09-01 00:00:00',
			'2026-09-01 00:00:00'
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int         $id      Issue.
	 * @param IssueStatus $status  New status.
	 * @param string      $note    Reason.
	 * @param int         $user_id Who decided.
	 * @return bool
	 */
	public function set_status( int $id, IssueStatus $status, string $note = '', int $user_id = 0 ): bool {
		$this->status      = $status;
		$this->note        = $note;
		$this->resolved_by = $user_id;

		return true;
	}
}
