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

    private static function current_user_has_caps($model)
    {
        return current_user_can(get_post_type_object($model->post_type)->cap->edit_posts);
    }

    public static function get_config($type_registry)
    {
        return             [
            'description' => __(
                'Gutenberg post interface',
                'wp-graphql-gutenberg'
            ),
            'fields' => [
                'blocks' => [
                    'type' => [
                        'list_of' => 'Block'
                    ],
                    'description' => __('Gutenberg blocks', 'wp-graphql-gutenberg'),
                    'resolve' => function ($model) {
                        if (!self::current_user_has_caps($model)) {
                            return null;
                        }

                        $data = self::ensure_not_stale($model, \WPGraphQLGutenberg\Blocks\PostMeta::get_post($model->ID));
                        return $data['blocks'];
                    }
                ],
                'blocksJSON' => [
                    'type' => 'String',
                    'description' => __('Gutenberg blocks as json string', 'wp-graphql-gutenberg'),
                    'resolve' => function ($model) {
                        if (!self::current_user_has_caps($model)) {
                            return null;
                        }

                        $data = self::ensure_not_stale($model, \WPGraphQLGutenberg\Blocks\PostMeta::get_post($model->ID));;
                        return json_encode($data['blocks']);
                    }
                ],
                'previewBlocks' => [
                    'type' => [
                        'list_of' => 'Block'
                    ],
                    'description' => __('Previewed gutenberg blocks', 'wp-graphql-gutenberg'),
                    'resolve' => function ($model, $args, $context, $info) {
                        if (!self::current_user_has_caps($model)) {
                            return null;
                        }

                        $id = BlockEditorPreview::get_preview_id($model->ID, $model->ID);

                        if (!empty($id)) {
                            return PostMeta::get_post($id)['blocks'];
                        }

                        return null;
                    })
                ],
                'previewBlocksJSON' => [
                    'type' => 'String',
                    'description' => __('Previewed Gutenberg blocks as json string', 'wp-graphql-gutenberg'),
                    'resolve' => function ($model) {
                        if (!self::current_user_has_caps($model)) {
                            return null;
                        }

                        $id = BlockEditorPreview::get_preview_id($model->ID, $model->ID);

                        if (!empty($id)) {
                            return json_encode(PostMeta::get_post($id)['blocks']);
                        }

                        return null;
                    })
                ]
            ],
            'resolveType' => function ($model) use ($type_registry) {
                return $type_registry->get_type(Utils::get_post_graphql_type($model, $type_registry));
            }
        ];
    }

    public static function register_type($type_registry)
    {
        register_graphql_interface_type(
            'BlockEditorContentNode',
            self::get_config($type_registry)
        );
    }
}
