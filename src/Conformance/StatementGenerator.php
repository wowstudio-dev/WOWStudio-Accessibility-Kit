<?php
/**
 * Accessibility statement rendering.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Conformance;

defined( 'ABSPATH' ) || exit;

/**
 * Turns the stored settings into a publishable accessibility statement.
 *
 * Two rules shape everything here.
 *
 * First, every claim in the output is attributed to the organisation, never to
 * the plugin. This plugin cannot verify that a site meets any standard, and the
 * statement says so in its own closing section rather than leaving a reader to
 * assume otherwise.
 *
 * Second, an unattested statement renders as a draft, prominently and
 * unmissably. A document nobody has read and stood behind is not a statement,
 * and publishing one as though it were would be exactly the sort of unearned
 * assurance this plugin exists to avoid.
 *
 * @since 0.7.0
 */
final class StatementGenerator {

	/**
	 * Statement settings.
	 *
	 * @since 0.7.0
	 * @var StatementSettings
	 */
	private StatementSettings $settings;

	/**
	 * How far to push every heading below its natural level.
	 *
	 * Zero when the statement is published on its own page, where its title is
	 * a second-level heading. The admin preview renders the same document
	 * nested inside a panel that already has its own headings, and a preview
	 * whose headings sat at the panel's level would put two "Accessibility
	 * statement" headings in the outline and leave the preview's sections
	 * looking like siblings of the panel's own. Pushing them down keeps the
	 * outline honest about what is a preview and what is the screen around it.
	 *
	 * @since 0.8.0
	 * @var int
	 */
	private int $offset = 0;

	/**
	 * Constructor.
	 *
	 * @since 0.7.0
	 *
	 * @param StatementSettings|null $settings Statement settings.
	 */
	public function __construct( ?StatementSettings $settings = null ) {
		$this->settings = $settings ?? new StatementSettings();
	}

	/**
	 * Returns the target standard's readable name.
	 *
	 * @since 0.7.0
	 *
	 * @param string $key Stored standard key.
	 * @return string
	 */
	public static function standard_label( string $key ): string {
		$standards = array(
			'wcag22aa' => __( 'WCAG 2.2 level AA', 'wowstudio-accessibility-kit' ),
			'wcag21aa' => __( 'WCAG 2.1 level AA', 'wowstudio-accessibility-kit' ),
		);

		return $standards[ $key ] ?? $standards['wcag22aa'];
	}

	/**
	 * Reports whether the statement has been attested.
	 *
	 * @since 0.7.0
	 *
	 * @return bool
	 */
	public function is_attested(): bool {
		return (bool) $this->settings->all()['attested'];
	}

	/**
	 * Lists what still has to be filled in before this is publishable.
	 *
	 * @since 0.7.0
	 *
	 * @return string[]
	 */
	public function missing(): array {
		$settings = $this->settings->all();
		$missing  = array();

		if ( '' === trim( (string) $settings['organisation'] ) ) {
			$missing[] = __( 'Who this statement is from.', 'wowstudio-accessibility-kit' );
		}

		if ( '' === trim( (string) $settings['feedback_email'] )
			&& '' === trim( (string) $settings['feedback_url'] )
			&& '' === trim( (string) $settings['feedback_phone'] ) ) {
			$missing[] = __( 'A way for people to report a problem. A statement without one is of little use to the person who has just hit a barrier, and European rules expect it.', 'wowstudio-accessibility-kit' );
		}

		if ( ConformanceStatus::Partial->value === $settings['status']
			&& '' === trim( (string) $settings['known_limitations'] ) ) {
			$missing[] = __( 'What the known problems are. Saying the site is partially conformant without saying which parts is not much of an answer.', 'wowstudio-accessibility-kit' );
		}

		return $missing;
	}

