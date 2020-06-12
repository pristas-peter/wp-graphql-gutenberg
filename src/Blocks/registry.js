import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import { withNotices } from '@wordpress/components';
import { registerPlugin } from '@wordpress/plugins';
import apiFetch from '@wordpress/api-fetch';

const Plugin = withNotices( ( props ) => {
	// importing getBlockTypes directly from `@wordpress/blocks` somehow breaks translations
	const { getBlockTypes } = window.wp.blocks;

	const {
		noticeOperations: { createErrorNotice },
	} = props;

	useEffect( () => {
		apiFetch( {
			path: 'wp-graphql-gutenberg/v1/block-registry',
			method: 'POST',
			data: {
				block_types: getBlockTypes(),
			},
		} ).catch( () => {
			createErrorNotice( __( 'Update of block types registry failed.', 'wp-graphql-gutenberg' ) );
		} );
	}, [] );

	return null;
} );

export const registerBlockRegistryUpdate = () => {
	registerPlugin( 'wp-graphql-gutenberg-block-registry-update', {
		render: Plugin,
	} );
};
