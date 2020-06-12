/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useContext, createContext, useRef } from '@wordpress/element';
import { useRegistry, useDispatch, useSelect } from '@wordpress/data';
import { addFilter } from '@wordpress/hooks';
import apiFetch from '@wordpress/api-fetch';
import { registerPlugin } from '@wordpress/plugins';
import { debounce } from 'lodash';

import { getBlockRegistry } from '../../Server/server';
import registerStore from './store';

const CoreBlockContext = createContext( null );

const Plugin = () => {
	// importing getBlockTypes directly from `@wordpress/blocks` somehow breaks translations
	const { serialize } = window.wp.blocks;
	const { createErrorNotice } = useDispatch( 'core/notices' );

	const blocksById = useSelect( ( select ) => select( `wp-graphql-gutenberg/block-editor-preview` ).getBlocksById() );

	const { current: postBatch } = useRef(
		debounce( ( { batch } ) => {
			apiFetch( {
				method: 'POST',
				path: 'wp-graphql-gutenberg/v1/block-editor-previews/batch',
				data: {
					batch: Object.keys( batch ).reduce( ( obj, id ) => {
						const blocksByCoreBlockId = batch[ id ].blocksByCoreBlockId;

						obj[ id ] = {
							blocks: serialize( batch[ id ].blocks ),

							blocksByCoreBlockId: Object.keys( blocksByCoreBlockId ).reduce(
								( blocksByCoreBlockIdObj, coreBlockId ) => {
									blocksByCoreBlockIdObj[ coreBlockId ] = serialize(
										blocksByCoreBlockId[ coreBlockId ]
									);

									return blocksByCoreBlockIdObj;
								},
								{}
							),
						};

						return obj;
					}, {} ),
					block_types: getBlockRegistry(),
				},
				parse: false,
			} ).catch( () => {
				createErrorNotice( __( 'Saving of preview blocks failed.', 'wp-graphql-gutenberg' ) );
			} );
		}, 500 ),
		[]
	);

	useEffect( () => {
		const batch = blocksById;
		postBatch( { batch } );
	}, [ blocksById, postBatch ] );

	return null;
};

export const registerBlockEditorPreview = () => {
	registerStore();

	registerPlugin( 'wp-graphql-gutenberg-block-editor-preview', {
		render: Plugin,
	} );

	addFilter( `editor.BlockEdit`, `wp-graphql-gutenberg/block-editor-preview.BlockEdit`, ( Edit ) => {
		return ( props ) => {
			const { setBlocks } = useDispatch( `wp-graphql-gutenberg/block-editor-preview` );

			const registry = useRegistry();
			const blocks = registry.select( `core/block-editor` ).getBlocks();
			const coreBlock = useContext( CoreBlockContext );

			const id = useSelect( ( select ) => select( `core/editor` ).getCurrentPostId(), [] );

			const coreBlockId =
				( coreBlock && coreBlock.attributes.ref && parseInt( coreBlock.attributes.ref, 10 ) ) || null;

			useEffect( () => {
				if ( id ) {
					setBlocks( {
						id,
						blocks,
						coreBlockId,
					} );
				}
			}, [ blocks, coreBlockId, id ] );

			if ( props.name === `core/block` ) {
				return (
					<CoreBlockContext.Provider value={ props }>
						<Edit { ...props }></Edit>
					</CoreBlockContext.Provider>
				);
			}

			return <Edit { ...props } />;
		};
	} );
};
