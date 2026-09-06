<?php
/**
 * In-memory decision storage.
 *
 * @package WOWStudio\AccessibilityKit
 */

declare( strict_types = 1 );

namespace WOWStudio\AccessibilityKit\Tests\Doubles;

use WOWStudio\AccessibilityKit\Db\Decision;
use WOWStudio\AccessibilityKit\Db\DecisionRepository;
use WOWStudio\AccessibilityKit\Scanner\IssueStatus;

/**
 * Decisions held in an array, so the durable half of a dismissal can be
 * asserted on without a database.
 */
final class FakeDecisionStore extends DecisionRepository {

	/**
	 * Decisions, keyed "fingerprint:post_id".
	 *
	 * @var array<string, Decision>
	 */
	public array $records = array();

	/**
	 * {@inheritDoc}
	 *
	 * @param string      $fingerprint Which finding.
	 * @param int         $post_id     Page, 0 for site-wide.
	 * @param string      $rule_id     Rule that produced it.
	 * @param IssueStatus $status      What was decided.
	 * @param string      $note        Why.
	 * @param int         $user_id     Who decided.
	 * @return bool
	 */
	public function record( string $fingerprint, int $post_id, string $rule_id, IssueStatus $status, string $note, int $user_id ): bool {
		$this->records[ $fingerprint . ':' . $post_id ] = new Decision(
			count( $this->records ) + 1,
			$fingerprint,
			$post_id,
			$rule_id,
			$status,
			$note,
			$user_id,
			'2026-09-06 00:00:00',
			'2026-09-06 00:00:00'
		);

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $fingerprint Which finding.
	 * @param int    $post_id     Page, 0 for site-wide.
	 * @return bool
	 */
	public function withdraw( string $fingerprint, int $post_id ): bool {
		unset( $this->records[ $fingerprint . ':' . $post_id ] );

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $fingerprint Which finding.
	 * @param int    $post_id     Page, 0 for site-wide.
	 * @return Decision|null
	 */
	public function find( string $fingerprint, int $post_id ): ?Decision {
		return $this->records[ $fingerprint . ':' . $post_id ] ?? null;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string[] $fingerprints Findings to look up.
	 * @param int      $post_id      Page they were found on.
	 * @return array<string, Decision>
	 */
	public function for_fingerprints( array $fingerprints, int $post_id ): array {
		$found = array();

		foreach ( $fingerprints as $fingerprint ) {
			$decision = $this->find( $fingerprint, $post_id ) ?? $this->find( $fingerprint, 0 );

			if ( $decision instanceof Decision ) {
				$found[ $fingerprint ] = $decision;
			}
		}

		return $found;
	}
}
