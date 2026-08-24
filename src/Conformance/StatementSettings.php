<?php
/**
 * Accessibility statement settings.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Conformance;

use WOWStudio\AccessibilityKit\Core\Installer;

defined( 'ABSPATH' ) || exit;

/**
 * Stores what the site owner has told us about their own accessibility work.
 *
 * The attestation fields are the important ones. Until a named person has
 * confirmed that they have read the statement and stand behind it, everything
 * generated from these settings is watermarked as a draft — because until then
 * it is a document a plugin wrote, not a statement anybody made.
 *
 * @since 0.7.0
 */
final class StatementSettings {

	/**
	 * Returns the settings, with defaults filled in.
	 *
	 * @since 0.7.0
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( Installer::SETTINGS_OPTION, array() );
		$saved  = is_array( $stored ) && isset( $stored['statement'] ) && is_array( $stored['statement'] )
			? $stored['statement']
			: array();

		return array(
			'organisation'      => isset( $saved['organisation'] ) && '' !== $saved['organisation']
				? (string) $saved['organisation']
				: (string) get_bloginfo( 'name' ),
			'standard'          => isset( $saved['standard'] ) ? (string) $saved['standard'] : 'wcag22aa',
			'status'            => isset( $saved['status'] ) ? (string) $saved['status'] : ConformanceStatus::Partial->value,
			'known_limitations' => isset( $saved['known_limitations'] ) ? (string) $saved['known_limitations'] : '',
			'feedback_email'    => isset( $saved['feedback_email'] ) ? (string) $saved['feedback_email'] : (string) get_option( 'admin_email' ),
			'feedback_phone'    => isset( $saved['feedback_phone'] ) ? (string) $saved['feedback_phone'] : '',
			'feedback_url'      => isset( $saved['feedback_url'] ) ? (string) $saved['feedback_url'] : '',
			'response_days'     => isset( $saved['response_days'] ) ? max( 1, (int) $saved['response_days'] ) : 5,
			'enforcement_body'  => isset( $saved['enforcement_body'] ) ? (string) $saved['enforcement_body'] : '',
			'enforcement_url'   => isset( $saved['enforcement_url'] ) ? (string) $saved['enforcement_url'] : '',
			'assessment'        => isset( $saved['assessment'] ) ? (string) $saved['assessment'] : 'self',
			'reviewed_on'       => isset( $saved['reviewed_on'] ) ? (string) $saved['reviewed_on'] : '',
			'attested'          => ! empty( $saved['attested'] ),
			'attested_by'       => isset( $saved['attested_by'] ) ? (string) $saved['attested_by'] : '',
			'attested_role'     => isset( $saved['attested_role'] ) ? (string) $saved['attested_role'] : '',
			'attested_at'       => isset( $saved['attested_at'] ) ? (string) $saved['attested_at'] : '',
		);
	}

	/**
	 * Saves changes over the current settings.
	 *
	 * Attestation is deliberately not a field a caller can simply set to true
	 * alongside everything else. It is granted by attest() and withdrawn by any
	 * edit, so a statement cannot quietly stay "approved" while its content
	 * changes underneath the person who approved it.
	 *
	 * @since 0.7.0
	 *
	 * @param array<string, mixed> $changes Values to change.
	 * @return array<string, mixed> The settings as they now stand.
	 */
	public function update( array $changes ): array {
		$current = $this->all();
		$before  = $current;

		$text = array( 'organisation', 'standard', 'status', 'feedback_phone', 'enforcement_body', 'assessment', 'reviewed_on' );

		foreach ( $text as $field ) {
			if ( array_key_exists( $field, $changes ) ) {
				$current[ $field ] = sanitize_text_field( (string) $changes[ $field ] );
			}
		}

		if ( array_key_exists( 'known_limitations', $changes ) ) {
			$current['known_limitations'] = sanitize_textarea_field( (string) $changes['known_limitations'] );
		}

		if ( array_key_exists( 'feedback_email', $changes ) ) {
			$current['feedback_email'] = sanitize_email( (string) $changes['feedback_email'] );
		}

		foreach ( array( 'feedback_url', 'enforcement_url' ) as $field ) {
			if ( array_key_exists( $field, $changes ) ) {
				$current[ $field ] = esc_url_raw( (string) $changes[ $field ] );
			}
		}

		if ( array_key_exists( 'response_days', $changes ) ) {
			$current['response_days'] = max( 1, (int) $changes['response_days'] );
		}

		if ( $this->materially_changed( $before, $current ) ) {
			$current['attested']      = false;
			$current['attested_by']   = '';
			$current['attested_role'] = '';
			$current['attested_at']   = '';
		}

		return $this->write( $current );
	}

	/**
	 * Records that a named person stands behind the statement.
	 *
	 * @since 0.7.0
	 *
	 * @param string $name Person attesting.
	 * @param string $role Their role.
	 * @return array<string, mixed> The settings as they now stand.
	 */
	public function attest( string $name, string $role ): array {
		$current = $this->all();

		$current['attested']      = true;
		$current['attested_by']   = sanitize_text_field( $name );
		$current['attested_role'] = sanitize_text_field( $role );
		$current['attested_at']   = gmdate( 'Y-m-d' );

		return $this->write( $current );
	}

	/**
	 * Withdraws the attestation.
	 *
	 * @since 0.7.0
	 *
	 * @return array<string, mixed> The settings as they now stand.
	 */
	public function withdraw(): array {
		$current = $this->all();

		$current['attested']      = false;
		$current['attested_by']   = '';
		$current['attested_role'] = '';
		$current['attested_at']   = '';

		return $this->write( $current );
	}

	/**
	 * Reports whether an edit changed anything a reader would notice.
	 *
	 * @since 0.7.0
	 *
	 * @param array<string, mixed> $before Settings as they were.
	 * @param array<string, mixed> $after  Settings as they now are.
	 * @return bool
	 */
	private function materially_changed( array $before, array $after ): bool {
		$watched = array(
			'organisation',
			'standard',
			'status',
			'known_limitations',
			'feedback_email',
			'feedback_phone',
			'feedback_url',
			'response_days',
			'enforcement_body',
			'enforcement_url',
			'assessment',
		);

		foreach ( $watched as $field ) {
			if ( ( $before[ $field ] ?? null ) !== ( $after[ $field ] ?? null ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Persists the settings.
	 *
	 * @since 0.7.0
	 *
	 * @param array<string, mixed> $statement Settings to write.
	 * @return array<string, mixed>
	 */
	private function write( array $statement ): array {
		$settings = get_option( Installer::SETTINGS_OPTION, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$settings['statement'] = $statement;

		update_option( Installer::SETTINGS_OPTION, $settings, false );

		return $statement;
	}
}
