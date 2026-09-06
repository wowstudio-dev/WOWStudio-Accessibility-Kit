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
import IssueList from './components/issue-list';
import Overview from './components/overview';
import AltTextEditor from './components/alt-text-editor';
import SiteFixes from './components/site-fixes';
import BulkScan from './components/bulk-scan';
import ThemePanel from './components/theme-panel';
import ScanPicker from './components/scan-picker';
import ScoreCard from './components/score-card';
import { ErrorState, Skeleton } from './components/states';

const settings = window.wsakSettings ?? {};
const capabilities = settings.capabilities ?? {};

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
			</header>

			<nav
				className="wsak__nav"
				aria-label={ __( 'Sections', 'wowstudio-accessibility-kit' ) }
			>
				<Button
					variant={ view === 'overview' ? 'primary' : 'tertiary' }
					aria-current={ view === 'overview' ? 'page' : undefined }
					onClick={ () => setView( 'overview' ) }
				>
					{ __( 'Overview', 'wowstudio-accessibility-kit' ) }
				</Button>
				<Button
					variant={ view === 'scan' ? 'primary' : 'tertiary' }
					aria-current={ view === 'scan' ? 'page' : undefined }
					onClick={ () => setView( 'scan' ) }
				>
					{ __( 'One page', 'wowstudio-accessibility-kit' ) }
				</Button>
				{ capabilities.runScan && (
					<Button
						variant={ view === 'bulk' ? 'primary' : 'tertiary' }
						aria-current={ view === 'bulk' ? 'page' : undefined }
						onClick={ () => setView( 'bulk' ) }
					>
						{ __( 'Your content', 'wowstudio-accessibility-kit' ) }
					</Button>
				) }
				{ capabilities.applyFix && (
					<Button
						variant={ view === 'images' ? 'primary' : 'tertiary' }
						aria-current={ view === 'images' ? 'page' : undefined }
						onClick={ () => setView( 'images' ) }
					>
						{ __( 'Images', 'wowstudio-accessibility-kit' ) }
					</Button>
				) }
				{ capabilities.viewReports && (
					<Button
						variant={ view === 'fixes' ? 'primary' : 'tertiary' }
						aria-current={ view === 'fixes' ? 'page' : undefined }
						onClick={ () => setView( 'fixes' ) }
					>
						{ __( 'Site fixes', 'wowstudio-accessibility-kit' ) }
					</Button>
				) }
				{ capabilities.viewReports && (
					<Button
						variant={ view === 'theme' ? 'primary' : 'tertiary' }
						aria-current={ view === 'theme' ? 'page' : undefined }
						onClick={ () => setView( 'theme' ) }
					>
						{ __( 'Theme', 'wowstudio-accessibility-kit' ) }
					</Button>
				) }
				{ capabilities.viewReports && (
					<Button
						variant={
							view === 'statement' ? 'primary' : 'tertiary'
						}
						aria-current={
							view === 'statement' ? 'page' : undefined
						}
						onClick={ () => setView( 'statement' ) }
					>
						{ __( 'Statement', 'wowstudio-accessibility-kit' ) }
					</Button>
				) }
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
			{ view === 'overview' && <Overview /> }

			{ view === 'bulk' && (
				<BulkScan
					onInspect={ ( postId ) => {
						setView( 'scan' );
						startScan( postId );
					} }
				/>
			) }

			{ view === 'images' && <AltTextEditor /> }

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
