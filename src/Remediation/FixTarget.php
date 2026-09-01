<?php
/**
 * Where a fix would be written.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Remediation;

defined( 'ABSPATH' ) || exit;

/**
 * The place a fix has to land, which decides whether we can make it at all.
 *
 * Kept separate from FixKind on purpose, because the two were being conflated
 * and the conflation flattered the plan. Knowing the right answer and being
 * able to apply it are different questions, and a rule can easily have the
 * first without the second — a missing `lang` attribute has exactly one correct
 * value and lives in a theme file no filter reaches.
 *
 * A one-click fix requires a favourable answer to both.
 *
 * @since 0.13.0
 */
enum FixTarget: string {

	/**
	 * The post's own content, through the reversible override layer.
	 *
	 * @since 0.13.0
	 */
	case Content = 'content';

	/**
	 * The site's own Additional CSS, under Appearance → Customise.
	 *
	 * @since 0.13.0
	 */
	case Css = 'css';

	/**
	 * The media library, which is a real repair rather than an interception:
	 * it applies wherever that image is used and outlives this plugin.
	 *
	 * @since 0.13.0
	 */
	case Media = 'media';

	/**
	 * A WordPress setting this plugin can change on the site's behalf.
	 *
	 * @since 0.13.0
	 */
	case Setting = 'setting';

	/**
	 * The theme's own files, which nothing here reaches.
	 *
	 * Not a gap to be closed later. Reaching arbitrary theme markup would mean
	 * rewriting the page as it is served, which is refused outright — see
	 * decision F6. These are handed to whoever maintains the theme.
	 *
	 * @since 0.13.0
	 */
	case Theme = 'theme';

	/**
	 * Nowhere. There is no fix to write.
	 *
	 * @since 0.13.0
	 */
	case None = 'none';

	/**
	 * Returns the translated label.
	 *
	 * @since 0.13.0
	 *
	 * @return string
	 */
	public function label(): string {
		return match ( $this ) {
			self::Content => __( 'Your page content', 'wowstudio-accessibility-kit' ),
			self::Css     => __( 'Your site’s Additional CSS', 'wowstudio-accessibility-kit' ),
			self::Media   => __( 'Your media library', 'wowstudio-accessibility-kit' ),
			self::Setting => __( 'A WordPress setting', 'wowstudio-accessibility-kit' ),
			self::Theme   => __( 'Your theme', 'wowstudio-accessibility-kit' ),
			self::None    => __( 'Nowhere', 'wowstudio-accessibility-kit' ),
		};
	}

	/**
	 * Whether this plugin can write here at all.
	 *
	 * @since 0.13.0
	 *
	 * @return bool
	 */
	public function is_reachable(): bool {
		return match ( $this ) {
			self::Content, self::Css, self::Media, self::Setting => true,
			self::Theme, self::None => false,
		};
	}
}
