<?php

namespace WPGraphQLGutenberg\Schema;

use WPGraphQLGutenberg\Blocks\PostMeta;
use WPGraphQLGutenberg\Blocks\Registry;
use WPGraphQLGutenberg\PostTypes\BlockEditorPreview;
use WPGraphQLGutenberg\Schema\Utils;

class Schema
{
    private $type_registry;

    function __construct()
    {

        add_filter(
            'register_post_type_args',
            function ($args, $post_type) {
                if ($post_type === 'wp_block') {
                    $args['show_in_graphql'] = true;
                    $args['graphql_single_name'] = 'ReusableBlock';
                    $args['graphql_plural_name'] = 'ReusableBlocks';
                }

                return $args;
            },
            10,
            2
        );

        add_filter('graphql_ReusableBlock_fields', function ($fields) {
            return array_merge($fields, [
                'previewBlocksFrom' => [
                    'type' => [
                        'list_of' => 'Block'
                    ],
                    'args' => [
                        'databaseId' => [
                            'type' => ['non_null' => 'Int']
                        ]
                    ],
                    'description' => 'Gutenberg blocks as previewed',
                    'resolve' => BlockEditorPreview::ensure_current_user_can_read(function ($model, $args) {
                        $id = BlockEditorPreview::get_preview_id($model->ID, $args['databaseId']);

                        if (!empty($id)) {
                            return PostMeta::get_post($id)['blocks'];
                        }

                        return null;
                    })
                ],
                'previewBlocksJSONFrom' => [
                    'type' => 'String',
                    'description' => 'Gutenberg blocks as previewed as json string',
                    'args' => [
                        'databaseId' => [
                            'type' => ['non_null' => 'Int']
                        ]
                    ],
                    'resolve' => BlockEditorPreview::ensure_current_user_can_read(function ($model, $args) {
                        $id = BlockEditorPreview::get_preview_id($model->ID, $args['databaseId']);

                        if (!empty($id)) {
                            return json_encode(PostMeta::get_post($id)['blocks']);
                        }

                        return null;
                    })
                ]
            ]);
        });

        add_filter('graphql_wp_object_type_config', function (
            $config
        ) {
            if (
                in_array(
                    strtolower($config['name']),
                    array_map(
                        'strtolower',
                        Utils::get_editor_graphql_types()
                    )
                )
            ) {
                $interfaces = $config['interfaces'];

                $config['interfaces'] = function () use (
                    $interfaces
                ) {
                    return array_merge($interfaces(), [
                        $this->type_registry->get_type(
                            'BlockEditorContentNode'
                        )
                    ]);
                };

                $fields_cb = $config['fields'];

                $config['fields'] = function () use (
                    $fields_cb
                ) {
                    $fields = $fields_cb();
                    $type_fields = \WPGraphQLGutenberg\Schema\Types\InterfaceType\BlockEditorContentNode::get_config($this->type_registry)['fields'];

                    foreach ($type_fields as $key => $value) {
                        $fields[$key] = $this->type_registry
                            ->get_type(
                                'BlockEditorContentNode'
                            )
                            ->getField($key)->config;
                    }

                    return $fields;
                };
            }
            return $config;
        });

        add_action(
            'graphql_register_types',
            function ($type_registry) {
                $this->type_registry = $type_registry;

                \WPGraphQLGutenberg\Schema\Types\InterfaceType\Block::register_type($type_registry);
                \WPGraphQLGutenberg\Schema\Types\InterfaceType\BlockEditorContentNode::register_type($type_registry);
                \WPGraphQLGutenberg\Schema\Types\Connection\BlockEditorContentNodeConnection::register_type($type_registry);
                \WPGraphQLGutenberg\Schema\Types\BlockTypes::register_block_types(Registry::get_registry(), $type_registry);
            }
        );
    }
}
