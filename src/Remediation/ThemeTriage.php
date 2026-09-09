<?php
/**
 * Sorting theme findings by what would actually fix them.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Remediation;

use WOWStudio\AccessibilityKit\Db\Issue;
use WOWStudio\AccessibilityKit\Scanner\RuleDescriptor;

defined( 'ABSPATH' ) || exit;

/**
 * Works out, for one theme finding, what would actually put it right.
 *
 * Most accessibility faults on a real WordPress site are in the theme rather
 * than the content, and this plugin cannot edit a theme. Reporting them and
 * stopping there would be accurate and useless. So each one is sorted by what
 * the problem really is — decision F6.
 *
 * **A setting, not code.** A logo with no description, a menu item with a
 * useless label. The fix is a field on a screen the site owner already has, and
 * pointing them at it is the best outcome available: permanent, no code, and
 * done without us. This tier is only ever used where the finding can be
 * identified with certainty. A guess that sent somebody to the wrong screen
 * would be worse than saying nothing.
 *
 * **Somebody has to edit the theme.** Everything else. It gets the correction
 * written out, so what is handed over is a change rather than a complaint.
 *
 * The third tier F6 described — reaching theme markup through a core filter —
 * is deliberately absent, and that is a finding rather than an omission. The
 * filters that reach theme output divide into two kinds. Those that set an
 * attribute, like `wp_get_attachment_image_attributes`, are always better served
 * by fixing the underlying setting: the media library entry is a real repair
 * that outlives this plugin, and an attribute filter is an interception that
 * does not. Those that hand you a blob of markup, like `get_search_form` or
 * `wp_nav_menu_items`, can only be used by parsing and rewriting somebody's
 * HTML at request time — which is the pattern F6 refuses, in miniature, with
 * the same objections and a narrower blast radius. Neither is worth building.
 *
 * @since 0.14.0
 */
final class ThemeTriage {

	/**
	 * The fix is a field on a screen the site owner already has.
	 *
	 * @since 0.14.0
	 * @var string
	 */
	public const TIER_SETTING = 'setting';

	/**
	 * Somebody has to edit the theme.
	 *
	 * @since 0.14.0
	 * @var string
	 */
	public const TIER_HANDOFF = 'handoff';

	/**
	 * Sorts one finding.
	 *
	 * @since 0.14.0
	 *
	 * @param Issue               $issue The finding.
	 * @param RuleDescriptor|null $rule  The rule that produced it.
	 * @return array<string, mixed>
	 */
	public function triage( Issue $issue, ?RuleDescriptor $rule = null ): array {
		$setting = $this->as_setting( $issue );

		if ( null !== $setting ) {
			return $setting;
		}

		$snippet = $this->snippet_for( $issue );

		return array(
			'tier'        => self::TIER_HANDOFF,
			'label'       => __( 'Someone has to edit the theme', 'wowstudio-accessibility-kit' ),

			/*
			 * Two sentences, because only one of them is ever true. "The change
			 * below", said over nothing at all, is a promise the card does not
			 * keep, and the reader goes looking for a snippet that is not there.
			 */
			'instruction' => '' !== $snippet
				? __( 'No setting reaches this, and nothing here can change a theme file. Send the change below to whoever maintains your theme — or paste it into a child theme if you keep one.', 'wowstudio-accessibility-kit' )
				: __( 'No setting reaches this, and nothing here can change a theme file. What the correction is depends on the template it lives in, so this one goes to whoever maintains your theme.', 'wowstudio-accessibility-kit' ),
			'url'         => '',
			'snippet'     => $snippet,
			'guidance'    => '' === $snippet && null !== $rule ? $rule->description() : '',
		);
	}

