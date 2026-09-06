<?php
/**
 * Switching site-wide fixes on and off, and running the ones that are on.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\SiteFixes;

use WOWStudio\AccessibilityKit\Core\Registrable;

defined( 'ABSPATH' ) || exit;

/**
 * Owns which fixes are enabled, and registers the hooks for those that are.
 *
 * One option holds the whole set rather than one option per fix. That is not
 * only tidiness: this is read on every front-end request, and a single
 * autoloaded array is one lookup where fourteen options would be fourteen.
 *
 * @since 0.19.0
 */
final class SiteFixManager implements Registrable {

	/**
	 * Where the enabled set is stored.
	 *
	 * @since 0.19.0
	 * @var string
	 */
	public const OPTION = 'wsak_site_fixes';

	/**
	 * The available fixes.
	 *
	 * @since 0.19.0
	 * @var SiteFixes
	 */
	private SiteFixes $fixes;

	/**
	 * Constructor.
	 *
	 * @since 0.19.0
	 *
	 * @param SiteFixes|null $fixes Registry to use.
	 */
	public function __construct( ?SiteFixes $fixes = null ) {
		$this->fixes = $fixes ?? SiteFixes::with_defaults();
	}

	/**
	 * Registers the enabled fixes.
	 *
	 * On `after_setup_theme`, not `init`, and the reason is one specific fix:
	 * `add_theme_support( 'title-tag' )` refuses once `wp_loaded` has fired, and
	 * core's own title renderer returns early unless the support was declared.
	 * Registering at `init` would be too late for it and for anything else that
	 * has to speak during theme setup. Priority 5 runs before a theme's own
	 * setup callback at the default 10, so a theme that declares support for
	 * itself still wins the argument by agreeing with us.
	 *
	 * @since 0.19.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'after_setup_theme', array( $this, 'apply_enabled' ), 5 );
		add_action( 'wp_head', array( $this, 'print_css' ), 8 );
	}

	/**
	 * Hooks up every fix that is switched on.
	 *
	 * @since 0.19.0
	 *
	 * @return void
	 */
	public function apply_enabled(): void {
		foreach ( $this->enabled() as $fix ) {
			$fix->hooks();
		}
	}

	/**
	 * Prints the combined stylesheet for the enabled CSS fixes.
	 *
	 * One inline style element rather than an enqueued file, because the whole
	 * of it is a few hundred bytes and a separate request would cost more than
	 * the rules do. Printed early in the head so that a theme's own stylesheet,
	 * which loads after, can still win — these are meant to supply what is
	 * missing, not to overrule a decision somebody made on purpose.
	 *
	 * @since 0.19.0
	 *
	 * @return void
	 */
	public function print_css(): void {
		$css = array();

		foreach ( $this->enabled() as $fix ) {
			if ( $fix instanceof ProvidesCss ) {
				$rules = trim( $fix->css() );

				if ( '' !== $rules ) {
					$css[] = $rules;
				}
			}
		}

		if ( array() === $css ) {
			return;
		}

		printf(
			"<style id=\"wsak-site-fixes\">\n%s\n</style>\n",
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Not HTML. See self::safe_css(), which is the correct escaping for this context and explains why esc_html() is not.
			self::safe_css( implode( "\n\n", $css ) )
		);
	}

	/**
	 * Makes a block of CSS safe to print inside a style element.
	 *
	 * Deliberately *not* `esc_html()`, and this is worth writing down because
	 * the mistake looks like carefulness and shipped once already. A `<style>`
	 * element is raw text: HTML entities inside it are never decoded, so
	 * `esc_html()` does not escape the CSS, it corrupts it. `p > a` becomes
	 * `p &gt; a`, which is not a selector, and every rule using a child
	 * combinator silently stops matching — a switch that appears to work and
	 * does nothing at all.
	 *
	 * What actually needs guarding is the one string that can end a raw text
	 * element early. CSS has no legitimate use for `</`, so removing it closes
	 * the injection route without touching a single valid rule. Nothing here
	 * comes from user input in the first place; this exists so that a fix
	 * registered through the `wsak_site_fixes` filter by somebody else cannot
	 * break out of the element.
	 *
	 * @since 0.19.0
	 *
	 * @param string $css The composed stylesheet.
	 * @return string
	 */
	private static function safe_css( string $css ): string {
		return str_replace( '</', '', $css );
	}