	/**
	 * Renders the statement as HTML.
	 *
	 * @since 0.7.0
	 *
	 * @param int $heading_offset Levels to push every heading down by, for
	 *                            rendering the statement inside a screen that
	 *                            already has headings of its own. Clamped to
	 *                            0-3 so the output can never exceed h6.
	 * @return string
	 */
	public function render( int $heading_offset = 0 ): string {
		$this->offset = max( 0, min( 3, $heading_offset ) );

		$settings     = $this->settings->all();
		$organisation = (string) $settings['organisation'];
		$standard     = self::standard_label( (string) $settings['standard'] );
		$status       = ConformanceStatus::tryFrom( (string) $settings['status'] ) ?? ConformanceStatus::Partial;

		$html = '<div class="wsak-statement">';

		if ( empty( $settings['attested'] ) ) {
			$html .= $this->draft_banner();
		}

		$html .= $this->heading( 2, __( 'Accessibility statement', 'wowstudio-accessibility-kit' ) );

		$html .= '<p>' . esc_html(
			sprintf(
				/* translators: %s: organisation name. */
				__( '%s wants as many people as possible to use this website. We are working to make it easier for everyone, including disabled people.', 'wowstudio-accessibility-kit' ),
				$organisation
			)
		) . '</p>';

		$html .= $this->heading( 3, __( 'How accessible this website is', 'wowstudio-accessibility-kit' ) );
		$html .= '<p>' . esc_html( $status->sentence( $organisation, $standard ) ) . '</p>';

		$limitations = trim( (string) $settings['known_limitations'] );

		if ( '' !== $limitations ) {
			$html .= $this->heading( 3, __( 'Known problems', 'wowstudio-accessibility-kit' ) );
			$html .= '<ul>';

			foreach ( $this->as_lines( $limitations ) as $line ) {
				$html .= '<li>' . esc_html( $line ) . '</li>';
			}

			$html .= '</ul>';
		}

		$html .= $this->feedback_section( $settings );
		$html .= $this->enforcement_section( $settings );
		$html .= $this->preparation_section( $settings );

		$html .= '</div>';

		/**
		 * Filters the rendered accessibility statement.
		 *
		 * @since 0.7.0
		 *
		 * @param string               $html     Rendered statement.
		 * @param array<string, mixed> $settings Statement settings.
		 */
		return (string) apply_filters( 'wsak_statement_html', $html, $settings );
	}

	/**
	 * Renders the draft banner.
	 *
	 * @since 0.7.0
	 *
	 * @return string
	 */
	private function draft_banner(): string {
		return '<div class="wsak-statement__draft" role="note">'
			. '<p><strong>' . esc_html__( 'Draft — not yet reviewed or approved', 'wowstudio-accessibility-kit' ) . '</strong></p>'
			. '<p>' . esc_html__( 'This statement was made from settings, and nobody has confirmed that it is accurate. Do not treat it as a statement from this organisation yet. Somebody has to read it and sign it off in the Accessibility settings first.', 'wowstudio-accessibility-kit' ) . '</p>'
			. '</div>';
	}

	/**
	 * Renders the feedback mechanism.
	 *
	 * European rules expect a route for reporting barriers, and it is the part
	 * a person in difficulty actually needs, so it is never omitted.
	 *
	 * @since 0.7.0
	 *
	 * @param array<string, mixed> $settings Statement settings.
	 * @return string
	 */
	private function feedback_section( array $settings ): string {
		$html = $this->heading( 3, __( 'Tell us about a problem', 'wowstudio-accessibility-kit' ) );

		$html .= '<p>' . esc_html__( 'Tell us if you find something on this site you cannot use. Tell us too if you need information in another format. We want to know what happened.', 'wowstudio-accessibility-kit' ) . '</p>';

		$routes = array();

		if ( '' !== trim( (string) $settings['feedback_email'] ) ) {
			$email    = (string) $settings['feedback_email'];
			$routes[] = sprintf(
				'<li>%s <a href="%s">%s</a></li>',
				esc_html__( 'Email:', 'wowstudio-accessibility-kit' ),
				esc_url( 'mailto:' . $email ),
				esc_html( $email )
			);
		}

		if ( '' !== trim( (string) $settings['feedback_phone'] ) ) {
			$routes[] = sprintf(
				'<li>%s %s</li>',
				esc_html__( 'Phone:', 'wowstudio-accessibility-kit' ),
				esc_html( (string) $settings['feedback_phone'] )
			);
		}

		if ( '' !== trim( (string) $settings['feedback_url'] ) ) {
			$routes[] = sprintf(
				'<li><a href="%s">%s</a></li>',
				esc_url( (string) $settings['feedback_url'] ),
				esc_html__( 'Use our contact form', 'wowstudio-accessibility-kit' )
			);
		}

		if ( array() === $routes ) {
			return $html . '<p><em>' . esc_html__( 'No contact route has been set yet.', 'wowstudio-accessibility-kit' ) . '</em></p>';
		}

		$html .= '<ul>' . implode( '', $routes ) . '</ul>';

		$days = (int) $settings['response_days'];

		$html .= '<p>' . esc_html(
			sprintf(
				/* translators: %d: number of working days. */
				_n( 'We aim to reply within %d working day.', 'We aim to reply within %d working days.', $days, 'wowstudio-accessibility-kit' ),
				$days
			)
		) . '</p>';

		return $html;
	}

