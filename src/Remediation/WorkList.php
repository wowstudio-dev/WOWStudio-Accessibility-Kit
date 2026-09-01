<?php
/**
 * Turning a pile of findings into an order somebody can work through.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Remediation;

use WOWStudio\AccessibilityKit\Scanner\RuleDescriptor;
use WOWStudio\AccessibilityKit\Scanner\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * Orders findings by what they ask of the reader, then by how much they matter.
 *
 * The list used to come back sorted by severity, which is the right answer to a
 * question nobody was asking. Somebody facing ninety findings does not need them
 * ranked by seriousness; they need to know where to start. Sorted by severity,
 * a list opens with the hardest, most abstract problem on the page — usually one
 * in the theme that they cannot fix — and that is a list people close.
 *
 * So the first cut is by what the finding asks of them, and severity sorts
 * inside that, where it is genuinely the next question.
 *
 * @since 0.13.0
 */
final class WorkList {

	/**
	 * How the rules describe themselves.
	 *
	 * @since 0.13.0
	 * @var array<string, RuleDescriptor>
	 */
	private array $rules = array();

	/**
	 * Constructor.
	 *
	 * @since 0.13.0
	 *
	 * @param RuleDescriptor[] $rules Every check, both passes.
	 */
	public function __construct( array $rules ) {
		foreach ( $rules as $rule ) {
			if ( $rule instanceof RuleDescriptor ) {
				$this->rules[ $rule->id() ] = $rule;
			}
		}
	}

	/**
	 * Returns the band a finding belongs in.
	 *
	 * A finding naming a rule we do not recognise needs a person: it cannot be
	 * offered a fix, and it must not be filed as though it were unimportant.
	 *
	 * @since 0.13.0
	 *
	 * @param string $rule_id Rule the finding names.
	 * @return ActionBand
	 */
	public function band_for( string $rule_id ): ActionBand {
		$rule = $this->rules[ $rule_id ] ?? null;

		return null === $rule ? ActionBand::Decide : ActionBand::from_plan( $rule->fix_plan() );
	}

	/**
	 * Sorts findings into the order they should be worked through.
	 *
	 * Stable within its tiers: findings that tie on band, severity and rule keep
	 * the order they arrived in, so re-reading a list does not reshuffle it.
	 *
	 * @since 0.13.0
	 *
	 * @param array<int, array<string, mixed>> $issues Findings, as rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function order( array $issues ): array {
		$decorated = array();

		foreach ( array_values( $issues ) as $position => $issue ) {
			$rule_id = (string) ( $issue['rule_id'] ?? '' );

			$decorated[] = array(
				'band'     => $this->band_for( $rule_id )->rank(),
				'severity' => -$this->weight_of( $issue ),
				'rule'     => $rule_id,
				'position' => $position,
				'issue'    => $issue,
			);
		}

		usort(
			$decorated,
			static function ( array $a, array $b ): int {
				return array( $a['band'], $a['severity'], $a['rule'], $a['position'] )
					<=> array( $b['band'], $b['severity'], $b['rule'], $b['position'] );
			}
		);

		return array_map( static fn( array $row ): array => $row['issue'], $decorated );
	}

	/**
	 * Groups findings under the four headings, dropping bands with nothing in them.
	 *
	 * @since 0.13.0
	 *
	 * @param array<int, array<string, mixed>> $issues Findings, as rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function grouped( array $issues ): array {
		$buckets = array();

		foreach ( $this->order( $issues ) as $issue ) {
			$band = $this->band_for( (string) ( $issue['rule_id'] ?? '' ) );

			$buckets[ $band->value ][] = $issue;
		}

		$groups = array();

		foreach ( ActionBand::cases() as $band ) {
			if ( ! isset( $buckets[ $band->value ] ) ) {
				continue;
			}

			$groups[] = array(
				'band'   => $band->value,
				'label'  => $band->label(),
				'blurb'  => $band->blurb(),
				'count'  => count( $buckets[ $band->value ] ),
				'issues' => $buckets[ $band->value ],
			);
		}

		return $groups;
	}

	/**
	 * Counts findings per band, including the empty ones.
	 *
	 * Empty bands are kept here, unlike in grouped(), because a summary saying
	 * "nothing needs a decision from you" is worth reading and an absent line
	 * says nothing at all.
	 *
	 * @since 0.13.0
	 *
	 * @param array<int, array<string, mixed>> $issues Findings, as rows.
	 * @return array<string, int>
	 */
	public function tally( array $issues ): array {
		$tally = array();

		foreach ( ActionBand::cases() as $band ) {
			$tally[ $band->value ] = 0;
		}

		foreach ( $issues as $issue ) {
			++$tally[ $this->band_for( (string) ( $issue['rule_id'] ?? '' ) )->value ];
		}

		return $tally;
	}

	/**
	 * Reads a finding's severity weight.
	 *
	 * @since 0.13.0
	 *
	 * @param array<string, mixed> $issue Finding row.
	 * @return int
	 */
	private function weight_of( array $issue ): int {
		$severity = $issue['severity'] ?? '';

		if ( $severity instanceof Severity ) {
			return $severity->weight();
		}

		return ( Severity::tryFrom( (string) $severity ) ?? Severity::Minor )->weight();
	}
}