	/**
	 * Returns the ids of the fixes that are switched on.
	 *
	 * @since 0.19.0
	 *
	 * @return string[]
	 */
	public function enabled_ids(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? array_values( array_filter( array_map( 'strval', $stored ) ) ) : array();

		/**
		 * Filters which site fixes are switched on.
		 *
		 * Lets a site force a fix on or off in code — useful in a version
		 * controlled deployment where the database is not the source of truth.
		 *
		 * @since 0.19.0
		 *
		 * @param string[] $stored Enabled fix identifiers.
		 */
		return (array) apply_filters( 'wsak_enabled_site_fixes', $stored );
	}

	/**
	 * Returns the enabled fixes themselves, skipping ids nothing answers to.
	 *
	 * @since 0.19.0
	 *
	 * @return SiteFix[]
	 */
	public function enabled(): array {
		$enabled = array();

		foreach ( $this->enabled_ids() as $id ) {
			$fix = $this->fixes->get( $id );

			// An id left behind by an add-on that has since been deactivated
			// is ignored rather than fatal. The stored value is not rewritten:
			// reactivating the add-on should bring its fix back on.
			if ( $fix instanceof SiteFix ) {
				$enabled[] = $fix;
			}
		}

		return $enabled;
	}

	/**
	 * Reports whether one fix is switched on.
	 *
	 * @since 0.19.0
	 *
	 * @param string $id Fix identifier.
	 * @return bool
	 */
	public function is_enabled( string $id ): bool {
		return in_array( $id, $this->enabled_ids(), true );
	}

	/**
	 * Switches a fix on or off.
	 *
	 * @since 0.19.0
	 *
	 * @param string $id      Fix identifier.
	 * @param bool   $enabled Whether it should be on.
	 * @return bool Whether the fix is now effectively in the requested state.
	 *              Read back through the filter rather than from the option, so
	 *              a site forcing a fix on or off in code reports what is
	 *              actually true rather than what was just written.
	 */
	public function set( string $id, bool $enabled ): bool {
		if ( null === $this->fixes->get( $id ) ) {
			return false;
		}

		$current = $this->enabled_ids();
		$next    = array_values( array_diff( $current, array( $id ) ) );

		if ( $enabled ) {
			$next[] = $id;
		}

		sort( $next );

		update_option( self::OPTION, $next, true );

		/**
		 * Fires after a site-wide fix is switched on or off.
		 *
		 * @since 0.19.0
		 *
		 * @param string $id      Fix identifier.
		 * @param bool   $enabled Whether it is now on.
		 */
		do_action( 'wsak_site_fix_toggled', $id, $enabled );

		return $this->is_enabled( $id ) === $enabled;
	}

	/**
	 * Describes every fix and its state, for the settings screen.
	 *
	 * @since 0.19.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function to_array(): array {
		$rows = array();

		foreach ( $this->fixes->all() as $fix ) {
			$rows[] = array(
				'id'          => $fix->id(),
				'title'       => $fix->title(),
				'description' => $fix->description(),
				'caveat'      => $fix->caveat(),
				'rules'       => $fix->rule_ids(),
				'enabled'     => $this->is_enabled( $fix->id() ),
				'is_css'      => $fix instanceof ProvidesCss,
			);
		}

		return $rows;
	}

	/**
	 * Returns the registry, so callers can look a fix up by rule.
	 *
	 * @since 0.19.0
	 *
	 * @return SiteFixes
	 */
	public function registry(): SiteFixes {
		return $this->fixes;
	}
}
