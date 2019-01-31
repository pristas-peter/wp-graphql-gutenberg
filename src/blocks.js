const {__} = wp.i18n;

// /**
//  * Register: aa Gutenberg Block.
//  *
//  * Registers a new block provided a unique name and an object defining its
//  * behavior. Once registered, the block is made editor as an option to any
//  * editor interface where blocks are implemented.
//  *
//  * @link https://wordpress.org/gutenberg/handbook/block-api/
//  * @param  {string}   name     Block name.
//  * @param  {Object}   settings Block settings.
//  * @return {?WPBlock}          The block, if it has been successfully
//  *                             registered; otherwise `undefined`.
//  */
// wp.blocks.registerBlockType( 'cgb/block-test', {
// 	// Block name. Block names must be string that contains a namespace prefix. Example: my-plugin/my-custom-block.
// 	title: __( 'test - CGB Block' ), // Block title.
// 	icon: 'shield', // Block icon from Dashicons → https://developer.wordpress.org/resource/dashicons/.
// 	category: 'common', // Block category — Group blocks together based on common traits E.g. common, formatting, layout widgets, embed.
// 	keywords: [
// 		__( 'test — CGB Block' ),
// 		__( 'CGB Example' ),
// 		__( 'create-guten-block' ),
// 	],

// 	/**
// 	 * The edit function describes the structure of your block in the context of the editor.
// 	 * This represents what the editor will render when the block is used.
// 	 *
// 	 * The "edit" property must be a valid function.
// 	 *
// 	 * @link https://wordpress.org/gutenberg/handbook/block-api/block-edit-save/
// 	 */
// 	edit: function( props ) {
// 		// Creates a <p class='wp-block-cgb-block-test'></p>.
// 		return (
// 			<div className={ props.className }>
// 				<p>— Hello from the backend.</p>
// 				<p>
// 					CGB BLOCK: <code>test</code> is a new Gutenberg block
// 				</p>
// 				<p>
// 					It was created via{ ' ' }
// 					<code>
// 						<a href="https://github.com/ahmadawais/create-guten-block">
// 							create-guten-block
// 						</a>
// 					</code>.
// 				</p>
// 			</div>
// 		);
//     },
    
//     attributes: {
//         content: {
//             type: 'string',
//             default: '22',
//         }
//     },

//     deprecated: [{
//         attributes: {
//             content: {
//                 type: 'number',
//                 default: 2,
//             }
//         },
//         migrate( ) {
//             console.log('migrate2', arguments);

//             return {
//                 content: 2
//             };
//         },

//         save: function( props ) {
//             return (
//                 <div>
//                     {props.attributes.content}
//                 </div>
//             );
//         },
//     }, {
//         attributes: {
//             content: {
//                 type: 'string',
//                 default: 'some random value',
//             }
//         },

//         migrate( ) {
//             console.log('migrate');

//             return {
//                 content: 3
//             };
//         },

//         save: function( props ) {
//             return (
//                 <div>
//                     <p>— Hello from the frontend.</p>
//                     <p>
//                         CGB BLOCK: <code>test</code> is a new Gutenberg block.
//                     </p>
//                     <p>
//                         It was created via{ ' ' }
//                         <code>
//                             <a href="https://github.com/ahmadawais/create-guten-block">
//                                 create-guten-block
//                             </a>
//                         </code>.
//                     </p>
//                 </div>
//             );
//         },
//     }],

// 	/**
// 	 * The save function defines the way in which the different attributes should be combined
// 	 * into the final markup, which is then serialized by Gutenberg into post_content.
// 	 *
// 	 * The "save" property must be specified and must be a valid function.
// 	 *
// 	 * @link https://wordpress.org/gutenberg/handbook/block-api/block-edit-save/
// 	 */
// 	save: function( props ) {
// 		return (
// 			<div>
// 				{props.attributes.content}
// 			</div>
// 		);
// 	},
// } );


function getBlockTypesForSerialization() {
    return wp.blocks.getBlockTypes().map(blockType => lodash.omit(blockType, ['transforms', 'icon']));
}

function getReusableBlocks(blocks, obj = {}) {
    const promises = [];

    blocks.forEach(block => {
        if (block.name === 'core/block') {
            const id = block.attributes.ref;

            promises.push(wp.apiFetch({path: `/wp/v2/blocks/${id}`})
                .then(wp_block => {
                    obj[id] = wp.blocks.parse(wp_block.content.raw).pop();
                }));
        }

        promises.push(getReusableBlocks(block.innerBlocks, obj));

    });

    return Promise.all(promises)
        .then(() => obj);
}

wp.apiFetch.use( ( options, next ) => {
    if (options.method === 'PUT') {
        if (options.data.content) {
            const post_content_blocks = wp.blocks.parse(options.data.content);

            return getReusableBlocks(post_content_blocks)
                .then(reusable_blocks => {
                    Object.assign(options.data, {
                        wp_graphql_gutenberg: {
                            post_content_blocks,
                            reusable_blocks,
                            block_types: getBlockTypesForSerialization(),
                        },
                    });

                    return next(options);
                });
        }
    }
    
    return next(options);

});
