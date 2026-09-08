/**
 * The accessibility dashboard.
 */

import { Button, Notice } from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { fetchScan, readableError, runScan } from './api';
import StatementSettings from './components/statement-settings';
import CoveragePanel from './components/coverage-panel';
import Inspector from './components/inspector';
import IssueDrilldown from './components/issue-drilldown';
import IssueList from './components/issue-list';
import Overview from './components/overview';
import AltTextEditor from './components/alt-text-editor';
import SiteFixes from './components/site-fixes';
import Dismissed from './components/dismissed';
import BulkScan from './components/bulk-scan';
import ThemePanel from './components/theme-panel';
import ScanPicker from './components/scan-picker';
import ScoreCard from './components/score-card';
import { ErrorState, Skeleton } from './components/states';

const settings = window.wsakSettings ?? {};
const capabilities = settings.capabilities ?? {};

/**
 * The eight screens, in the three groups they fall into.
 *
 * Find is what you look at, Fix is what you change, Record is what you decided
 * and what you publish about it. A screen appears only when the person has the
 * capability its routes require — the routes enforce that regardless, and this
 * is so the interface does not offer a door that will not open.
 */
const GROUPS = [
	{
		id: 'find',
		label: __( 'Find', 'wowstudio-accessibility-kit' ),
		items: [
			{
				id: 'overview',
				label: __( 'Overview', 'wowstudio-accessibility-kit' ),
			},
			{
				id: 'scan',
				label: __( 'One page', 'wowstudio-accessibility-kit' ),
			},
			{
				id: 'bulk',
				label: __( 'Your content', 'wowstudio-accessibility-kit' ),
				needs: 'runScan',
			},
			{
				id: 'theme',
				label: __( 'Theme', 'wowstudio-accessibility-kit' ),
				needs: 'viewReports',
			},
		],
	},
	{
		id: 'fix',
		label: __( 'Fix', 'wowstudio-accessibility-kit' ),
		items: [
			{
				id: 'images',
				label: __( 'Images', 'wowstudio-accessibility-kit' ),
				needs: 'applyFix',
			},
			{
				id: 'fixes',
				label: __( 'Site fixes', 'wowstudio-accessibility-kit' ),
				needs: 'viewReports',
			},
		],
	},
	{
		id: 'record',
		label: __( 'Record', 'wowstudio-accessibility-kit' ),
		items: [
			{
				id: 'aside',
				label: __( 'False positives', 'wowstudio-accessibility-kit' ),
				needs: 'viewReports',
			},
			{
				id: 'statement',
				label: __( 'Statement', 'wowstudio-accessibility-kit' ),
				needs: 'viewReports',
			},
		],
	},
];

/**
 * The whole screen.
 *
 * @return {Element} The app.
 */
