<?php
/**
 * Data tables without header cells.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Scanner\Rules;

use DOMElement;
use WOWStudio\AccessibilityKit\Scanner\Detection;
use WOWStudio\AccessibilityKit\Scanner\Document;
use WOWStudio\AccessibilityKit\Scanner\Finding;
use WOWStudio\AccessibilityKit\Scanner\Rule;
use WOWStudio\AccessibilityKit\Scanner\Severity;

defined( 'ABSPATH' ) || exit;

/**
 * Flags tables that contain no header cells.
 *
 * Reported as needing review, not settled automatically. A table with no th
 * elements is either a data table missing its headers, which is a real problem,
 * or a layout table, which is dated but not a failure. Nothing in the markup
 * reliably tells the two apart.
 *
 * @since 0.3.0
 */
final class TableHeadersMissing implements Rule {

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'table-headers-missing';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function wcag_sc(): string {
		return '1.3.1';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return Severity
	 */
	public function severity(): Severity {
		return Severity::Serious;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return Detection
	 */
	public function detection(): Detection {
		return Detection::Manual;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Table has no header cells', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'If this is a data table, someone using a screen reader cannot tell which column or row a cell belongs to, so the numbers lose their meaning. Mark the header cells up as th with a scope attribute. If the table is only being used for layout, it needs no headers and you can mark this as reviewed.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.3.0
	 *
	 * @param Document $document Parsed page.
	 * @return Finding[]
	 */
	public function evaluate( Document $document ): array {
		$findings = array();

		foreach ( $document->find( '//table' ) as $table ) {
			if ( ! $table instanceof DOMElement || $document->is_hidden( $table ) ) {
				continue;
			}

			if ( $document->find( './/th', $table )->length > 0 ) {
				continue;
			}

			if ( $document->find( './/*[@role="columnheader" or @role="rowheader"]', $table )->length > 0 ) {
				continue;
			}

			$findings[] = new Finding(
				$this->id(),
				$this->wcag_sc(),
				$this->severity(),
				$this->detection(),
				__( 'This table has no header cells. If it presents data rather than layout, its headers need marking up.', 'wowstudio-accessibility-kit' ),
				$document->selector_for( $table ),
				$document->context_for( $table )
			);
		}

		return $findings;
	}
}
