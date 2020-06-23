<?php

namespace WPGraphQLGutenberg\PostTypes;

use WP_Query;
use WP_REST_Request;
use WPGraphQL\Utils\Utils;
use WPGraphQLGutenberg\Blocks\Block;
use WPGraphQLGutenberg\Blocks\Registry;
use WPGraphQLGutenberg\Schema\Utils as SchemaUtils;

if (!defined('WP_GRAPHQL_GUTENBERG_PREVIEW_POST_TYPE_NAME')) {
	define('WP_GRAPHQL_GUTENBERG_PREVIEW_POST_TYPE_NAME', 'wgg_preview');
}

if (!defined('WP_GRAPHQL_GUTENBERG_PREVIEW_GRAPHQL_SINGLE_NAME')) {
	define('WP_GRAPHQL_GUTENBERG_PREVIEW_GRAPHQL_SINGLE_NAME', 'BlockEditorPreview');
}

if (!defined('WP_GRAPHQL_GUTENBERG_PREVIEW_GRAPHQL_PLURAL_NAME')) {
	define('WP_GRAPHQL_GUTENBERG_PREVIEW_GRAPHQL_PLURAL_NAME', 'BlockEditorPreviews');
}

class BlockEditorPreview {
	public static function post_type() {
		return WP_GRAPHQL_GUTENBERG_PREVIEW_POST_TYPE_NAME;
	}

	public static function is_block_editor_preview($id) {
		return get_post_type($id) === WP_GRAPHQL_GUTENBERG_PREVIEW_POST_TYPE_NAME;
	}

	public static function get_previewed_post_id($id) {
		return get_post_meta($id, 'post_id', true);
	}

	public static function get_preview_id($post_id, $preview_post_id) {
		$query = new WP_Query([
			'meta_query' => [
				[
					'key' => 'post_id',
					'value' => $post_id
				],
				[
					'key' => 'preview_post_id',
					'value' => $preview_post_id
				]
			],
			'post_type' => WP_GRAPHQL_GUTENBERG_PREVIEW_POST_TYPE_NAME,
			'post_status' => 'auto-draft',
			'fields' => 'ids'
		]);

		$posts = $query->get_posts();

		if (!empty($posts)) {
			return $posts[0] ?? null;
		}

		return null;
	}

	public static function insert_preview($post_id, $preview_post_id, $post_content) {
		$post_title = $post_id;
		$post_name = $post_id;

		if ($post_id !== $preview_post_id) {
			$post_title = $post_id . ' (' . $preview_post_id . ')';
			$post_name = $post_id . '-' . $preview_post_id;
		}

		$insert_options = [
			'post_title' => $post_title,
			'post_name' => $post_name,
			'post_content' => $post_content,
			'post_type' => WP_GRAPHQL_GUTENBERG_PREVIEW_POST_TYPE_NAME,
			'post_status' => 'auto-draft',
			'meta_input' => [
				'post_id' => $post_id,
				'preview_post_id' => $preview_post_id
			]
		];

		$id = self::get_preview_id($post_id, $preview_post_id);

		if ($id) {
			$insert_options['ID'] = $id;
		}

		return wp_insert_post($insert_options, true);
	}

