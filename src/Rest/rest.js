import { __ } from '@wordpress/i18n';

export const disableAutosaveMiddleware = ( options, next ) => {
	const restBase = getPostTypeRestBase();

	if ( restBase ) {
		if ( testAutosave( { ...options, restBase } ) ) {
			return Promise.reject(
				new Error( __( 'Autosaves are disabled by wp-graphql-gutenberg.', 'wp-graphql-gutenberg' ) )
			);
		}
	}

	return next( options );
};
