import { __ } from '@wordpress/i18n';
import { select } from '@wordpress/data';

function getPostTypeRestBase() {
	const editor = select( 'core/editor' );

	if ( editor ) {
		const post = editor.getCurrentPost();
		const currentPostType = post && post.type;

		if ( currentPostType ) {
			const postType = select( 'core' ).getPostType( currentPostType );

			if ( postType ) {
				return postType.rest_base;
			}
		}
	}

	return null;
}

function testAutosave( { restBase, path, method } ) {
	return method === 'POST' && new RegExp( `^\/wp\/v\\d+\/${ restBase }\/(\\d+)\/autosaves` ).test( path );
}

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
