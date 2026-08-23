/**
 * Choosing something to scan.
 */

import { Button, SearchControl } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { fetchScannable, readableError } from '../api';
import { Busy, EmptyState, ErrorState, Skeleton } from './states';

/**
 * Lists content to scan, with whatever is already known about each item.
 *
 * @param {Object}   props          Component props.
 * @param {Function} props.onScan   Called with a post id when a scan starts.
 * @param {Function} props.onOpen   Called with a scan id to open a stored scan.
 * @param {number}   props.scanning Post id currently being scanned, or 0.
 * @param {boolean}  props.canScan  Whether this user may start a scan.
 * @return {Element} The picker.
 */
export default function ScanPicker( { onScan, onOpen, scanning, canScan } ) {
	const [ search, setSearch ] = useState( '' );
	const [ state, setState ] = useState( {
		status: 'loading',
		items: [],
		error: '',
	} );

	useEffect( () => {
		let cancelled = false;

		setState( ( previous ) => ( { ...previous, status: 'loading' } ) );

		const timer = setTimeout(
			() => {
				fetchScannable( { search } )
					.then( ( data ) => {
						if ( ! cancelled ) {
							setState( {
								status: 'ready',
								items: data.items ?? [],
								error: '',
							} );
						}
					} )
					.catch( ( error ) => {
						if ( ! cancelled ) {
							setState( {
								status: 'error',
								items: [],
								error: readableError( error ),
							} );
						}
					} );
			},
			search ? 300 : 0
		);

		return () => {
			cancelled = true;
			clearTimeout( timer );
		};
	}, [ search ] );

	return (
		<section className="wsak-picker" aria-labelledby="wsak-picker-title">
			<h2 className="wsak-picker__title" id="wsak-picker-title">
				{ __( 'Scan a page', 'wowstudio-accessibility-kit' ) }
			</h2>

			<SearchControl
				__nextHasNoMarginBottom
				label={ __(
					'Search your content',
					'wowstudio-accessibility-kit'
				) }
				placeholder={ __(
					'Search by title',
					'wowstudio-accessibility-kit'
				) }
				value={ search }
				onChange={ setSearch }
			/>

			{ state.status === 'loading' && (
				<Skeleton
					label={ __(
						'Loading your content…',
						'wowstudio-accessibility-kit'
					) }
					rows={ 4 }
				/>
			) }

			{ state.status === 'error' && (
				<ErrorState
					message={ state.error }
					onRetry={ () => setSearch( ( value ) => `${ value }` ) }
				/>
			) }

			{ state.status === 'ready' && ! state.items.length && (
				<EmptyState
					title={
						search
							? __(
									'Nothing matched that search',
									'wowstudio-accessibility-kit'
							  )
							: __(
									'No published content yet',
									'wowstudio-accessibility-kit'
							  )
					}
					body={
						search
							? __(
									'Try a different word, or clear the search to see everything.',
									'wowstudio-accessibility-kit'
							  )
							: __(
									'Publish a post or page and it will appear here, ready to scan.',
									'wowstudio-accessibility-kit'
							  )
					}
				/>
			) }

			{ state.status === 'ready' && state.items.length > 0 && (
				<table className="wsak-table">
					<caption className="screen-reader-text">
						{ __(
							'Your published content, with the result of the last scan.',
							'wowstudio-accessibility-kit'
						) }
					</caption>
					<thead>
						<tr>
							<th scope="col">
								{ __( 'Title', 'wowstudio-accessibility-kit' ) }
							</th>
							<th scope="col">
								{ __(
									'Last scan',
									'wowstudio-accessibility-kit'
								) }
							</th>
							<th scope="col">
								<span className="screen-reader-text">
									{ __(
										'Actions',
										'wowstudio-accessibility-kit'
									) }
								</span>
							</th>
						</tr>
					</thead>
					<tbody>
						{ state.items.map( ( item ) => (
							<tr key={ item.id }>
								<th scope="row" className="wsak-table__title">
									{ item.title ||
										__(
											'(no title)',
											'wowstudio-accessibility-kit'
										) }
									<span className="wsak-table__type">
										{ item.type }
									</span>
								</th>
								<td>
									{ item.last_scan ? (
										<Button
											variant="link"
											onClick={ () =>
												onOpen( item.last_scan.scan_id )
											}
										>
											{ sprintf(
												/* translators: %d: score out of 100. */
												__(
													'Scored %d out of 100',
													'wowstudio-accessibility-kit'
												),
												item.last_scan.score ?? 0
											) }
										</Button>
									) : (
										<span className="wsak-table__never">
											{ __(
												'Not scanned yet',
												'wowstudio-accessibility-kit'
											) }
										</span>
									) }
								</td>
								<td className="wsak-table__action">
									{ scanning === item.id ? (
										<Busy
											label={ __(
												'Scanning…',
												'wowstudio-accessibility-kit'
											) }
										/>
									) : (
										<Button
											variant="primary"
											disabled={
												! canScan || scanning > 0
											}
											onClick={ () => onScan( item.id ) }
											aria-label={ sprintf(
												/* translators: %s: content title. */
												__(
													'Scan “%s”',
													'wowstudio-accessibility-kit'
												),
												item.title
											) }
										>
											{ __(
												'Scan',
												'wowstudio-accessibility-kit'
											) }
										</Button>
									) }
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</section>
	);
}