export default function App() {
	const [ scan, setScan ] = useState( null );
	const [ scanning, setScanning ] = useState( 0 );
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ announcement, setAnnouncement ] = useState( '' );
	const [ view, setView ] = useState( 'overview' );

	// What a figure on the overview was narrowed to when somebody opened it:
	// { rule } or { post }. Held beside the view rather than encoded into it so
	// the drill-down screen stays one screen rather than one per filter.
	const [ filter, setFilter ] = useState( null );

	/*
	 * Seeded by the server and kept current in the browser. Reading it back
	 * from the server after every scan would be a request for one string; a
	 * scan that just finished happened now, and now is what the header should
	 * say.
	 */
	const [ lastScan, setLastScan ] = useState( settings.lastScan ?? '' );

	// How a finished scan is presented. The inspector puts each finding beside
	// the page it came from, which is the more useful of the two whenever the
	// page can actually be framed; the list stays available because a scan that
	// ran without a browser has nothing to show beside it.
	const [ resultView, setResultView ] = useState( 'inspect' );

	const resultHeading = useRef( null );

	// Moving focus to the results is what makes this usable from a keyboard or a
	// screen reader. Without it, finishing a scan silently changes the page
	// under someone who is still sitting on the button they pressed.
	useEffect( () => {
		if ( scan && resultHeading.current ) {
			resultHeading.current.focus();
		}
	}, [ scan ] );

	const startScan = useCallback( ( postId ) => {
		setError( '' );
		setScanning( postId );
		setAnnouncement(
			__( 'Scanning the page…', 'wowstudio-accessibility-kit' )
		);

		runScan( postId )
			.then( ( data ) => {
				setScan( data );
				setLastScan(
					new Date().toLocaleString( undefined, {
						dateStyle: 'medium',
						timeStyle: 'short',
					} )
				);
				setAnnouncement(
					sprintf(
						/* translators: %d: number of findings. */
						__(
							'Scan finished. %d findings.',
							'wowstudio-accessibility-kit'
						),
						data.issues?.length ?? 0
					)
				);
			} )
			.catch( ( caught ) => {
				setError( readableError( caught ) );
				setAnnouncement(
					__( 'The scan failed.', 'wowstudio-accessibility-kit' )
				);
			} )
			.finally( () => setScanning( 0 ) );
	}, [] );

	const openScan = useCallback( ( scanId ) => {
		setError( '' );
		setLoading( true );

		fetchScan( scanId )
			.then( setScan )
			.catch( ( caught ) => setError( readableError( caught ) ) )
			.finally( () => setLoading( false ) );
	}, [] );

	const byDetection = scan?.summary?.by_detection ?? {};

	return (
		<div className="wsak">
			<header className="wsak__header">
				{ /*
				 * Decorative: the heading beside it already names the screen,
				 * so announcing the mark as well would just be noise.
				 */ }
				<span className="wsak__mark" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" focusable="false">
						<path
							d="M3 7.6 L7.2 16.6 L12 9.4 L16.8 16.6 L21 7.6"
							stroke="#fff"
							strokeWidth="2.6"
							strokeLinecap="round"
							strokeLinejoin="round"
						/>
					</svg>
				</span>
				<div className="wsak__titles">
					<h1 className="wsak__title">
						{ __(
							'Accessibility Kit',
							'wowstudio-accessibility-kit'
						) }
					</h1>
					<p className="wsak__lede">
						{ __(
							'Find, fix and document accessibility problems at the code level.',
							'wowstudio-accessibility-kit'
						) }
					</p>
				</div>

				{ /*
				 * When anything was last checked, on every screen rather than
				 * only on the report. Nothing here scans on its own, so a
				 * figure on screen is only ever as old as the last time
				 * somebody pressed the button — and "never" is a normal answer
				 * that the report alone would never have told them.
				 *
				 * The control beside it opens the screen where you choose what
				 * to check. It does not start a site-wide run: a button that
				 * quietly begins scanning every page is not one somebody should
				 * be able to press by accident.
				 */ }
				{ capabilities.runScan && (
					<div className="wsak__meta">
						<p className="wsak__last-scan">
							{ lastScan
								? sprintf(
										/* translators: %s: when the most recent scan finished. */
										__(
											'Last checked %s',
											'wowstudio-accessibility-kit'
										),
										lastScan
								  )
								: __(
										'Nothing checked yet',
										'wowstudio-accessibility-kit'
								  ) }
						</p>
						<Button
							variant="primary"
							onClick={ () => setView( 'bulk' ) }
						>
							{ __(
								'Check pages',
								'wowstudio-accessibility-kit'
							) }
						</Button>
					</div>
				) }
			</header>

			{ /*
			 * Grouped, because eight tabs in a row make somebody guess what
			 * each one is for. The three names say why a screen exists rather
			 * than what it contains: things you look at, things you change, and
			 * the record of what you decided.
			 *
			 * Theme sits under Find, not Fix. It is a scan of a thing — the
			 * header, navigation and footer — and belongs beside the other two
			 * scans; that its findings often point at a site fix is where they
			 * lead, not what the screen is.
			 */ }
			<nav
				className="wsak__nav"
				aria-label={ __( 'Sections', 'wowstudio-accessibility-kit' ) }
			>
				{ GROUPS.map( ( group ) => {
					const items = group.items.filter(
						( item ) => ! item.needs || capabilities[ item.needs ]
					);

					if ( ! items.length ) {
						return null;
					}

					return (
						<div className="wsak__nav-group" key={ group.id }>
							<h2
								className="wsak__nav-label"
								id={ `wsak-nav-${ group.id }` }
							>
								{ group.label }
							</h2>
							<ul
								className="wsak__nav-items"
								aria-labelledby={ `wsak-nav-${ group.id }` }
							>
								{ items.map( ( item ) => (
									<li key={ item.id }>
										<Button
											variant={
												view === item.id
													? 'primary'
													: 'tertiary'
											}
											aria-current={
												view === item.id
													? 'page'
													: undefined
											}
											onClick={ () => setView( item.id ) }
										>
											{ item.label }
										</Button>
									</li>
								) ) }
							</ul>
						</div>
					);
				} ) }
			</nav>

			<p className="screen-reader-text" role="status" aria-live="polite">
				{ announcement }
			</p>

			{ error && (
				<ErrorState
					message={ error }
					onRetry={ () => setError( '' ) }
				/>
			) }

			{ ! capabilities.runScan && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'You can read results here but not start a scan. An administrator can grant that.',
						'wowstudio-accessibility-kit'
					) }
				</Notice>
			) }

			{ /*
			 * Checking one page and checking a hundred are different jobs with
			 * different coverage, so they are different screens rather than one
			 * screen with a mode. Opening a page from a bulk result drops into
			 * the single-page view, which is where the browser pass runs.
			 */ }
			{ view === 'overview' && (
				<Overview
					onGo={ setView }
					onDrill={ ( next ) => {
						setFilter( next );
						setView( 'findings' );
					} }
				/>
			) }

			{ view === 'findings' && (
				<IssueDrilldown
					filter={ filter }
					onBack={ () => setView( 'overview' ) }
					onGo={ setView }
				/>
			) }

			{ view === 'bulk' && (
				<BulkScan
					onInspect={ ( postId ) => {
						setView( 'scan' );
						startScan( postId );
					} }
				/>
			) }

			{ view === 'images' && <AltTextEditor /> }

			{ view === 'aside' && <Dismissed /> }

			{ view === 'fixes' && <SiteFixes /> }

			{ view === 'theme' && <ThemePanel /> }

			{ view === 'statement' && <StatementSettings /> }

			{ view === 'scan' && loading && (
				<Skeleton
					label={ __(
						'Loading the scan…',
						'wowstudio-accessibility-kit'
					) }
				/>
			) }

			{ view === 'scan' && scan && ! loading && (
				<section
					className="wsak-result"
					aria-labelledby="wsak-result-title"
				>
					<div className="wsak-result__head">
						<h2
							className="wsak-result__title"
							id="wsak-result-title"
							tabIndex="-1"
							ref={ resultHeading }
						>
							{ scan.post_title
								? sprintf(
										/* translators: %s: content title. */
										__(
											'Results for “%s”',
											'wowstudio-accessibility-kit'
										),
										scan.post_title
								  )
								: __(
										'Scan results',
										'wowstudio-accessibility-kit'
								  ) }
						</h2>
						<div className="wsak-result__actions">
							<Button
								variant={
									resultView === 'inspect'
										? 'primary'
										: 'secondary'
								}
								aria-pressed={ resultView === 'inspect' }
								disabled={ ! scan.preview_url }
								onClick={ () =>
									setResultView(
										resultView === 'inspect'
											? 'list'
											: 'inspect'
									)
								}
							>
								{ resultView === 'inspect'
									? __(
											'Show as a list',
											'wowstudio-accessibility-kit'
									  )
									: __(
											'Show on the page',
											'wowstudio-accessibility-kit'
									  ) }
							</Button>
							<Button
								variant="secondary"
								onClick={ () => setScan( null ) }
							>
								{ __(
									'Back to your content',
									'wowstudio-accessibility-kit'
								) }
							</Button>
						</div>
					</div>

					{ scan.coverage_notice && (
						<Notice status="warning" isDismissible={ false }>
							<strong>
								{ __(
									'This scan covered less than usual.',
									'wowstudio-accessibility-kit'
								) }
							</strong>{ ' ' }
							{ scan.coverage_notice }
						</Notice>
					) }

					<ScoreCard
						score={ scan.score ?? 0 }
						auto={ byDetection.auto ?? 0 }
						manual={ byDetection.manual ?? 0 }
						severity={ scan.summary?.by_severity }
					/>

					{ resultView === 'inspect' && scan.preview_url ? (
						<Inspector
							issues={ scan.issues ?? [] }
							previewUrl={ scan.preview_url }
							title={ scan.post_title }
							scanId={ scan.scan_id }
							placeable={ Boolean( scan.placeable ) }
							onExit={ () => setResultView( 'list' ) }
							onFindings={ ( data ) =>
								setScan( ( current ) => ( {
									...current,
									issues: data.issues ?? current.issues,
									browser_pass: data.browser_pass,
									// The verdict moves with the findings. See
									// the note on the browser-pass route: these
									// used to be left at the server pass's
									// numbers, so the card said "0 issues" over
									// a list of them.
									score: data.score ?? current.score,
									summary: data.summary ?? current.summary,
								} ) )
							}
						/>
					) : (
						<IssueList
							issues={ scan.issues ?? [] }
							onInspect={
								scan.preview_url
									? () => setResultView( 'inspect' )
									: undefined
							}
						/>
					) }
				</section>
			) }

			{ view === 'scan' && ! scan && ! loading && (
				<ScanPicker
					onScan={ startScan }
					onOpen={ openScan }
					scanning={ scanning }
					canScan={ Boolean( capabilities.runScan ) }
				/>
			) }

			{ view === 'scan' && <CoveragePanel /> }

			{ /*
			 * Reachable as a view rather than a tab. It is reference material
			 * people want twice — once when they start and once when a finding
			 * surprises them — and a permanent tab for that is a tab everybody
			 * scrolls past. The doors to it are on the overview and at the foot
			 * of the findings list, which is where those two moments happen.
			 */ }
			{ view === 'coverage' && (
				<>
					<p className="wsak-back">
						<Button
							variant="link"
							onClick={ () => setView( 'overview' ) }
						>
							{ __(
								'← Back to the overview',
								'wowstudio-accessibility-kit'
							) }
						</Button>
					</p>
					<CoveragePanel />
				</>
			) }

			<footer className="wsak__footer">
				<p>
					{ __(
						'This plugin helps you find, fix and document accessibility problems. It does not determine whether your site meets the ADA, the European Accessibility Act, Section 508, or any other legal requirement, and nothing here is legal advice.',
						'wowstudio-accessibility-kit'
					) }
				</p>
			</footer>
		</div>
	);
}
