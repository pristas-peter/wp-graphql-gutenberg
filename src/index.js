import apiFetch from '@wordpress/api-fetch';
// import data from '@wordpress/data';
// import { applyFilters } from '@wordpress/hooks';
import { render } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';

import Settings from './Admin/settings';
import { checkIframeAdmin } from './Admin/admin';
import { middleware, disableAutosaveMiddleware } from './Rest/rest';
import * as server from './Server/server';
import { registerBlockEditorPreview } from './PostTypes/block-editor-preview';

apiFetch.use( middleware );

const { IS_SERVER_PARAM } = server;

const params = new URLSearchParams( window.location.search );
const isServer = params.get( IS_SERVER_PARAM ) === 'true';
if ( isServer ) {
	if ( ! window.wp.wpGraphqlGutenberg ) {
		window.wp.wpGraphqlGutenberg = {};
	}

	window.wp.wpGraphqlGutenberg.server = server;
	apiFetch.use( disableAutosaveMiddleware );

	const { blockEditorReady, closeEditor } = server;

	domReady( () => {
		blockEditorReady().then( closeEditor );
		blockEditorReady().then( checkIframeAdmin );
	} );
} else {
	registerBlockEditorPreview();

	domReady( () => {
		const admin = document.getElementById( 'wp-graphql-gutenberg-admin' );

		if ( admin ) {
			render( <Settings />, admin );
		}
	} );
}
