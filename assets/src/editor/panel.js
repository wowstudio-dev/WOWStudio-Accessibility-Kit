/**
 * The sidebar that checks what you are writing, as you write it.
 */

import { getBlockContent } from '@wordpress/blocks';
import { Notice, PanelBody, Spinner } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect, useRef, useState } from '@wordpress/element';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/editor';
import { __, _n, sprintf } from '@wordpress/i18n';

import { checkBlocks, readableError } from './api';
import BlockFinding from './block-finding';

/**
 * How long the writing has to stop before anything is checked.
 *
 * Long enough that a sentence being typed is not checked six times on the way,
 * short enough that putting an image in and looking at the panel shows the
 * result rather than a stale one.
 */
const SETTLE_MS = 1200;

/**
 * The accessibility sidebar.
 *
 * @return {Element} The sidebar.
 */
export default function Panel() {
	const [ result, setResult ] = useState( null );
	const [ checking, setChecking ] = useState( false );
	const [ error, setError ] = useState( '' );

	const timer = useRef( null );
	const inFlight = useRef( 0 );

	// getBlocks() returns a new array on every store change, so this subscribes
	// to the serialized content instead. Otherwise the effect below would fire
	// on selection changes, cursor moves and every other editor event that
	// touches nothing we care about.
	const blocks = useSelect(
		( select ) => select( 'core/block-editor' ).getBlocks(),
		[]
	);

	const { selectBlock } = useDispatch( 'core/block-editor' );

	useEffect( () => {
		clearTimeout( timer.current );

		timer.current = setTimeout( () => {
			const payload = flatten( blocks );

			if ( ! payload.length ) {
				setResult( { blocks: [], total: 0 } );

				return;
			}

			const ticket = inFlight.current + 1;
			inFlight.current = ticket;

			setChecking( true );
			setError( '' );

			checkBlocks( payload )
				.then( ( data ) => {
					// A reply for content that has since changed is discarded.
					// Without this, a slow request can overwrite the result of a
					// faster later one and the panel shows findings for text
					// that is no longer there.
					if ( ticket !== inFlight.current ) {
						return;
					}

					setResult( data );
				} )
				.catch( ( caught ) => {
					if ( ticket === inFlight.current ) {
						setError( readableError( caught ) );
					}
				} )
				.finally( () => {
					if ( ticket === inFlight.current ) {
						setChecking( false );
					}
				} );
		}, SETTLE_MS );

		return () => clearTimeout( timer.current );
	}, [ blocks ] );

	const total = result?.total ?? 0;

	return (
		<>
			<PluginSidebarMoreMenuItem
				target="wsak-sidebar"
				icon="universal-access-alt"
			>
				{ __( 'Accessibility', 'wowstudio-accessibility-kit' ) }
			</PluginSidebarMoreMenuItem>

			<PluginSidebar
				name="wsak-sidebar"
				icon="universal-access-alt"
				title={ __( 'Accessibility', 'wowstudio-accessibility-kit' ) }
			>
				<PanelBody>
					{ error && (
						<Notice status="error" isDismissible={ false }>
							{ error }
						</Notice>
					) }

					<p className="wsak-editor__status">
						{ checking && <Spinner /> }
						{ null === result
							? __(
									'Checking what you have written…',
									'wowstudio-accessibility-kit'
							  )
							: sprintf(
									/* translators: %d: number of findings. */
									_n(
										'%d thing to look at.',
										'%d things to look at.',
										total,
										'wowstudio-accessibility-kit'
									),
									total
							  ) }
					</p>

					{ /*
					 * Said on every reply rather than only when nothing is
					 * found. A panel that goes quiet reads as "this page is
					 * fine", and what it actually means is "this checked what it
					 * can see from here".
					 */ }
					{ result?.scope && (
						<p className="wsak-editor__scope">{ result.scope }</p>
					) }

					{ result?.blocks?.map( ( block ) => (
						<BlockFinding
							key={ block.id }
							block={ block }
							onSelect={ () => selectBlock( block.id ) }
						/>
					) ) }
				</PanelBody>
			</PluginSidebar>
		</>
	);
}

/**
 * Flattens the block tree into what the server needs to check it.
 *
 * Inner blocks are walked, because a finding inside a column or a group belongs
 * to the block that actually holds it — attributing it to the container would
 * point somebody at a layout wrapper and leave them hunting.
 *
 * @param {Array} blocks Blocks from the editor store.
 * @return {Array} Flat list of block id and its own markup.
 */
function flatten( blocks ) {
	const out = [];

	const walk = ( list ) => {
		list.forEach( ( block ) => {
			if ( block.innerBlocks?.length ) {
				walk( block.innerBlocks );

				return;
			}

			const html = getBlockContent( block );

			if ( html && html.trim() ) {
				out.push( { id: block.clientId, html } );
			}
		} );
	};

	walk( blocks );

	return out;
}
