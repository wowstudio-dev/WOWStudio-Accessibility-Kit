<?php
/**
 * Accessibility state, in the lists people already look at.
 *
 * @package WOWStudio\AccessibilityKit
 */

namespace WOWStudio\AccessibilityKit\Admin;

use WOWStudio\AccessibilityKit\Core\Registrable;
use WOWStudio\AccessibilityKit\Db\Scan;
use WOWStudio\AccessibilityKit\Db\ScanRepository;
use WOWStudio\AccessibilityKit\Support\Capabilities;
use WOWStudio\AccessibilityKit\Support\ScannableTypes;

defined( 'ABSPATH' ) || exit;

/**
 * Adds an accessibility column to the Posts and Pages list tables.
 *
 * The dashboard is where somebody goes when they have decided to think about
 * accessibility. This is for the other ninety per cent of the time: the Pages
 * screen is where people already are, several times a day, and a column there
 * puts the state of a page in front of them without their having asked.
 *
 * Kept to a score and a count. The temptation is to say more — severities, a
 * breakdown, a little chart — and the reason not to is that this is somebody
 * else's screen. It has a job that is not ours, and a plugin that colonises it
 * gets switched off.
 *
 * "Not checked" is shown as exactly that, never as a zero or a dash. A page
 * nobody has scanned and a page with no findings are completely different
 * statements, and a column that renders them alike would be this plugin's own
 * version of the fault it reports on other people's sites.
 *
 * @since 0.22.0
 */
final class ContentColumns implements Registrable {

	/**
	 * The column key.
	 *
	 * @since 0.22.0
	 * @var string
	 */
	private const COLUMN = 'wsak_accessibility';

	/**
	 * Scans for everything on the current screen, fetched once.
	 *
	 * @since 0.22.0
	 * @var array<int, Scan>|null
	 */
	private ?array $scans = null;

	/**
	 * Scans.
	 *
	 * @since 0.22.0
	 * @var ScanRepository
	 */
	private ScanRepository $repository;

