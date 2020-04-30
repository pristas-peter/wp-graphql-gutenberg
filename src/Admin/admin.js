import { __ } from '@wordpress/i18n';
import { getBlocks, getBlockRegistry } from '../Server/server';

export const actions = {
	HEARTBEAT: 'HEARTBEAT',
	PARSE_BATCH: 'PARSE_BATCH',
};

export const checkIframeAdmin = () => {
	const admin = window.frameElement && window.frameElement.admin;
	if ( admin ) {
		admin.queue.forEach( ( { action, options, onError, onComplete } ) => {
			try {
				switch ( action ) {
					case actions.HEARTBEAT:
						onComplete();
						break;
					case actions.PARSE_BATCH:
						onComplete( {
							batch: options.data.reduce(
								// eslint-disable-next-line camelcase
								( batch, { id, post_content } ) => {
									batch[ id ] = {
										post_content,
										blocks: getBlocks( {
											postContent: post_content,
										} ),
									};

									return batch;
								},
								{}
							),
							block_types: getBlockRegistry(),
						} );

						break;
					default:
						onError(
							new Error(
								__( 'Invalid action', 'wp-graphql-gutenberg' )
							)
						);
				}
			} catch ( error ) {
				onError( error );
			}
		} );
	}
};
