import { unmountComponentAtNode } from '@wordpress/element';
import { getBlockTypes, getSaveContent, parse } from '@wordpress/blocks';

export const IS_SERVER_PARAM = 'wpGraphqlGutenbergServer';

export const visitBlocks = ( { blocks, visitor } ) => {
	blocks.forEach( ( block ) => {
		visitor( block );

		if ( block.innerBlocks ) {
			visitBlocks( { blocks: block.innerBlocks, visitor } );
		}
	} );

	return blocks;
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

// get blocks rendered output with inner blocks included
export const getBlockSaveContent = ( { block } ) => {
	return getSaveContent( block.name, block.attributes, block.innerBlocks );
};

// parse post content to blocks array
export const getBlocks = ( { postContent } ) => {
	return visitBlocks( {
		blocks: parse( postContent ),
		visitor: ( block ) => {
			block.saveContent = getBlockSaveContent( { block } );
		},
	} );
};

export const createBatch = ( { contentById } ) => {
	return {
		block_types: getBlockRegistry(),
		batch: Object.keys( contentById ).reduce( ( obj, id ) => {
			const postContent = contentById[ id ];

			obj[ id ] = {
				blocks: getBlocks( { postContent } ),
				post_content: postContent,
			};

			return obj;
		}, {} ),
	};
};
