<?php

namespace WPGraphQLGutenberg\Schema\Types\Connection;

use WPGraphQL\Connection\PostObjects;
use WPGraphQLGutenberg\Schema\Utils;

class BlockEditorContentNodeConnection {
	function __construct() {
		add_action('graphql_register_types', function ($type_registry) {
			register_graphql_connection([
				'fromType' => 'RootQuery',
				'toType' => 'BlockEditorContentNode',
				'fromFieldName' => 'blockEditorContentNodes',
				'connectionArgs' => PostObjects::get_connection_args(),
				'connectionTypeName' => 'BlockEditorContentNodeConnection',
				'resolve' => function ($id, $args, $context, $info) {
					$resolver = new \WPGraphQL\Data\Connection\PostObjectConnectionResolver(
						$id,
						$args,
						$context,
						$info,
						'post'
					);
					$resolver->setQueryArg('post_type', Utils::get_graphql_allowed_editor_post_types());
					$connection = $resolver->get_connection();
					return $connection;
				},
				'resolveNode' => function ($model, $args, $context, $info) {
					$id = $model->ID ?? $model;

					$resolver = Utils::get_post_resolver($id);
					return $resolver($id, $context);
				}
			]);
		});
	}
}
