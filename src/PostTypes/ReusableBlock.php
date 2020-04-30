<?php

namespace WPGraphQLGutenberg\PostTypes;

class ReusableBlock {
	public function __construct() {
		add_filter(
			'register_post_type_args',
			function ( $args, $post_type ) {
				if ( 'wp_block' === $post_type ) {
					$args['show_in_graphql']     = true;
					$args['graphql_single_name'] = 'ReusableBlock';
					$args['graphql_plural_name'] = 'ReusableBlocks';
				}

				return $args;
			},
			10,
			2
		);
	}
}
