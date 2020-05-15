import { unmountComponentAtNode } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { getBlockTypes, parse } from '@wordpress/blocks';
import { createVisitor as createUUIDVisitor } from '../blocks/uuid';

export const createVisitor = createUUIDVisitor;

export const IS_SERVER_PARAM = 'wpGraphqlGutenbergServer';

export const visitBlocks = ( { blocks = [], visitor } ) => {
	return blocks.map( ( block ) => {
		const innerBlocks = visitBlocks( {
			blocks: block.innerBlocks || [],
			visitor,
		} );

		const visited = visitor( block );
		visited.innerBlocks = innerBlocks;
		return applyFilters( 'wpGraphqlGutenberg.visitorBlock', visited, blocks );
	} );
};

// waits upon gutenberg initialization (block library)
// taken from wp-admin/edit-form-blocks.php
export const blockEditorReady = () => {
	return window._wpLoadBlockEditor;
};

// closes editor on admin page
// useful to turn off unexpected autoupdates of the opened post
export const closeEditor = () => {
	// inspired from https://github.com/WordPress/gutenberg/blob/master/packages/edit-post/src/index.js
	unmountComponentAtNode( document.querySelector( `#editor` ) );
};

// get block type registry
export const getBlockRegistry = () => {
	return JSON.parse(
		JSON.stringify(
			getBlockTypes().map( ( { icon, transforms, ...rest } ) => rest ) // eslint-disable-line no-unused-vars
		)
	);
};

// parse post content to blocks array
export const getBlocks = ( { postContent, id } ) => {
	return visitBlocks( {
		blocks: parse( postContent ),
		visitor: createVisitor( { id } ),
	} );
};

export const createBatch = ( { contentById } ) => {
	return {
		block_types: getBlockRegistry(),
		batch: Object.keys( contentById ).reduce( ( obj, id ) => {
			const postContent = contentById[ id ];

			const blocks = getBlocks( { postContent, id } );

			obj[ id ] = {
				blocks,
			};

			return obj;
		}, {} ),
	};
};
