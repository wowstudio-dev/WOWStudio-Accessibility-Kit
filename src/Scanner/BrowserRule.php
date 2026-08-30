<?php
/**
 * A check that only a rendered page can answer.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner;

defined( 'ABSPATH' ) || exit;

/**
 * A check performed by the browser pass.
 *
 * Data rather than behaviour: the work happens in the browser, so there is
 * nothing for PHP to execute. What PHP still needs is the ability to describe
 * the check in the coverage panel, to recognise its ID when a finding arrives,
 * and to decide the severity and wording that finding is stored with.
 *
 * That last point is deliberate. Findings come back from axe, but axe's rule
 * IDs, severities and phrasing are not what we show anyone. Each browser check
 * is declared here with our own identifier, our own severity, and our own
 * plain-language description, and the incoming axe ID is mapped onto it. A
 * finding whose axe ID is not claimed by any rule here is discarded rather than
 * stored, so the browser pass cannot introduce checks we never described.
 *
 * @since 0.10.0
 */
final class BrowserRule implements RuleDescriptor {

	/**
	 * Constructor.
	 *
	 * @since 0.10.0
	 *
	 * @param string    $id          Our stable rule identifier.
	 * @param string    $axe_id      The axe-core rule ID this maps from.
	 * @param string    $wcag_sc     WCAG success criterion.
	 * @param Severity  $severity    Severity we assign, independent of axe's impact.
	 * @param Detection $detection   Whether this settles the issue or flags it.
	 * @param string    $title       Short human-readable name.
	 * @param string    $description What fails, who it affects, how to fix it.
	 */
	public function __construct(
		private readonly string $id,
		private readonly string $axe_id,
		private readonly string $wcag_sc,
		private readonly Severity $severity,
		private readonly Detection $detection,
		private readonly string $title,
		private readonly string $description
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
	 * Returns the axe-core rule ID this check maps from.
	 *
	 * @since 0.10.0
	 *
	 * @return string
	 */
	public function axe_id(): string {
		return $this->axe_id;
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
}