	/**
	 * Renders the escalation route, when one has been given.
	 *
	 * @since 0.7.0
	 *
	 * @param array<string, mixed> $settings Statement settings.
	 * @return string
	 */
	private function enforcement_section( array $settings ): string {
		$body = trim( (string) $settings['enforcement_body'] );

		if ( '' === $body ) {
			return '';
		}

		$html = $this->heading( 3, __( 'If you are not happy with our response', 'wowstudio-accessibility-kit' ) );

		$url = trim( (string) $settings['enforcement_url'] );

		if ( '' !== $url ) {
			$html .= '<p>' . sprintf(
				/* translators: %s: name of the enforcement body, linked when a URL was given. */
				esc_html__( 'You can contact %s.', 'wowstudio-accessibility-kit' ),
				sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html( $body ) )
			) . '</p>';

			return $html;
		}

		return $html . '<p>' . esc_html(
			sprintf(
				/* translators: %s: name of the enforcement body, linked when a URL was given. */
				__( 'You can contact %s.', 'wowstudio-accessibility-kit' ),
				$body
			)
		) . '</p>';
	}

	/**
	 * Renders how and when the statement was prepared.
	 *
	 * The closing paragraph is the honest one, and it is not optional. A reader
	 * deciding how much weight to give this document is entitled to know that
	 * part of the assessment was automated and that automated testing sees only
	 * part of the picture.
	 *
	 * @since 0.7.0
	 *
	 * @param array<string, mixed> $settings Statement settings.
	 * @return string
	 */
	private function preparation_section( array $settings ): string {
		$html = $this->heading( 3, __( 'How we prepared this statement', 'wowstudio-accessibility-kit' ) );

		$reviewed = trim( (string) $settings['reviewed_on'] );

		if ( '' !== $reviewed ) {
			$html .= '<p>' . esc_html(
				sprintf(
					/* translators: %s: date the statement was last reviewed. */
					__( 'This statement was last reviewed on %s.', 'wowstudio-accessibility-kit' ),
					$this->readable_date( $reviewed )
				)
			) . '</p>';
		}

		$html .= '<p>' . esc_html(
			'external' === $settings['assessment']
				? __( 'The website was assessed by an external organisation, alongside automated testing.', 'wowstudio-accessibility-kit' )
				: __( 'The website was assessed by us, using automated testing alongside our own checks.', 'wowstudio-accessibility-kit' )
		) . '</p>';

		$html .= '<p>' . esc_html__( 'Automated testing finds only some accessibility problems. Some things need a person to judge. Is the alternative text accurate? Does the focus order make sense? Does the page read sensibly aloud? Where a person has not checked, this site has not been fully assessed.', 'wowstudio-accessibility-kit' ) . '</p>';

		if ( ! empty( $settings['attested'] ) ) {
			$by   = (string) $settings['attested_by'];
			$role = (string) $settings['attested_role'];
			$when = $this->readable_date( (string) $settings['attested_at'] );

			$html .= '<p>' . esc_html(
				'' !== $role
					? sprintf(
						/* translators: 1: name, 2: role, 3: date. */
						__( 'Reviewed and approved by %1$s, %2$s, on %3$s.', 'wowstudio-accessibility-kit' ),
						$by,
						$role,
						$when
					)
					: sprintf(
						/* translators: 1: name, 2: date. */
						__( 'Reviewed and approved by %1$s on %2$s.', 'wowstudio-accessibility-kit' ),
						$by,
						$when
					)
			) . '</p>';
		}

		return $html;
	}

	/**
	 * Renders one heading at its natural level, pushed down by the offset.
	 *
	 * @since 0.8.0
	 *
	 * @param int    $level Natural heading level, as published on its own page.
	 * @param string $text  Already-translated heading text.
	 * @return string
	 */
	private function heading( int $level, string $text ): string {
		$level = min( 6, $level + $this->offset );

		return sprintf( '<h%1$d>%2$s</h%1$d>', $level, esc_html( $text ) );
	}

	/**
	 * Splits a textarea into trimmed lines.
	 *
	 * @since 0.7.0
	 *
	 * @param string $text Raw text.
	 * @return string[]
	 */
	private function as_lines( string $text ): array {
		$lines = preg_split( '/\R+/u', $text );

		if ( ! is_array( $lines ) ) {
			return array();
		}

		return array_values( array_filter( array_map( 'trim', $lines ), static fn( string $line ): bool => '' !== $line ) );
	}

	/**
	 * Formats a stored date for the reader's locale.
	 *
	 * @since 0.7.0
	 *
	 * @param string $date Date in Y-m-d.
	 * @return string
	 */
	private function readable_date( string $date ): string {
		$timestamp = strtotime( $date );

		if ( false === $timestamp ) {
			return $date;
		}

		return wp_date( (string) get_option( 'date_format' ), $timestamp );
	}
}
