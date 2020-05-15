import { __ } from '@wordpress/i18n';
import { getBlockTypes } from '@wordpress/blocks';
import { useEffect } from '@wordpress/element';
import { withNotices } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const Plugin = withNotices( ( props ) => {
	const {
		noticeOperations: { createErrorNotice },
	} = props;

	useEffect( () => {
		apiFetch( {
			path: 'wp-graphql-gutenberg/v1/block-registry',
			method: 'POST',
			data: getBlockTypes(),
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
