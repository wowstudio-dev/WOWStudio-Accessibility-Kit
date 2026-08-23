<?php
/**
 * A stored override.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Remediation;

defined( 'ABSPATH' ) || exit;

/**
 * One row of the fixes table.
 *
 * @since 0.6.0
 */
final class Fix {

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param int       $id            Row ID.
	 * @param int       $issue_id      Issue this resolves.
	 * @param int       $post_id       Post the override applies to.
	 * @param string    $rule_id       Rule that raised the issue.
	 * @param string    $engine        Which AI path produced it.
	 * @param string    $provider      Provider used.
	 * @param string    $model         Model used.
	 * @param string    $before_markup Markup as it stands.
	 * @param string    $after_markup  Markup to substitute.
	 * @param FixStatus $status        Whether it is in force.
	 * @param string    $applied_at    When it was applied.
	 * @param int       $applied_by    Who applied it.
	 * @param string    $reverted_at   When it was undone, if it was.
	 */
	public function __construct(
		public readonly int $id,
		public readonly int $issue_id,
		public readonly int $post_id,
		public readonly string $rule_id,
		public readonly string $engine,
		public readonly string $provider,
		public readonly string $model,
		public readonly string $before_markup,
		public readonly string $after_markup,
		public readonly FixStatus $status,
		public readonly string $applied_at,
		public readonly int $applied_by,
		public readonly string $reverted_at = ''
	) {}

	/**
	 * Builds a fix from a database row.
	 *
	 * @since 0.6.0
	 *
	 * @param object $row Row from $wpdb.
	 * @return self
	 */
	public static function from_row( object $row ): self {
		return new self(
			(int) $row->id,
			(int) $row->issue_id,
			(int) $row->post_id,
			(string) $row->rule_id,
			(string) $row->engine,
			(string) $row->provider,
			(string) $row->model,
			(string) $row->before_markup,
			(string) $row->after_markup,
			FixStatus::tryFrom( (string) $row->status ) ?? FixStatus::Reverted,
			(string) $row->applied_at,
			(int) $row->applied_by,
			null === $row->reverted_at ? '' : (string) $row->reverted_at
		);
	}

	/**
	 * Shapes the fix for a REST response.
	 *
	 * @since 0.6.0
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'           => $this->id,
			'issue_id'     => $this->issue_id,
			'post_id'      => $this->post_id,
			'rule_id'      => $this->rule_id,
			'engine'       => $this->engine,
			'provider'     => $this->provider,
			'model'        => $this->model,
			'before'       => $this->before_markup,
			'after'        => $this->after_markup,
			'status'       => $this->status->value,
			'status_label' => $this->status->label(),
			'applied_at'   => $this->applied_at,
			'reverted_at'  => $this->reverted_at,
		);
	}
}
