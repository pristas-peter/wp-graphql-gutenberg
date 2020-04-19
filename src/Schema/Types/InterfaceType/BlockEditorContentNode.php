<?php

namespace WPGraphQLGutenberg\Schema\Types\InterfaceType;

use WPGraphQLGutenberg\Schema\Utils;
use GraphQL\Error\ClientAware;
use WPGraphQLGutenberg\Blocks\PostMeta;
use WPGraphQLGutenberg\PostTypes\BlockEditorPreview;

class StaleContentException extends \Exception implements ClientAware
{
    public function isClientSafe()
    {
        return true;
    }

    public function getCategory()
    {
        return 'gutenberg';
    }
}

class BlockEditorContentNode
{
    private $type_registry;

    private static function ensure_not_stale($model, $data)
    {
        if (empty($data)) {
            throw new StaleContentException(__('Blocks content is not sourced.', 'wp-graphql-gutenberg'));
        }

        if (PostMeta::is_data_stale($model, $data)) {
            throw new StaleContentException(__('Blocks content is stale.', 'wp-graphql-gutenberg'));
        }

        return $data;
    }

    function __construct()
    {
        $fields = [
            'blocks' => [
                'type' => [
                    'list_of' => ['non_null' => 'Block']
                ],
                'description' => __('Gutenberg blocks', 'wp-graphql-gutenberg'),
                'resolve' => Utils::ensure_capability(function ($model) {
                    $data = self::ensure_not_stale($model, PostMeta::get_post($model->ID));
                    return $data['blocks'];
                }, function ($cap) {
                    return $cap->edit_posts;
                })
            ],
            'blocksJSON' => [
                'type' => 'String',
                'description' => __('Gutenberg blocks as json string', 'wp-graphql-gutenberg'),
                'resolve' => Utils::ensure_capability(function ($model) {
                    $data = self::ensure_not_stale($model, PostMeta::get_post($model->ID));;
                    return json_encode($data['blocks']);
                }, function ($cap) {
                    return $cap->edit_posts;
                })
            ],
            'previewBlocks' => [
                'type' => [
                    'list_of' => ['non_null' => 'Block']
                ],
                'description' => __('Previewed gutenberg blocks', 'wp-graphql-gutenberg'),
                'resolve' => Utils::ensure_capability(function ($model) {
                    $id = BlockEditorPreview::get_preview_id($model->ID, $model->ID);

                    if (!empty($id)) {
                        return PostMeta::get_post($id)['blocks'];
                    }

                    return null;
                }, function ($cap) {
                    return $cap->edit_posts;
                })
            ],
            'previewBlocksJSON' => [
                'type' => 'String',
                'description' => __('Previewed Gutenberg blocks as json string', 'wp-graphql-gutenberg'),
                'resolve' => Utils::ensure_capability(function ($model) {
                    $id = BlockEditorPreview::get_preview_id($model->ID, $model->ID);

                    if (!empty($id)) {
                        return json_encode(PostMeta::get_post($id)['blocks']);
                    }

                    return null;
                }, function ($cap) {
                    return $cap->edit_posts;
                })
            ]
        ];

        add_filter('graphql_wp_object_type_config', function (
            $config
        ) use ($fields) {
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
                    $fields_cb,
                    $fields
                ) {
                    $result = $fields_cb();

                    foreach ($fields as $key => $value) {
                        $result[$key] = $this->type_registry
                            ->get_type(
                                'BlockEditorContentNode'
                            )
                            ->getField($key)->config;
                    }

                    return $result;
                };
            }
            return $config;
        });

        add_action(
            'graphql_register_types',
            function ($type_registry) use ($fields) {

                $this->type_registry = $type_registry;

                register_graphql_interface_type(
                    'BlockEditorContentNode',
                    [
                        'description' => __(
                            'Gutenberg post interface',
                            'wp-graphql-gutenberg'
                        ),
                        'fields' => $fields,
                        'resolveType' => function ($model) use ($type_registry) {
                            return $type_registry->get_type(Utils::get_post_graphql_type($model, $type_registry));
                        }
                    ]
                );
            }
        );
    }
}
