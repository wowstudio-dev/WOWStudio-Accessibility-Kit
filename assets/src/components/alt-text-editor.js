/**
 * Every undescribed image on the site, with somewhere to describe it.
 */

import {
	Button,
	CheckboxControl,
	Notice,
	TextControl,
} from '@wordpress/components';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

import { fetchUndescribedMedia, readableError, saveAltText } from '../api';
import { EmptyState, Skeleton } from './states';

/**
 * How many images one page of the list holds.
 *
 * Twenty-five rather than everything at once. This is a screen somebody works
 * down rather than skims, and a list of nine hundred text fields is both slow
 * to render and impossible to feel any progress against.
 */
const PER_PAGE = 25;

/**
 * One image, and the field that describes it.
 *
 * The field is a real label rather than a placeholder. Placeholder text
 * disappears the moment somebody types, which for anyone relying on it to
 * remember what the field was is the worst possible moment for it to go.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.item     The image.
 * @param {Object}   props.draft    What has been typed so far.
 * @param {Function} props.onChange Records a change.
 * @param {Function} props.onSave   Saves this row alone.
 * @param {boolean}  props.busy     Whether a save is in flight.
 * @return {Element} The row.
 */
function ImageRow( { item, draft, onChange, onSave, busy } ) {
	const fieldId = `wsak-alt-${ item.id }`;
	const text = draft?.text ?? '';
	const decorative = draft?.decorative ?? false;
	const changed = '' !== text || decorative;

	return (
		<li className="wsak-alt-row">
			<div className="wsak-alt-row__media">
				{ item.thumbnail ? (
					/*
					 * Deliberately empty alt. The filename sits beside it as
					 * real text, so describing the thumbnail here would make a
					 * screen reader read the same name twice — and this plugin
					 * putting a redundant image in its own interface would be
					 * the exact fault it reports on other people's pages.
					 */
					<img
						className="wsak-alt-row__thumb"
						src={ item.thumbnail }
						alt=""
						width="80"
						height="80"
					/>
				) : (
					<span className="wsak-alt-row__thumb wsak-alt-row__thumb--none" />
				) }
			</div>

			<div className="wsak-alt-row__body">
				<p className="wsak-alt-row__file">
					<a href={ item.edit_url } target="_blank" rel="noreferrer">
						{ item.filename || item.title }
						<span className="screen-reader-text">
							{ ' ' }
							{ __(
								'(opens the media screen in a new tab)',
								'wowstudio-accessibility-kit'
							) }
						</span>
					</a>
				</p>

				{ item.uploaded_to && (
					<p className="wsak-alt-row__origin">
						{ sprintf(
							/* translators: %s: title of the post the image was uploaded from. */
							__(
								'Uploaded from “%s”. It may be used elsewhere too.',
								'wowstudio-accessibility-kit'
							),
							item.uploaded_to.title
						) }
					</p>
				) }

				<TextControl
					id={ fieldId }
					className="wsak-alt-row__field"
					label={ __(
						'Describe this image',
						'wowstudio-accessibility-kit'
					) }
					help={ __(
						'Say what the image tells the reader, not what it looks like.',
						'wowstudio-accessibility-kit'
					) }
					value={ text }
					disabled={ decorative || busy }
					onChange={ ( value ) =>
						onChange( item.id, { text: value, decorative } )
					}
					__nextHasNoMarginBottom
				/>

				<CheckboxControl
					className="wsak-alt-row__decorative"
					label={ __(
						'Decorative — this image carries no meaning of its own',
						'wowstudio-accessibility-kit'
					) }
					help={ __(
						'Saves an empty description, which tells a screen reader to skip the image. That is a real answer, not a blank one.',
						'wowstudio-accessibility-kit'
					) }
					checked={ decorative }
					disabled={ busy }
					onChange={ ( value ) =>
						onChange( item.id, { text, decorative: value } )
					}
					__nextHasNoMarginBottom
				/>

				{ draft?.error && (
					<p className="wsak-alt-row__error" role="alert">
						{ draft.error }
					</p>
				) }

				{ draft?.saved && (
					<p className="wsak-alt-row__saved">
						{ __( 'Saved.', 'wowstudio-accessibility-kit' ) }
					</p>
				) }
			</div>

			<div className="wsak-alt-row__actions">
				<Button
					variant="secondary"
					disabled={ ! changed || busy }
					onClick={ () => onSave( [ item.id ] ) }
				>
					{ __( 'Save', 'wowstudio-accessibility-kit' ) }
				</Button>
			</div>
		</li>
	);
}

