import { useSelect, useRegistry } from '@wordpress/data';
import { addFilter, removeFilter } from '@wordpress/hooks';
import { useEffect, useContext, createContext } from '@wordpress/element';

import { visitBlocks, createVisitor } from '../Server/server';

const CoreBlockContext = createContext( null );

export const registerBlockUUID = () => {
	addFilter( 'blocks.registerBlockType', 'wpGraphqlGutenberg.registerBlockType', ( blockType ) => {
		const result = {
			...blockType,
			attributes: {
				...blockType.attributes,
				wpGraphqlUUID: {
					type: 'string',
				},
			},
		};

		if ( result.deprecated ) {
			result.deprecated = result.deprecated.map( ( definition ) => {
				return {
					...definition,
					attributes: {
						...definition.attributes,
						wpGraphqlUUID: {
							type: 'string',
						},
					},
				};
			} );
		}

		return result;
	} );

	addFilter( 'blocks.getBlockAttributes', 'wpGraphqlGutenberg.getBlockAttributes', ( attributes ) => {
		if ( ! attributes.wpGraphqlUUID ) {
			attributes.wpGraphqlUUID = null;
		}

		return attributes;
	} );

	addFilter( 'editor.BlockEdit', 'wpGraphqlGutenberg.BlockEdit', ( Edit ) => {
		return ( props ) => {
			const registry = useRegistry();
			const blocks = registry.select( `core/block-editor` ).getBlocks();
			const coreBlock = useContext( CoreBlockContext );

			const postId = useSelect( ( select ) => select( `core/editor` ).getCurrentPostId(), [] );

			const coreBlockId =
				( coreBlock && coreBlock.attributes.ref && parseInt( coreBlock.attributes.ref, 10 ) ) || null;

			const id = coreBlockId || postId;

			const clientId = props.clientId;

			useEffect( () => {
				if ( ! props.attributes.wpGraphqlUUID ) {
					const filter = ( block ) => {
						if ( block.clientId === clientId ) {
							props.setAttributes( {
								wpGraphqlUUID: block.attributes.wpGraphqlUUID,
							} );
						}

						return block;
					};

					const namespace = `wpGraphqlGutenberg.BlockEdit.${ clientId }`;

					addFilter( 'wpGraphqlGutenberg.visitorBlock', namespace, filter );

					visitBlocks( {
						blocks,
						visitor: createVisitor( { id } ),
					} );

					removeFilter( 'wpGraphqlGutenberg.visitorBlock', namespace );
				}
			}, [ id, blocks, clientId ] );

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
