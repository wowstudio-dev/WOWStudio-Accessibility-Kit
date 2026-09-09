/**
 * The first run: three steps, each of which does something real.
 */

import { Button, Notice } from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { readableError, setOnboarded } from '../api';
import BulkScan from './bulk-scan';
import SiteFixes from './site-fixes';

/**
 * What this plugin promises, and what it refuses to.
 *
 * All three are load-bearing rather than decorative. Each one is a thing people
 * have been burned by often enough that saying it early is worth more than any
 * feature list: an accessibility plugin that quietly ships data somewhere, one
 * that bolts a toolbar onto the front of your site, and one that claims to have
 * settled the law for you. The third has the FTC precedent behind it, which is
 * why product rule 1 exists and why `bin/check-claims.php` guards it.
 */
const PROMISES = [
	{
		key: 'private',
		title: __( 'Nothing leaves your site', 'wowstudio-accessibility-kit' ),
		body: __(
			'No API, no account, no telemetry. Every check runs on your own server and in your own browser, and the plugin makes no outbound request at all — which is a promise it keeps by having nowhere to send anything.',
			'wowstudio-accessibility-kit'
		),
	},
	{
		key: 'no-overlay',
		title: __(
			'Nothing is added to your site',
			'wowstudio-accessibility-kit'
		),
		body: __(
			'No widget, no toolbar, no floating button for your visitors to find. Their browser and their operating system already do larger text and higher contrast better than any overlay could. What this changes is your markup and your styles.',
			'wowstudio-accessibility-kit'
		),
	},
	{
		key: 'honest',
		title: __(
			'It will not tell you that you are compliant',
			'wowstudio-accessibility-kit'
		),
		body: __(
			'Automated checks reach part of WCAG, never all of it. Every finding is labelled either as settled by a check or as needing a person to look, and the conformance report is a draft you review and put your own name to.',
			'wowstudio-accessibility-kit'
		),
	},
];

/**
 * Step one: what you have just installed.
 *
 * @return {Element} The step.
 */
function WhatThisIs() {
	return (
		<section
			className="wsak-welcome__step"
			aria-labelledby="wsak-welcome-intro"
		>
			<h2 id="wsak-welcome-intro" className="wsak-welcome__title">
				{ __(
					'Find, fix and document',
					'wowstudio-accessibility-kit'
				) }
			</h2>

			<p className="wsak-welcome__lede">
				{ __(
					'This plugin reads your pages, tells you what is wrong with them and where, fixes what can honestly be fixed from here, and writes the rest down for whoever can. It is free, all of it, and nothing in it is held back.',
					'wowstudio-accessibility-kit'
				) }
			</p>

			<ul className="wsak-welcome__promises">
				{ PROMISES.map( ( promise ) => (
					<li className="wsak-promise" key={ promise.key }>
						<h3 className="wsak-promise__title">
							{ promise.title }
						</h3>
						<p className="wsak-promise__body">{ promise.body }</p>
					</li>
				) ) }
			</ul>
		</section>
	);
}

/**
 * The steps, in order.
 *
 * Two of the three render the screen they are about rather than a summary of
 * it. A wizard that paraphrases the settings it is setting is two copies of the
 * same text that will disagree within a release, and on this screen in
 * particular the thing being paraphrased away would be the caveat under each
 * switch — which is exactly what somebody needs in front of them at the moment
 * they flip it.
 *
 * @param {Function} onGo Opens another screen.
 * @return {Array<Object>} The steps.
 */
function stepsFor( onGo ) {
	return [
		{
			key: 'about',
			label: __( 'What this is', 'wowstudio-accessibility-kit' ),
			render: () => <WhatThisIs />,
		},
		{
			key: 'fixes',
			label: __( 'Switch on fixes', 'wowstudio-accessibility-kit' ),
			render: () => <SiteFixes />,
		},
		{
			key: 'scan',
			label: __( 'Check your pages', 'wowstudio-accessibility-kit' ),
			render: () => <BulkScan onInspect={ () => onGo( 'scan' ) } />,
		},
	];
}

/**
 * The setup.
 *
 * Everything here is optional and nothing is written without being asked for.
 * Leaving halfway through is a supported outcome rather than an abandoned
 * funnel: the plugin behaves identically either way, and the only thing
 * finishing records is that this person has seen it.
 *
 * @param {Object}   props           Component props.
 * @param {Function} props.onGo      Opens another screen.
 * @param {string}   [props.homeUrl] Where "finish" leads.
 * @param {boolean}  [props.revisit] Whether the setup has been done before.
 * @return {Element} The screen.
 */
