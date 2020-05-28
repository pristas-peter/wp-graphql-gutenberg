<?php

namespace WPGraphQLGutenberg\Rest;

if (!defined('WP_GRAPHQL_GUTENBERG_REST_FIELD_NAME')) {
	define('WP_GRAPHQL_GUTENBERG_REST_FIELD_NAME', 'wp_graphql_gutenberg');
}

use WPGraphQLGutenberg\Blocks\Registry;

class Rest {
	function __construct() {
		add_action('rest_api_init', function () {
			register_rest_route('wp-graphql-gutenberg/v1', '/block-registry', [
				'methods' => 'POST',
				'callback' => function ($request) {
					Registry::update_registry(Registry::normalize($request->get_param('block_types')));
					return Registry::get_registry();
				},
				'permission_callback' => function () {
					return current_user_can('edit_others_posts');
				},
				'schema' => [
					'$schema' => 'http://json-schema.org/draft-04/schema#',
					'title' => 'Blocks registry',
					'type' => 'object'
				]
			]);
		});
	}
}
