<?php

namespace WPGraphQLGutenberg\Rest;

if ( ! defined( 'WP_GRAPHQL_GUTENBERG_REST_FIELD_NAME' ) ) {
	define( 'WP_GRAPHQL_GUTENBERG_REST_FIELD_NAME', 'wp_graphql_gutenberg' );
}

use WPGraphQLGutenberg\Blocks\Registry;
use WPGraphQLGutenberg\Blocks\PostMeta;

class Rest {
	function __construct() {
		add_action('rest_api_init', function () {
			register_rest_route('wp-graphql-gutenberg/v1', '/blocks/batch', [
				'methods'             => 'POST',
				'callback'            => function ( $value ) {
					Registry::update_registry( Registry::normalize( $value['block_types'] ) );
					PostMeta::update_batch( $value['batch'], Registry::get_registry() );

					return (object) [];
				},
				'permission_callback' => function () {
					return current_user_can( 'edit_others_posts' );
				},
				'schema'              => [
					'$schema' => 'http://json-schema.org/draft-04/schema#',
					// The title property marks the identity of the resource.
					'title'   => 'Posts which support editor',
					'type'    => 'object',
					// 'properties' => [
					//     "success" => ["type" => "boolean"]
					// ]
				],
			]);
		});
	}
}