export default function Onboarding( { onGo, homeUrl, revisit } ) {
	const steps = stepsFor( onGo );

	const [ index, setIndex ] = useState( 0 );
	const [ error, setError ] = useState( '' );
	const [ saving, setSaving ] = useState( false );

	const stage = useRef( null );
	const first = useRef( true );

	/*
	 * Focus follows the step. Without this, pressing "Next" changes everything
	 * on screen while the reading position stays on a button that has just
	 * moved, which for anybody on a keyboard or a screen reader is the same as
	 * the page not having changed at all.
	 *
	 * Not on the first render, though: taking focus off the page the moment it
	 * loads is its own rudeness.
	 */
	useEffect( () => {
		if ( first.current ) {
			first.current = false;

			return;
		}

		stage.current?.focus();
	}, [ index ] );

	const finish = useCallback( () => {
		setError( '' );
		setSaving( true );

		setOnboarded( true )
			.then( () => {
				window.location.href = homeUrl || window.location.href;
			} )
			.catch( ( caught ) => {
				setError( readableError( caught ) );
				setSaving( false );
			} );
	}, [ homeUrl ] );

	const last = index === steps.length - 1;

	return (
		<div className="wsak-welcome">
			<header className="wsak-welcome__head">
				<p className="wsak-welcome__eyebrow">
					{ revisit
						? __( 'Setup', 'wowstudio-accessibility-kit' )
						: __(
								'Welcome to Accessibility Kit',
								'wowstudio-accessibility-kit'
						  ) }
				</p>
				<h1 className="wsak-welcome__heading">
					{ __(
						'Three steps, and none of them are mandatory',
						'wowstudio-accessibility-kit'
					) }
				</h1>
				<p className="wsak-welcome__sub">
					{ __(
						'You can leave at any point and the plugin works exactly the same. Nothing here is switched on for you.',
						'wowstudio-accessibility-kit'
					) }
				</p>
			</header>

			<div className="wsak-welcome__body">
				{ /*
				 * The rail is navigation as well as progress: a step already
				 * seen can be gone back to, which is the difference between a
				 * setup and a slideshow. Steps ahead are buttons too — nothing
				 * here has to be done in order, and disabling them would be
				 * pretending otherwise.
				 */ }
				<nav
					className="wsak-welcome__rail"
					aria-label={ __(
						'Setup steps',
						'wowstudio-accessibility-kit'
					) }
				>
					<ol className="wsak-welcome__rail-items">
						{ steps.map( ( step, at ) => (
							<li
								className={ `wsak-rail-step${
									at === index ? ' is-current' : ''
								}${ at < index ? ' is-done' : '' }` }
								key={ step.key }
							>
								<button
									type="button"
									className="wsak-rail-step__button"
									aria-current={
										at === index ? 'step' : undefined
									}
									onClick={ () => setIndex( at ) }
								>
									<span className="wsak-rail-step__n">
										{ at + 1 }
									</span>
									<span className="wsak-rail-step__label">
										{ step.label }
									</span>
								</button>
							</li>
						) ) }
					</ol>
				</nav>

				<div
					className="wsak-welcome__stage"
					tabIndex="-1"
					ref={ stage }
				>
					{ /*
					 * The count in words as well as in the rail. The rail says
					 * it in position and colour, and neither of those survives
					 * being read aloud.
					 */ }
					<p className="wsak-welcome__counter">
						{ sprintf(
							/* translators: 1: current step number. 2: how many steps there are. */
							__(
								'Step %1$d of %2$d',
								'wowstudio-accessibility-kit'
							),
							index + 1,
							steps.length
						) }
					</p>

					{ steps[ index ].render() }

					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }

					<div className="wsak-welcome__actions">
						{ index > 0 && (
							<Button
								variant="secondary"
								onClick={ () => setIndex( index - 1 ) }
							>
								{ __( 'Back', 'wowstudio-accessibility-kit' ) }
							</Button>
						) }

						{ last ? (
							<Button
								variant="primary"
								isBusy={ saving }
								disabled={ saving }
								onClick={ finish }
							>
								{ __(
									'Finish setup',
									'wowstudio-accessibility-kit'
								) }
							</Button>
						) : (
							<Button
								variant="primary"
								onClick={ () => setIndex( index + 1 ) }
							>
								{ __( 'Next', 'wowstudio-accessibility-kit' ) }
							</Button>
						) }

						{ /*
						 * Leaving is a real option and is worded as one. "Skip"
						 * implies something was missed; nothing here is
						 * required, and the switches and the scan are equally
						 * available from the menu afterwards.
						 */ }
						{ ! last && (
							<Button
								variant="link"
								className="wsak-welcome__leave"
								disabled={ saving }
								onClick={ finish }
							>
								{ __(
									'I will do this later',
									'wowstudio-accessibility-kit'
								) }
							</Button>
						) }
					</div>
				</div>
			</div>
		</div>
	);
}
