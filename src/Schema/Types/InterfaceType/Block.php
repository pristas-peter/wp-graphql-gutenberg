<?php

namespace WPGraphQLGutenberg\Schema\Types\InterfaceType;

use WPGraphQLGutenberg\PostTypes\BlockEditorPreview;
use WPGraphQLGutenberg\Schema\Types\BlockTypes;
use WPGraphQLGutenberg\Schema\Utils;

class Block {
	private static function get_parent_id($id) {
		if (BlockEditorPreview::is_block_editor_preview($id)) {
			return BlockEditorPreview::get_previewed_post_id($id);
		}

		return $id;
	}

	function __construct() {
		add_action('graphql_register_types', function ($type_registry) {
			register_graphql_interface_type('Block', [
				'description' => __('Gutenberg block interface', 'wp-graphql-gutenberg'),
				'fields' => [
					'name' => [
						'type' => ['non_null' => 'String'],
						'description' => __('Name of the block.', 'wp-graphql-gutenberg')
					],
					'originalContent' => [
						'type' => 'String',
						'description' => __('Original HTML content.', 'wp-graphql-gutenberg')
					],
					'saveContent' => [
						'type' => 'String',
						'description' => __('Original HTML content with inner blocks.', 'wp-graphql-gutenberg')
					],
					'innerBlocks' => [
						'type' => [
							'list_of' => ['non_null' => 'Block']
						],
						'description' => __('Gutenberg blocks', 'wp-graphql-gutenberg'),
						'resolve' => function ($block, $args, $context, $info) {
							return $block->innerBlocks;
						}
					],
					'parentNode' => [
						'type' => ['non_null' => 'Node'],
						'description' => __('Parent post.', 'wp-graphql-gutenberg'),
						'resolve' => function ($block, $args, $context, $info) {
							$id = self::get_parent_id($block->postId);

							$resolver = Utils::get_post_resolver($id);
							return $resolver($id, $context);
						}
					],
					'parentNodeDatabaseId' => [
						'type' => ['non_null' => 'Int'],
						'description' => __('Parent post id.', 'wp-graphql-gutenberg'),
						'resolve' => function ($block) {
							return self::get_parent_id($block->postId);
						}
					],
					'isDynamic' => [
						'type' => ['non_null' => 'Boolean'],
						'description' => __('Is block rendered server side.', 'wp-graphql-gutenberg'),
						'resolve' => function ($block, $args, $context, $info) {
							return in_array($block->name, get_dynamic_block_names(), true);
						}
					],
					'dynamicContent' => [
						'type' => 'String',
						'description' => __('Server side rendered content.', 'wp-graphql-gutenberg'),
						'resolve' => function ($block, $args, $context, $info) {
							$registry = \WP_Block_Type_Registry::get_instance();
							$server_block_type = $registry->get_registered($block->name);

							if (empty($server_block_type)) {
								return null;
							}

							return $server_block_type->render($block->attributes);
						}
					],
					'order' => [
						'type' => ['non_null' => 'Int']
					]
				],
				'resolveType' => function ($block) use ($type_registry) {
					return $type_registry->get_type(BlockTypes::format_block_name($block->name));
				}
			]);
		});
	}
}
