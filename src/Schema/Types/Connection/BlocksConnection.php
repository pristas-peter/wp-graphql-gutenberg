<?php

namespace WPGraphQLGutenberg\Schema\Types\Connection;

use WPGraphQLGutenberg\Data\BlocksConnectionResolver;
use WPGraphQLGutenberg\Data\BlocksConnectionResolverType;

class BlocksConnection {
	function __construct() {
		add_action('graphql_register_types', function ($type_registry) {
			register_graphql_connection([
				'fromType' => 'BlockEditorContentNode',
				'fromFieldName' => 'blocks',
				'toType' => 'BlockUnion',
				// 'connectionArgs' => self::get_connection_args(),
				'resolve' => function ($root, $args, $context, $info) {
					$resolver = new BlocksConnectionResolver(
						$root,
						$args,
						$context,
						$info,
						BlocksConnectionResolverType::Post
					);
					return $resolver->get_connection();
				}
			]);

			register_graphql_connection([
				'fromType' => 'Block',
				'fromFieldName' => 'innerBlocks',
				'toType' => 'BlockUnion',
				// 'connectionArgs' => self::get_connection_args(),
				'resolve' => function ($root, $args, $context, $info) {
					$resolver = new BlocksConnectionResolver(
						$root,
						$args,
						$context,
						$info,
						BlocksConnectionResolverType::Block
					);
					return $resolver->get_connection();
				}
			]);

			register_graphql_connection([
				'fromType' => 'RootQuery',
				'fromFieldName' => 'blockEditorContentNodeBlocks',
				'toType' => 'BlockUnion',
				// 'connectionArgs' => self::get_connection_args(),
				'resolve' => function ($root, $args, $context, $info) {
					$resolver = new BlocksConnectionResolver(
						$root,
						$args,
						$context,
						$info,
						BlocksConnectionResolverType::Root
					);
					return $resolver->get_connection();
				}
			]);
		});
	}
}
