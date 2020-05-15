<?php

namespace WPGraphQLGutenberg\Schema\Types\Object;

use WPGraphQLGutenberg\Blocks\Registry;
use WPGraphQLGutenberg\PostTypes\BlockEditorPreview;
use WPGraphQLGutenberg\Schema\Utils;

class ReusableBlock {
	function __construct() {
		add_filter('graphql_ReusableBlock_fields', function ($fields) {
			return array_merge($fields, [
				'previewBlocksFrom' => [
					'type' => [
						'list_of' => ['non_null' => 'BlockUnion']
					],
					'args' => [
						'databaseId' => [
							'type' => ['non_null' => 'Int']
						]
					],
					'description' => 'Previewed gutenberg blocks',
					'resolve' => Utils::ensure_capability(
						function ($model, $args) {
							if (
								!current_user_can(
									get_post_type_object(get_post_type($args['databaseId']))->cap->edit_posts
								)
							) {
								return null;
							}

							$id = BlockEditorPreview::get_preview_id($model->ID, $args['databaseId']);

							if (!empty($id)) {
								return PostMeta::get_post($id, Registry::get_registry())['blocks'];
							}

							return null;
						},
						function ($cap) {
							return $cap->edit_posts;
						}
					)
				],
				'previewBlocksFromJSON' => [
					'type' => 'String',
					'description' => 'Previewed gutenberg blocks as json string',
					'args' => [
						'databaseId' => [
							'type' => ['non_null' => 'Int']
						]
					],
					'resolve' => Utils::ensure_capability(
						function ($model, $args) {
							if (
								!current_user_can(
									get_post_type_object(get_post_type($args['databaseId']))->cap->edit_posts
								)
							) {
								return null;
							}

							$id = BlockEditorPreview::get_preview_id($model->ID, $args['databaseId']);

							if (!empty($id)) {
								return json_encode(PostMeta::get_post($id, Registry::get_registry())['blocks']);
							}

							return null;
						},
						function ($cap) {
							return $cap->edit_posts;
						}
					)
				]
			]);
		});
	}
}
