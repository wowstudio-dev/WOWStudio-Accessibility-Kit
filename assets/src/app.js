/**
 * The accessibility dashboard.
 */

import { Button, Notice } from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { fetchScan, readableError, runScan } from './api';
import CoveragePanel from './components/coverage-panel';
import IssueList from './components/issue-list';
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
				<h1 className="wsak__title">
					{ __( 'Accessibility', 'wowstudio-accessibility-kit' ) }
				</h1>
				<p className="wsak__lede">
					{ __(
						'Find, fix, document, and monitor accessibility problems in your site at the code level.',
						'wowstudio-accessibility-kit'
					) }
				</p>
			</header>

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

			{ loading && (
				<Skeleton
					label={ __(
						'Loading the scan…',
						'wowstudio-accessibility-kit'
					) }
				/>
			) }

			{ scan && ! loading && (
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
					/>

					<IssueList issues={ scan.issues ?? [] } />
				</section>
			) }

			{ ! scan && ! loading && (
				<ScanPicker
					onScan={ startScan }
					onOpen={ openScan }
					scanning={ scanning }
					canScan={ Boolean( capabilities.runScan ) }
				/>
			) }

			<CoveragePanel />

			<footer className="wsak__footer">
				<p>
					{ __(
						'This plugin helps you find, fix, document, and monitor accessibility problems. It does not determine whether your site meets the ADA, the European Accessibility Act, Section 508, or any other legal requirement, and nothing here is legal advice.',
						'wowstudio-accessibility-kit'
					) }
				</p>
			</footer>
		</div>
	);
}
