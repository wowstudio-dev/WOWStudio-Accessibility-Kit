<?php
/**
 * Giving a field the name its placeholder already carries.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\SiteFixes\Fixes;

use WOWStudio\AccessibilityKit\SiteFixes\RunsInBrowser;

defined( 'ABSPATH' ) || exit;

/**
 * Promotes a field's placeholder to its accessible name, where it has none.
 *
 * A form field with no label is announced as "edit, blank". Very often the
 * author did write words for that field — they put them in the placeholder,
 * which looks like a label and is not one: it vanishes the moment somebody
 * starts typing, it is frequently too faint to read, and it is announced
 * inconsistently or not at all.
 *
 * So this invents nothing. It takes the words already written for that field
 * and makes them the field's name as well, which costs nothing visually and
 * turns "edit, blank" into "Your email address, edit".
 *
 * **What it deliberately will not do** is manufacture a label from a field's
 * `name` or `id`. That is where a fix of this kind usually goes wrong: it can
 * always produce something, so a field ends up announced as "user_email_2", the
 * finding disappears from the report, and the reader is no better off. A
 * plausible label is worse than a missing one, because it stops anybody looking
 * again. A field with no placeholder is left exactly as it is, and its finding
 * stays open until a person writes a real label.
 *
 * @since 0.20.0
 */
final class LabelFormFields implements RunsInBrowser {

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return string
	 */
	public function id(): string {
		return 'label-form-fields';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return string
	 */
	public function title(): string {
		return __( 'Name fields that only have placeholder text', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return string
	 */
	public function description(): string {
		return __( 'Where a form field has placeholder text but no label, this makes the placeholder the field\'s name for assistive technology as well. Nothing changes on screen. It never invents a name out of a field\'s internal id — a field with no placeholder is left alone and keeps its finding, because a plausible label is worse than a missing one.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return string[]
	 */
	public function rule_ids(): array {
		return array( 'form-control-label-missing' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return string
	 */
	public function caveat(): string {
		return __( 'Only fields that already have placeholder text are covered, so this closes some findings and deliberately leaves the rest. A placeholder acting as a label is itself a compromise; a real, visible label is better. Runs in the browser, so it does nothing with JavaScript off.', 'wowstudio-accessibility-kit' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.20.0
	 *
	 * @return void
	 */
	public function hooks(): void {
		// Carried out in the browser. The manager enqueues the one script that
		// runs every enabled fix of this kind, and tells it this one is on.
	}
}
