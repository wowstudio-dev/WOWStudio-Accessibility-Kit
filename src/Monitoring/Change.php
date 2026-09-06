<?php
/**
 * What changed on one page between two scans.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Monitoring;

defined( 'ABSPATH' ) || exit;

/**
 * One page's accessibility, before and after.
 *
 * Deliberately a record of *what moved*, not a verdict on the page. A score that
 * fell is worth surfacing; a score that fell is not the same as a site that got
 * worse, because the checks that produced it cover part of WCAG and the page may
 * simply have gained content that is honestly harder.
 *
 * `regressed` is therefore a narrow claim: findings this page did not have
 * before, that it has now. That much is a fact about the two scans rather than
 * an interpretation of them.
 *
 * @since 0.21.0
 */
final class Change {

	/**
	 * Constructor.
	 *
	 * @since 0.21.0
	 *
	 * @param int                $post_id      The page.
	 * @param string             $title        Its title when the comparison ran.
	 * @param int                $before_scan  Scan the page was in before.
	 * @param int                $after_scan   Scan it is in now.
	 * @param int|null           $before_score Score then, if one was recorded.
	 * @param int|null           $after_score  Score now.
	 * @param array<string, int> $appeared     Rule ids that gained findings, and how many.
	 * @param array<string, int> $resolved     Rule ids that lost findings, and how many.
	 * @param string             $compared_at  When the later scan ran.
	 */
	public function __construct(
		public readonly int $post_id,
		public readonly string $title,
		public readonly int $before_scan,
		public readonly int $after_scan,
		public readonly ?int $before_score,
		public readonly ?int $after_score,
		public readonly array $appeared,
		public readonly array $resolved,
		public readonly string $compared_at
	) {}

	/**
	 * Returns the score movement, or null when either score is unknown.
	 *
	 * @since 0.21.0
	 *
	 * @return int|null
	 */
	public function delta(): ?int {
		if ( null === $this->before_score || null === $this->after_score ) {
			return null;
		}

		return $this->after_score - $this->before_score;
	}

	/**
	 * Reports whether this page gained findings it did not have before.
	 *
	 * The narrow claim, and the one worth alerting on. A score that moved
	 * without any new finding usually means the page changed length rather
	 * than changed quality.
	 *
	 * @since 0.21.0
	 *
	 * @return bool
	 */
	public function regressed(): bool {
		return array() !== $this->appeared;
	}

	/**
	 * Reports whether anything moved at all.
	 *
	 * @since 0.21.0
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return array() === $this->appeared && array() === $this->resolved && 0 === ( $this->delta() ?? 0 );
	}

	/**
	 * Describes the change for the interface.
	 *
	 * @since 0.21.0
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'post_id'      => $this->post_id,
			'title'        => $this->title,
			'before_scan'  => $this->before_scan,
			'after_scan'   => $this->after_scan,
			'before_score' => $this->before_score,
			'after_score'  => $this->after_score,
			'delta'        => $this->delta(),
			'appeared'     => $this->appeared,
			'resolved'     => $this->resolved,
			'regressed'    => $this->regressed(),
			'compared_at'  => $this->compared_at,
		);
	}
}
