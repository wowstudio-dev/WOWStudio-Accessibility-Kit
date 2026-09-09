/**
 * What is wrong with the theme, and who can put each thing right.
 */

import { Button, Notice, TextareaControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { checkTheme, fetchTheme, readableError } from '../api';
import { Busy, Skeleton } from './states';
import { DetectionTag, SeverityTag } from './tags';

/**
 * The theme's own findings, sorted by what would actually fix them.
 *
 * Most accessibility faults on a real site are here rather than in the content,
 * and this plugin cannot edit a theme. Reporting them and stopping there would
 * be accurate and useless, so each one says who can fix it and how — decision
 * F6.
 *
 * @return {Element} The panel.
 */
export default function ThemePanel() {
	const [ state, setState ] = useState( null );
	const [ checking, setChecking ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ copied, setCopied ] = useState( false );

	useEffect( () => {
		fetchTheme()
			.then( setState )
			.catch( ( caught ) => setError( readableError( caught ) ) );
	}, [] );

	const check = () => {
		setChecking( true );
		setError( '' );

		checkTheme()
			.then( setState )
			.catch( ( caught ) => setError( readableError( caught ) ) )
			.finally( () => setChecking( false ) );
	};

	if ( null === state && ! error ) {
		return (
			<Skeleton
				label={ __(
					'Looking at your theme…',
					'wowstudio-accessibility-kit'
				) }
			/>
		);
	}

	const findings = state?.findings ?? [];
	const settings = findings.filter(
		( one ) => one.triage?.tier === 'setting'
	);
	const handoffs = findings.filter(
		( one ) => one.triage?.tier === 'handoff'
	);

	return (
		<section className="wsak-theme" aria-labelledby="wsak-theme-title">
			<div className="wsak-theme__head">
				<h2 className="wsak-theme__title" id="wsak-theme-title">
					{ state?.theme
						? sprintf(
								/* translators: %s: theme name. */
								__(
									'Your theme: %s',
									'wowstudio-accessibility-kit'
								),
								state.theme
						  )
						: __( 'Your theme', 'wowstudio-accessibility-kit' ) }
				</h2>
				<Button
					variant={ state?.checked ? 'secondary' : 'primary' }
					disabled={ checking }
					onClick={ check }
				>
					{ state?.checked
						? __( 'Check again', 'wowstudio-accessibility-kit' )
						: __(
								'Check my theme',
								'wowstudio-accessibility-kit'
						  ) }
				</Button>
			</div>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ checking && (
				<Busy
					label={ __(
						'Looking at a few representative pages…',
						'wowstudio-accessibility-kit'
					) }
				/>
			) }

			<p className="wsak-theme__lede">
				{ __(
					'Your header, navigation and footer are the same on every page, so they are checked once here rather than reported against every page that uses them.',
					'wowstudio-accessibility-kit'
				) }
			</p>

			{ state && ! state.checked && ! checking && (
				<p className="wsak-theme__lede">
					{ __(
						'Nothing has looked at your theme yet.',
						'wowstudio-accessibility-kit'
					) }
				</p>
			) }

			{ settings.length > 0 && (
				<section className="wsak-group">
					<h3 className="wsak-group__title">
						{ __(
							'You can fix these yourself, without code',
							'wowstudio-accessibility-kit'
						) }
					</h3>
					<p className="wsak-group__blurb">
						{ __(
							'Each of these is a field on a screen you already have. Fixing it there is permanent, and it holds whether or not this plugin stays installed.',
							'wowstudio-accessibility-kit'
						) }
					</p>
					<ul className="wsak-issues">
						{ settings.map( ( finding ) => (
							<Finding key={ finding.id } finding={ finding } />
						) ) }
					</ul>
				</section>
			) }

			{ handoffs.length > 0 && (
				<section className="wsak-group">
					<h3 className="wsak-group__title">
						{ __(
							'These need whoever maintains your theme',
							'wowstudio-accessibility-kit'
						) }
					</h3>
					<p className="wsak-group__blurb">
						{ __(
							'These live in theme files, which nothing here can change. There is a copyable summary below with the exact change for each one.',
							'wowstudio-accessibility-kit'
						) }
					</p>
					<ul className="wsak-issues">
						{ handoffs.map( ( finding ) => (
							<Finding key={ finding.id } finding={ finding } />
						) ) }
					</ul>
				</section>
			) }

			{ state?.handover && (
				<div className="wsak-theme__handover">
					<h3 className="wsak-group__title">
						{ sprintf(
							/* translators: %d: number of changes needed. */
							_n(
								'Send this to your developer (%d change)',
								'Send this to your developer (%d changes)',
								handoffs.length,
								'wowstudio-accessibility-kit'
							),
							handoffs.length
						) }
					</h3>
					<TextareaControl
						__nextHasNoMarginBottom
						label={ __(
							'The changes, written out',
							'wowstudio-accessibility-kit'
						) }
						hideLabelFromVision
						value={ state.handover }
						rows={ 10 }
						readOnly
						onChange={ () => {} }
					/>
					<div className="wsak-theme__handover-actions">
						<Button
							variant="secondary"
							onClick={ () => {
								navigator.clipboard
									?.writeText( state.handover )
									.then( () => setCopied( true ) )
									.catch( () => {} );
							} }
						>
							{ copied
								? __( 'Copied', 'wowstudio-accessibility-kit' )
								: __(
										'Copy it',
										'wowstudio-accessibility-kit'
								  ) }
						</Button>
					</div>
				</div>
			) }

			{ state?.checked && findings.length === 0 && (
				<Notice status="success" isDismissible={ false }>
					{ __(
						'None of the automated checks found anything in your theme. That is a good sign and not a clean bill of health — most of WCAG needs a person.',
						'wowstudio-accessibility-kit'
					) }
				</Notice>
			) }
		</section>
	);
}