/**
 * The alt-text screen.
 *
 * Nothing here proposes any wording. What an image is *for* is a question about
 * why it was put on the page, and this plugin has no way to know that — so it
 * does the part it can do properly instead, which is finding every undescribed
 * image on the site and putting them in one list with a field beside each. The
 * work is still yours; the forty media screens are not.
 *
 * What gets written is WordPress's own alt field, so it applies wherever the
 * image is used and stays behind if this plugin is ever removed.
 *
 * @return {Element} The screen.
 */
export default function AltTextEditor() {
	const [ data, setData ] = useState( null );
	const [ page, setPage ] = useState( 1 );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ drafts, setDrafts ] = useState( {} );
	const [ announcement, setAnnouncement ] = useState( '' );

	const live = useRef( true );

	useEffect( () => {
		live.current = true;
		return () => {
			live.current = false;
		};
	}, [] );

	const load = useCallback( ( which ) => {
		setLoading( true );
		setError( '' );

		fetchUndescribedMedia( which, PER_PAGE )
			.then( ( result ) => {
				if ( ! live.current ) {
					return;
				}
				setData( result );
				setDrafts( {} );
			} )
			.catch(
				( err ) => live.current && setError( readableError( err ) )
			)
			.finally( () => live.current && setLoading( false ) );
	}, [] );

	useEffect( () => load( page ), [ load, page ] );

	const change = useCallback( ( id, next ) => {
		setDrafts( ( current ) => ( {
			...current,
			// The previous outcome is dropped on edit. Leaving "Saved." beside
			// a field somebody has since changed would be a lie about what is
			// stored.
			[ id ]: { ...next, error: '', saved: false },
		} ) );
	}, [] );

	const save = useCallback(
		( ids ) => {
			const items = ids
				.map( ( id ) => ( { id, ...( drafts[ id ] || {} ) } ) )
				.filter(
					( item ) => '' !== ( item.text ?? '' ) || item.decorative
				)
				.map( ( item ) => ( {
					id: item.id,
					text: item.text ?? '',
					decorative: Boolean( item.decorative ),
				} ) );

			if ( ! items.length ) {
				return;
			}

			setBusy( true );

			saveAltText( items )
				.then( ( result ) => {
					if ( ! live.current ) {
						return;
					}

					const results = result?.results || [];
					const saved = results.filter( ( row ) => row.saved );

					setDrafts( ( current ) => {
						const next = { ...current };

						results.forEach( ( row ) => {
							if ( row.saved ) {
								delete next[ row.id ];
							} else {
								next[ row.id ] = {
									...( next[ row.id ] || {} ),
									error: row.message || '',
									saved: false,
								};
							}
						} );

						return next;
					} );

					// Described images drop out of the list, because the list
					// is "what still needs doing". Removing them here rather
					// than refetching keeps whatever else is half-typed.
					if ( saved.length ) {
						const done = new Set( saved.map( ( row ) => row.id ) );

						setData( ( current ) =>
							current
								? {
										...current,
										items: current.items.filter(
											( item ) => ! done.has( item.id )
										),
										total: Math.max(
											0,
											current.total - saved.length
										),
								  }
								: current
						);
					}

					setAnnouncement(
						sprintf(
							/* translators: 1: how many were saved. 2: how many failed. */
							__(
								'%1$d saved, %2$d not saved.',
								'wowstudio-accessibility-kit'
							),
							saved.length,
							results.length - saved.length
						)
					);
				} )
				.catch(
					( err ) => live.current && setError( readableError( err ) )
				)
				.finally( () => live.current && setBusy( false ) );
		},
		[ drafts ]
	);

	if ( loading && ! data ) {
		return (
			<Skeleton
				label={ __(
					'Looking for undescribed images…',
					'wowstudio-accessibility-kit'
				) }
			/>
		);
	}

	const items = data?.items || [];
	const changedIds = Object.keys( drafts )
		.map( ( id ) => Number( id ) )
		.filter(
			( id ) =>
				'' !== ( drafts[ id ]?.text ?? '' ) || drafts[ id ]?.decorative
		);

	return (
		<section className="wsak-alt" aria-labelledby="wsak-alt-title">
			<h2 id="wsak-alt-title" className="wsak-alt__title">
				{ __(
					'Images without a description',
					'wowstudio-accessibility-kit'
				) }
			</h2>

			<p className="wsak-alt__lede">
				{ __(
					'Every image here has never been described. Images already marked decorative are left alone — an empty description is somebody’s decision, not a gap. What you write goes into WordPress’s own alt text, so it applies everywhere that image is used and stays behind if this plugin is removed.',
					'wowstudio-accessibility-kit'
				) }
			</p>

			<p className="screen-reader-text" role="status" aria-live="polite">
				{ announcement }
			</p>

			{ error && (
				<Notice
					status="error"
					isDismissible={ false }
					className="wsak-alt__error"
				>
					{ error }
				</Notice>
			) }

			{ ! items.length ? (
				<EmptyState
					title={ __(
						'Every image has been described',
						'wowstudio-accessibility-kit'
					) }
					body={ __(
						'Nothing in the media library is missing a description. That covers images this plugin can see in the library — it does not cover images added by a theme, a plugin, or a page builder that stores them elsewhere.',
						'wowstudio-accessibility-kit'
					) }
				/>
			) : (
				<>
					<div className="wsak-alt__bar">
						<p className="wsak-alt__count">
							{ sprintf(
								/* translators: %d: how many images still have no description. */
								_n(
									'%d image still needs a description.',
									'%d images still need a description.',
									data.total,
									'wowstudio-accessibility-kit'
								),
								data.total
							) }
						</p>

						<Button
							variant="primary"
							disabled={ ! changedIds.length || busy }
							onClick={ () => save( changedIds ) }
						>
							{ changedIds.length
								? sprintf(
										/* translators: %d: how many rows have been filled in. */
										_n(
											'Save %d description',
											'Save %d descriptions',
											changedIds.length,
											'wowstudio-accessibility-kit'
										),
										changedIds.length
								  )
								: __(
										'Save descriptions',
										'wowstudio-accessibility-kit'
								  ) }
						</Button>
					</div>

					<ul className="wsak-alt__list">
						{ items.map( ( item ) => (
							<ImageRow
								key={ item.id }
								item={ item }
								draft={ drafts[ item.id ] }
								onChange={ change }
								onSave={ save }
								busy={ busy }
							/>
						) ) }
					</ul>

					{ data.total_pages > 1 && (
						<nav
							className="wsak-alt__pages"
							aria-label={ __(
								'Pages of images',
								'wowstudio-accessibility-kit'
							) }
						>
							<Button
								variant="secondary"
								disabled={ page <= 1 || busy }
								onClick={ () => setPage( page - 1 ) }
							>
								{ __(
									'Previous',
									'wowstudio-accessibility-kit'
								) }
							</Button>

							<span className="wsak-alt__page">
								{ sprintf(
									/* translators: 1: current page. 2: how many pages. */
									__(
										'Page %1$d of %2$d',
										'wowstudio-accessibility-kit'
									),
									page,
									data.total_pages
								) }
							</span>

							<Button
								variant="secondary"
								disabled={ page >= data.total_pages || busy }
								onClick={ () => setPage( page + 1 ) }
							>
								{ __( 'Next', 'wowstudio-accessibility-kit' ) }
							</Button>
						</nav>
					) }
				</>
			) }
		</section>
	);
}
