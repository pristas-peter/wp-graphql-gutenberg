import { registerStore } from '@wordpress/data';

export default () =>
	registerStore( `wp-graphql-gutenberg/block-editor-preview`, {
		reducer(
			state = {
				blocksById: {},
			},
			action
		) {
			const { type, ...payload } = action;

			switch ( type ) {
				case `SET_BLOCKS`: {
					const { blocks, coreBlockId, id, ...rest } = payload;

					const stateById = state.blocksById[ action.id ] || {
						blocks: [],
						blocksByCoreBlockId: {},
					};

					if ( coreBlockId ) {
						return {
							...state,
							blocksById: {
								...state.blocksById,
								[ id ]: {
									...stateById,
									...rest,
									blocksByCoreBlockId: {
										...stateById.blocksByCoreBlockId,
										[ coreBlockId ]: blocks,
									},
								},
							},
						};
					}

					return {
						...state,
						blocksById: {
							...state.blocksById,
							[ id ]: {
								...stateById,
								...rest,
								blocks,
							},
						},
					};
				}
			}

			return state;
		},

		actions: {
			setBlocks( payload ) {
				return {
					...payload,
					type: `SET_BLOCKS`,
				};
			},
		},
		selectors: {
			getBlocksById: ( state ) => state.blocksById,
		},
	} );