	public function __construct() {
		add_filter('graphql_gutenberg_editor_post_types', function ($post_types) {
			return array_filter($post_types, function ($post_type) {
				return WP_GRAPHQL_GUTENBERG_PREVIEW_POST_TYPE_NAME !== $post_type;
			});
		});

		add_filter('graphql_RootMutation_fields', function ($config) {
			$keys = [];

			foreach ($config as $key => $value) {
				if (strpos($key, WP_GRAPHQL_GUTENBERG_PREVIEW_GRAPHQL_SINGLE_NAME) !== false) {
					$keys[] = $key;
				}
			}

			foreach ($keys as $key) {
				unset($config[$key]);
			}

			return $config;
		});

		add_filter(
			'graphql_RootQueryTo' . WP_GRAPHQL_GUTENBERG_PREVIEW_GRAPHQL_SINGLE_NAME . 'ConnectionWhereArgs_fields',
			function ($fields, $type) {
				$fields['previewedDatabaseId'] = [
					'type' => 'Int'
				];

				$fields['previewedParentDatabaseId'] = [
					'type' => 'Int'
				];

				return $fields;
			},
			10,
			2
		);

		add_filter(
			'graphql_post_object_connection_query_args',
			function ($query_args, $source, $args, $context, $info) {
				if (lcfirst(WP_GRAPHQL_GUTENBERG_PREVIEW_GRAPHQL_PLURAL_NAME) === $info->fieldName) {
					if (isset($args['where']['previewedDatabaseId'])) {
						if (!isset($query_args['meta_query'])) {
							$query_args['meta_query'] = [];
						}

						$query_args['meta_query'][] = [
							'key' => 'post_id',
							'value' => $args['where']['previewedDatabaseId']
						];
					}

					if (isset($args['where']['previewedParentDatabaseId'])) {
						if (!isset($query_args['meta_query'])) {
							$query_args['meta_query'] = [];
						}

						$query_args['meta_query'][] = [
							'key' => 'preview_post_id',
							'value' => $args['where']['previewedParentDatabaseId']
						];
					}

					if (
						current_user_can(
							get_post_type_object(WP_GRAPHQL_GUTENBERG_PREVIEW_POST_TYPE_NAME)->cap->edit_posts
						)
					) {
						$query_args['post_status'] = 'auto-draft';
					} else {
						$query_args['post_status'] = 'publish';
					}
				}

				return $query_args;
			},
			10,
			5
		);

		add_action('init', function () {
			register_post_type(WP_GRAPHQL_GUTENBERG_PREVIEW_POST_TYPE_NAME, [
				'public' => false,
				'labels' => [
					'name' => __('Previews', 'wp-graphql-gutenberg')
				],
				'show_in_rest' => true,
				'rest_base' => 'wp-graphql-gutenberg-previews',
				'show_ui' => true,
				'publicly_queryable' => false,
				'exclude_from_search' => true,
				'show_in_menu' => false,
				'show_in_nav_menus' => false,
				'show_in_admin_bar' => false,
				'show_in_graphql' => true,
				'graphql_single_name' => WP_GRAPHQL_GUTENBERG_PREVIEW_GRAPHQL_SINGLE_NAME,
				'graphql_plural_name' => WP_GRAPHQL_GUTENBERG_PREVIEW_GRAPHQL_PLURAL_NAME,
				'supports' => ['title', 'custom-fields', 'author', 'editor']
			]);
		});

		add_action('rest_api_init', function () {
			register_rest_route('wp-graphql-gutenberg/v1', '/block-editor-previews/batch', [
				'methods' => 'POST',
				'callback' => function (WP_REST_Request $request) {
					Registry::update_registry(Registry::normalize($request->get_param('block_types')));
					$registry = Registry::get_registry();

					$batch = $request->get_param('batch');

					foreach ($batch as $post_id => $data) {
						foreach ($data['blocksByCoreBlockId'] as $core_block_id => $post_content) {
							$result = self::insert_preview($core_block_id, $post_id, $post_content, $registry);

							if (is_wp_error($result)) {
								return $result;
							}
						}

						$result = self::insert_preview($post_id, $post_id, $data['blocks'], $registry);

						if (is_wp_error($result)) {
							return $result;
						}

						return [
							'batch' => [
								// TODO: Add created/updated entitites to response
							]
						];
					}
				},
				'permission_callback' => function () {
					return current_user_can(
						get_post_type_object(WP_GRAPHQL_GUTENBERG_PREVIEW_POST_TYPE_NAME)->cap->edit_posts
					);
				}
			]);
		});

		add_action('graphql_register_types', function ($type_registry) {
			register_graphql_field(WP_GRAPHQL_GUTENBERG_PREVIEW_GRAPHQL_SINGLE_NAME, 'previewed', [
				'type' => 'BlockEditorContentNode',
				'resolve' => function ($model, $args, $context, $info) {
					$id = get_post_meta($model->ID, 'post_id', true);
					$resolver = SchemaUtils::get_post_resolver($id);
					return $resolver($id, $context);
				}
			]);

			register_graphql_field(WP_GRAPHQL_GUTENBERG_PREVIEW_GRAPHQL_SINGLE_NAME, 'blocks', [
				'type' => ['list_of' => ['non_null' => 'Block']],
				'resolve' => function ($model) {
					return Block::create_blocks(
						parse_blocks(get_post($model->ID)->post_content),
						$model->ID,
						Registry::get_registry()
					);
				}
			]);

			register_graphql_field(WP_GRAPHQL_GUTENBERG_PREVIEW_GRAPHQL_SINGLE_NAME, 'previewedDatabaseId', [
				'type' => 'Int',
				'resolve' => function ($model) {
					return get_post_meta($model->ID, 'post_id', true);
				}
			]);

			register_graphql_field(WP_GRAPHQL_GUTENBERG_PREVIEW_GRAPHQL_SINGLE_NAME, 'previewedParentDatabaseId', [
				'type' => 'Int',
				'resolve' => function ($model) {
					return get_post_meta($model->ID, 'preview_post_id', true);
				}
			]);

			register_graphql_field(WP_GRAPHQL_GUTENBERG_PREVIEW_GRAPHQL_SINGLE_NAME, 'blocksJSON', [
				'type' => 'String',
				'resolve' => function ($model) {
					return json_encode(
						Block::create_blocks(
							parse_blocks(get_post($model->ID)->post_content),
							$model->ID,
							Registry::get_registry()
						)
					);
				}
			]);

			register_graphql_field(WP_GRAPHQL_GUTENBERG_PREVIEW_GRAPHQL_SINGLE_NAME, 'lastUpdateTime', [
				'type' => 'String',
				'resolve' => function ($model) {
					return Utils::prepare_date_response(get_post($model->ID)->post_modified_gmt) . 'Z';
				}
			]);
		});
	}
}