	/**
	 * Constructor.
	 *
	 * @since 0.22.0
	 *
	 * @param ScanRepository|null $repository Scans.
	 */
	public function __construct( ?ScanRepository $repository = null ) {
		$this->repository = $repository ?? new ScanRepository();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 0.22.0
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'attach' ) );
	}

	/**
	 * Attaches the column to every post type worth showing it on.
	 *
	 * @since 0.22.0
	 *
	 * @return void
	 */
	public function attach(): void {
		if ( ! current_user_can( Capabilities::VIEW_REPORTS ) ) {
			return;
		}

		foreach ( $this->post_types() as $type ) {
			add_filter( "manage_{$type}_posts_columns", array( $this, 'add_column' ) );
			add_action( "manage_{$type}_posts_custom_column", array( $this, 'render' ), 10, 2 );
		}

		// One query for the whole screen, run once the list is known and before
		// any row is rendered. A filter, not an action: the_posts has to hand
		// the posts back, and add_action would discard the return value.
		add_filter( 'the_posts', array( $this, 'prime' ), 10, 1 );

		add_action( 'admin_head-edit.php', array( $this, 'print_styles' ) );
	}

	/**
	 * Prints the few rules the column needs.
	 *
	 * Inline and only on the list screen. Enqueuing a stylesheet would be a
	 * whole extra request for under three hundred bytes, on a screen this
	 * plugin is a guest on.
	 *
	 * The colours are the same values the plugin's own palette uses, so they
	 * are already covered by the contrast guard rather than being a second set
	 * nobody checks.
	 *
	 * @since 0.22.0
	 *
	 * @return void
	 */
	public function print_styles(): void {
		echo '<style>
			.wsak-col { display: inline-flex; gap: 6px; align-items: baseline; }
			.wsak-col__count { color: #565471; font-size: 12px; }
			.wsak-col--high strong { color: #15803d; }
			.wsak-col--mid strong { color: #b54708; }
			.wsak-col--low strong { color: #b42318; }
			.wsak-col--none, .wsak-col--unknown { color: #565471; }
		</style>' . "\n";
	}

	/**
	 * Adds the column header.
	 *
	 * @since 0.22.0
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public function add_column( $columns ): array {
		$columns = (array) $columns;

		// Before the date column, which is conventionally last, so this does
		// not push the thing people scan for off the end of the row.
		$date = $columns['date'] ?? null;

		unset( $columns['date'] );

		$columns[ self::COLUMN ] = __( 'Accessibility', 'wowstudio-accessibility-kit' );

		if ( null !== $date ) {
			$columns['date'] = $date;
		}

		return $columns;
	}

	/**
	 * Fetches every scan the current screen will need, in one query.
	 *
	 * @since 0.22.0
	 *
	 * @param mixed $posts The posts the query found.
	 * @return mixed The posts, untouched.
	 */
	public function prime( $posts ) {
		if ( ! is_array( $posts ) || array() === $posts || null !== $this->scans ) {
			return $posts;
		}

		$ids = array();

		foreach ( $posts as $post ) {
			if ( isset( $post->ID ) ) {
				$ids[] = (int) $post->ID;
			}
		}

		$this->scans = $this->repository->latest_for_posts( $ids );

		return $posts;
	}

	/**
	 * Renders one cell.
	 *
	 * @since 0.22.0
	 *
	 * @param string $column  Column being rendered.
	 * @param int    $post_id The row's post.
	 * @return void
	 */
	public function render( $column, $post_id ): void {
		if ( self::COLUMN !== $column ) {
			return;
		}

		$post_id = (int) $post_id;
		$scan    = $this->scans[ $post_id ] ?? null;

		if ( ! $scan instanceof Scan ) {
			printf(
				'<span class="wsak-col wsak-col--none">%s</span>',
				esc_html__( 'Not checked', 'wowstudio-accessibility-kit' )
			);

			return;
		}

		$open = (int) ( $scan->summary['total'] ?? 0 );

		printf(
			'<span class="wsak-col wsak-col--%1$s"><strong>%2$s</strong> <span class="wsak-col__count">%3$s</span></span>',
			esc_attr( $this->band( $scan->score ) ),
			null === $scan->score
				? esc_html__( 'Checked', 'wowstudio-accessibility-kit' )
				: esc_html( sprintf( '%d/100', $scan->score ) ),
			esc_html(
				sprintf(
					/* translators: %d: number of findings. */
					_n( '%d finding', '%d findings', $open, 'wowstudio-accessibility-kit' ),
					$open
				)
			)
		);
	}

	/**
	 * Returns a coarse band for the score, for colour only.
	 *
	 * Three bands rather than a gradient, and no wording attached to them. The
	 * colour is a hint about where to look first; calling a page "good" on the
	 * strength of automated checks that cover part of WCAG would be the kind of
	 * verdict this plugin does not issue.
	 *
	 * @since 0.22.0
	 *
	 * @param int|null $score The score.
	 * @return string
	 */
	private function band( ?int $score ): string {
		if ( null === $score ) {
			return 'unknown';
		}

		if ( $score >= 90 ) {
			return 'high';
		}

		return $score >= 60 ? 'mid' : 'low';
	}

	/**
	 * Returns the post types the column appears on.
	 *
	 * @since 0.22.0
	 *
	 * @return string[]
	 */
	private function post_types(): array {
		/*
		 * The types the plugin actually scans. A column reading "not checked"
		 * on a list of things this plugin will not check is a column that
		 * reports its own absence as a fault on somebody else's screen.
		 */
		$types = ScannableTypes::names();

		/**
		 * Filters which post types get the accessibility column.
		 *
		 * Narrows within what is scanned. Adding a type here that
		 * `wsak_post_types` does not cover gets a column that can only ever say
		 * "not checked".
		 *
		 * @since 0.22.0
		 *
		 * @param string[] $types Post type names.
		 */
		return (array) apply_filters( 'wsak_column_post_types', $types );
	}
}