/**
 * One theme finding, with what to do about it.
 *
 * @param {Object} props         Component props.
 * @param {Object} props.finding The finding.
 * @return {Element} The card.
 */
function Finding( { finding } ) {
	const triage = finding.triage ?? {};

	return (
		<li className="wsak-issue">
			<div className="wsak-issue__head">
				<h4 className="wsak-issue__title">{ finding.rule_title }</h4>
				<div className="wsak-issue__tags">
					<SeverityTag
						severity={ finding.severity }
						label={ finding.severity_label }
					/>
					<DetectionTag
						detection={ finding.detection }
						label={ finding.detection_label }
					/>
					<span className="wsak-tag wsak-tag--sc">
						{ sprintf(
							/* translators: %s: WCAG success criterion number. */
							__( 'WCAG %s', 'wowstudio-accessibility-kit' ),
							finding.wcag_sc
						) }
					</span>
				</div>
			</div>

			{ finding.consequence && (
				<p className="wsak-issue__consequence">
					{ finding.consequence }
				</p>
			) }

			{ triage.instruction && (
				<p className="wsak-issue__plan">{ triage.instruction }</p>
			) }

			{ triage.url && (
				<p className="wsak-theme__link">
					<a href={ triage.url }>
						{ __(
							'Take me to the setting',
							'wowstudio-accessibility-kit'
						) }
					</a>
				</p>
			) }

			{ triage.guidance && (
				<p className="wsak-issue__plan wsak-theme__guidance">
					{ triage.guidance }
				</p>
			) }

			{ triage.snippet && (
				<pre
					className="wsak-issue__context"
					tabIndex="0"
					role="group"
					aria-label={ sprintf(
						/* translators: %s: name of the accessibility check. */
						__(
							'Suggested change for: %s',
							'wowstudio-accessibility-kit'
						),
						finding.rule_title
					) }
				>
					<code>{ triage.snippet }</code>
				</pre>
			) }
		</li>
	);
}
