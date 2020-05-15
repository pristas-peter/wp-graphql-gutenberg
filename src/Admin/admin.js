import { __ } from '@wordpress/i18n';
import { getBlockRegistry } from '../Server/server';

export const actions = {
	HEARTBEAT: 'HEARTBEAT',
	GET_BLOCK_REGISTRY: 'GET_BLOCK_REGISTRY',
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
					case actions.GET_BLOCK_REGISTRY:
						onComplete( getBlockRegistry() );
						break;
					default:
						onError( new Error( __( 'Invalid action', 'wp-graphql-gutenberg' ) ) );
				}
			} catch ( error ) {
				onError( error );
			}
		} );
	}
};
