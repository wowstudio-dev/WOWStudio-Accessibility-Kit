<?php
/**
 * A check that only a rendered page can answer.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

use WOWStudio\AccessibilityKit\Remediation\FixKind;
use WOWStudio\AccessibilityKit\Remediation\FixPlan;
use WOWStudio\AccessibilityKit\Remediation\FixTarget;

defined( 'ABSPATH' ) || exit;

/**
 * A check performed by the browser pass.
 *
 * Data rather than behaviour: the work happens in the browser, so there is
 * nothing for PHP to execute. What PHP still needs is the ability to describe
 * the check in the coverage panel, to recognise its ID when a finding arrives,
 * and to decide the severity and wording that finding is stored with.
 *
 * That last point is deliberate. The browser is an untrusted caller like any
 * other: it reports which check failed and where, and PHP decides what that
 * means. Severity, WCAG criterion and remediation wording are fixed here and
 * never taken from the payload, and a finding whose rule ID is not claimed by
 * any rule in BrowserRules is discarded rather than stored. So the browser pass
 * cannot introduce checks we never described, nor inflate the severity of one
 * we did.
 *
 * @since 0.10.0
 */
final class BrowserRule implements RuleDescriptor {

	/**
	 * Constructor.
	 *
	 * @since 0.10.0
	 *
	 * @param string       $id          Our stable rule identifier.
	 * @param string       $wcag_sc     WCAG success criterion.
	 * @param Severity     $severity    How much this fault hurts.
	 * @param Detection    $detection   Whether this settles the issue or flags it.
	 * @param string       $title       Short human-readable name.
	 * @param string       $description What fails, who it affects, how to fix it.
	 * @param FixPlan|null $fix_plan    What can be done about it.
	 * @param string       $consequence One sentence on who this shuts out.
	 */
	public function __construct(
		private readonly string $id,
		private readonly string $wcag_sc,
		private readonly Severity $severity,
		private readonly Detection $detection,
		private readonly string $title,
		private readonly string $description,
		private readonly ?FixPlan $fix_plan = null,
		private readonly string $consequence = ''
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.10.0
	 *
	 * @return string
	 */
	public function id(): string {
		return $this->id;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.10.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return $this->wcag_sc;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.10.0
	 *
	 * @return Severity
	 */
	public function severity(): Severity {
		return $this->severity;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.10.0
	 *
	 * @return Detection
	 */
	public function detection(): Detection {
		return $this->detection;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.10.0
	 *
	 * @return ScanPass
	 */
	public function pass(): ScanPass {
		return ScanPass::Browser;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.10.0
	 *
	 * @return string
	 */
	public function title(): string {
		return $this->title;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.10.0
	 *
	 * @return string
	 */
	public function description(): string {
		return $this->description;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.13.0
	 *
	 * @return string
	 */
	public function consequence(): string {
		return $this->consequence;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.13.0
	 *
	 * @return FixPlan
	 */
	public function fix_plan(): FixPlan {
		// A browser rule that was never given a plan is treated as needing a
		// person. Silence must never read as "we can fix this".
		return $this->fix_plan ?? FixPlan::manual( FixTarget::Theme );
	}
}
