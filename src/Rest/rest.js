import { select, dispatch } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

import { createBatch } from '../Server/server';

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

function testPost( { path, method, restBase } ) {
	return (
		method === 'PUT' &&
		new RegExp( `^\/wp\/v\\d+\/${ restBase }\/\\d+` ).test( path )
	);
}

function testReusableBlock( { path, method } ) {
	return (
		( method === 'POST' || method === 'PUT' ) &&
		/\/wp\/v\d+\/blocks(\/\d+)*(\?.+)*$/.test( path )
	);
}

function testAutosave( { restBase, path, method } ) {
	return (
		method === 'POST' &&
		new RegExp( `^\/wp\/v\\d+\/${ restBase }\/(\\d+)\/autosaves` ).test(
			path
		)
	);
}

export const middleware = ( options, next ) => {
	const restBase = getPostTypeRestBase();

	if ( restBase ) {
		const { method, path } = options;

		if (
			testPost( { method, path, restBase } ) ||
			testReusableBlock( { method, path } ) ||
			testAutosave( { method, path, restBase } )
		) {
			return next( options ).then( ( response ) => {
				apiFetch( {
					method: 'POST',
					path: 'wp-graphql-gutenberg/v1/blocks/batch',
					data: createBatch( {
						contentById: { [ response.id ]: response.content.raw },
					} ),
				} ).catch( () =>
					dispatch( 'core/notices' ).createErrorNotice(
						sprintf(
							__(
								'Saving of parsed blocks failed for id %d.',
								'wp-graphql-gutenberg'
							),
							response.id
						)
					)
				);

				return response;
			} );
		}
	}

	return next( options );
};

export const disableAutosaveMiddleware = ( options, next ) => {
	const restBase = getPostTypeRestBase();

	if ( restBase ) {
		if ( testAutosave( { ...options, restBase } ) ) {
			return Promise.reject(
				new Error(
					__(
						'Autosaves are disabled by wp-graphql-gutenberg.',
						'wp-graphql-gutenberg'
					)
				)
			);
		}
	}

	return next( options );
};
