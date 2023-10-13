import apiFetch from '@wordpress/api-fetch';
import { render } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';

import Settings from './Admin/settings';
import { checkIframeAdmin } from './Admin/admin';
import { disableAutosaveMiddleware } from './Rest/rest';
import * as server from './Server/server';
// import { registerBlockEditorPreview } from './PostTypes/block-editor-preview';
// import { registerBlockRegistryUpdate } from './Blocks/registry';

const { IS_SERVER_PARAM } = server;

const params = new URLSearchParams(window.location.search);
const isServer = params.get(IS_SERVER_PARAM) === 'true';
if (isServer) {
	if (!window.wp.wpGraphqlGutenberg) {
		window.wp.wpGraphqlGutenberg = {};
	}

	window.wp.wpGraphqlGutenberg.server = server;
	apiFetch.use(disableAutosaveMiddleware);

	const { blockEditorReady, closeEditor } = server;

	domReady(() => {
		blockEditorReady().then(closeEditor);
		blockEditorReady().then(checkIframeAdmin);
	});
} else {
	/**
	 * Disable these to avoid the block registry to be updated
	 * when opening the block editor.
	 *
	 * If we do not have the same registered blocks per post types
	 * the registry will be updated without all the available blocks.
	 *
	 * ⚠️ Disabling this, means that you have to manually hit the
	 *   "Update block registry" button in the settings page.
	 */
	// registerBlockRegistryUpdate();
	// registerBlockEditorPreview();

	domReady(() => {
		const admin = document.getElementById('wp-graphql-gutenberg-admin');

		if (admin) {
			render(<Settings />, admin);
		}
	});
}