	/**
	 * Identifies the findings that are settings rather than code.
	 *
	 * Certainty only. Each branch below rests on something WordPress itself put
	 * in the markup or the database, not on a class name that happened to look
	 * right — sending somebody to the wrong screen is worse than sending them
	 * nowhere.
	 *
	 * @since 0.14.0
	 *
	 * @param Issue $issue The finding.
	 * @return array<string, mixed>|null
	 */
	private function as_setting( Issue $issue ): ?array {
		$logo = $this->custom_logo_for( $issue );

		if ( null !== $logo ) {
			return array(
				'tier'        => self::TIER_SETTING,
				'label'       => __( 'This is a setting, not code', 'wowstudio-accessibility-kit' ),
				'instruction' => __( 'This is your site logo. Describe it in the media library and every page that shows it is fixed at once — including pages nothing here has scanned.', 'wowstudio-accessibility-kit' ),
				'url'         => (string) get_edit_post_link( $logo, 'raw' ),
				'snippet'     => '',
				'guidance'    => '',
			);
		}

		if ( $this->is_menu_item( $issue ) ) {
			// Menus live in two entirely different places depending on the
			// theme, and Appearance → Menus is not merely the wrong screen on a
			// block theme — it is frequently not registered at all. Sending
			// somebody to a page that 404s is worse than sending them nowhere.
			$block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();

			return array(
				'tier'        => self::TIER_SETTING,
				'label'       => __( 'This is a setting, not code', 'wowstudio-accessibility-kit' ),
				'instruction' => $block_theme
					? __( 'This is an item in one of your menus. Open the navigation block in the editor and give it a label — no code, and nothing here needs to stay installed for it to hold.', 'wowstudio-accessibility-kit' )
					: __( 'This is an item in one of your menus. Give it a label in Appearance → Menus and the problem is gone — no code, and nothing here needs to stay installed for it to hold.', 'wowstudio-accessibility-kit' ),
				'url'         => $block_theme ? admin_url( 'site-editor.php' ) : admin_url( 'nav-menus.php' ),
				'snippet'     => '',
				'guidance'    => '',
			);
		}

		return null;
	}

	/**
	 * Returns the custom logo's attachment ID when this finding is about it.
	 *
	 * WordPress stores which attachment is the logo, so this is a fact rather
	 * than a resemblance: the image in the finding either is that file or it is
	 * not.
	 *
	 * @since 0.14.0
	 *
	 * @param Issue $issue The finding.
	 * @return int|null
	 */
	private function custom_logo_for( Issue $issue ): ?int {
		if ( 'img-alt-missing' !== $issue->rule_id ) {
			return null;
		}

		$logo = (int) get_theme_mod( 'custom_logo' );

		if ( $logo <= 0 ) {
			return null;
		}

		$file = wp_basename( (string) get_attached_file( $logo ) );

		if ( '' === $file ) {
			return null;
		}

		// Compared on the file name rather than the whole URL, because the
		// markup may carry a resized variant, a CDN host, or a srcset entry.
		$stem = pathinfo( $file, PATHINFO_FILENAME );

		return str_contains( $issue->context, $stem ) ? $logo : null;
	}

	/**
	 * Reports whether the finding is inside a WordPress menu.
	 *
	 * `menu-item` is written by core's own walker, so its presence is a
	 * statement by WordPress about what this element is.
	 *
	 * @since 0.14.0
	 *
	 * @param Issue $issue The finding.
	 * @return bool
	 */
	private function is_menu_item( Issue $issue ): bool {
		return str_contains( $issue->context, 'menu-item' )
			|| str_contains( $issue->context, 'wp-block-navigation-item' );
	}

	/**
	 * Writes out the correction, so what is handed over is a change.
	 *
	 * Deliberately the smallest possible statement of what should be different.
	 * Guessing at surrounding template code would produce something that looks
	 * pasteable and is not, which wastes the time of whoever receives it.
	 *
	 * Code only. Where no correction is listed this used to fall through to the
	 * rule's description — a paragraph of English — which the panel then set in
	 * the dark monospaced block kept for markup, under a line telling the reader
	 * to send the change below to their developer. It was not a change, and the
	 * typeface made a claim about it that was not true. That text is worth
	 * showing; it goes out as `guidance` now and reads as the prose it is.
	 *
	 * @since 0.14.0
	 *
	 * @param Issue $issue The finding.
	 * @return string The correction, or '' when there is no general one.
	 */
	private function snippet_for( Issue $issue ): string {
		$corrections = array(
			'html-lang-missing'          => '<html <?php language_attributes(); ?>>',
			'document-title-missing'     => "add_theme_support( 'title-tag' ); // in the theme's after_setup_theme",
			'landmark-main-missing'      => '<main id="content">…</main>  <!-- wrap the page content -->',
			'img-alt-missing'            => '<img … alt="what somebody would miss if this did not load">',
			'link-name-missing'          => '<a href="…"><span class="screen-reader-text">Where this goes</span>…</a>',
			'button-name-missing'        => '<button aria-label="What this does">…</button>',
			'form-control-label-missing' => '<label for="field-id">What to type here</label>',
			'iframe-title-missing'       => '<iframe title="What is embedded here" …>',
			'table-headers-missing'      => '<th scope="col">Column heading</th>',
		);

		return $corrections[ $issue->rule_id ] ?? '';
	}
}
