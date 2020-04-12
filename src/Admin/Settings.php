<?php

namespace WPGraphQLGutenberg\Admin;

use \WPGraphQLGutenberg\Admin\Editor;
use WPGraphQLGutenberg\Blocks\PostMeta;
use WPGraphQLGutenberg\Blocks\Utils;
use WPGraphQLGutenberg\PostTypes\BlockEditorPreview;

class Settings
{
    function is_stale($model)
    {
        $data = PostMeta::get_post($model->ID);

        return empty($data) || PostMeta::is_data_stale($model, $data);
    }

    function __construct()
    {
        add_action('admin_menu', function () {
            add_menu_page(
                __('GraphQL Gutenberg', 'wp-graphql-gutenberg'),
                'GraphQL Gutenberg',
                'manage_options',
                'wp-graphql-gutenberg-admin',
                function () {
                    echo '<div class="wrap"><div id="wp-graphql-gutenberg-admin"></div></div>';
                },
                'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0MDAgNDAwIj48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNNTcuNDY4IDMwMi42NmwtMTQuMzc2LTguMyAxNjAuMTUtMjc3LjM4IDE0LjM3NiA4LjN6Ii8+PHBhdGggZmlsbD0iI0UxMDA5OCIgZD0iTTM5LjggMjcyLjJoMzIwLjN2MTYuNkgzOS44eiIvPjxwYXRoIGZpbGw9IiNFMTAwOTgiIGQ9Ik0yMDYuMzQ4IDM3NC4wMjZsLTE2MC4yMS05Mi41IDguMy0xNC4zNzYgMTYwLjIxIDkyLjV6TTM0NS41MjIgMTMyLjk0N2wtMTYwLjIxLTkyLjUgOC4zLTE0LjM3NiAxNjAuMjEgOTIuNXoiLz48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNNTQuNDgyIDEzMi44ODNsLTguMy0xNC4zNzUgMTYwLjIxLTkyLjUgOC4zIDE0LjM3NnoiLz48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNMzQyLjU2OCAzMDIuNjYzbC0xNjAuMTUtMjc3LjM4IDE0LjM3Ni04LjMgMTYwLjE1IDI3Ny4zOHpNNTIuNSAxMDcuNWgxNi42djE4NUg1Mi41ek0zMzAuOSAxMDcuNWgxNi42djE4NWgtMTYuNnoiLz48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNMjAzLjUyMiAzNjdsLTcuMjUtMTIuNTU4IDEzOS4zNC04MC40NSA3LjI1IDEyLjU1N3oiLz48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNMzY5LjUgMjk3LjljLTkuNiAxNi43LTMxIDIyLjQtNDcuNyAxMi44LTE2LjctOS42LTIyLjQtMzEtMTIuOC00Ny43IDkuNi0xNi43IDMxLTIyLjQgNDcuNy0xMi44IDE2LjggOS43IDIyLjUgMzEgMTIuOCA0Ny43TTkwLjkgMTM3Yy05LjYgMTYuNy0zMSAyMi40LTQ3LjcgMTIuOC0xNi43LTkuNi0yMi40LTMxLTEyLjgtNDcuNyA5LjYtMTYuNyAzMS0yMi40IDQ3LjctMTIuOCAxNi43IDkuNyAyMi40IDMxIDEyLjggNDcuN00zMC41IDI5Ny45Yy05LjYtMTYuNy0zLjktMzggMTIuOC00Ny43IDE2LjctOS42IDM4LTMuOSA0Ny43IDEyLjggOS42IDE2LjcgMy45IDM4LTEyLjggNDcuNy0xNi44IDkuNi0zOC4xIDMuOS00Ny43LTEyLjhNMzA5LjEgMTM3Yy05LjYtMTYuNy0zLjktMzggMTIuOC00Ny43IDE2LjctOS42IDM4LTMuOSA0Ny43IDEyLjggOS42IDE2LjcgMy45IDM4LTEyLjggNDcuNy0xNi43IDkuNi0zOC4xIDMuOS00Ny43LTEyLjhNMjAwIDM5NS44Yy0xOS4zIDAtMzQuOS0xNS42LTM0LjktMzQuOSAwLTE5LjMgMTUuNi0zNC45IDM0LjktMzQuOSAxOS4zIDAgMzQuOSAxNS42IDM0LjkgMzQuOSAwIDE5LjItMTUuNiAzNC45LTM0LjkgMzQuOU0yMDAgNzRjLTE5LjMgMC0zNC45LTE1LjYtMzQuOS0zNC45IDAtMTkuMyAxNS42LTM0LjkgMzQuOS0zNC45IDE5LjMgMCAzNC45IDE1LjYgMzQuOSAzNC45IDAgMTkuMy0xNS42IDM0LjktMzQuOSAzNC45Ii8+PC9zdmc+'
            );
        });

        add_action('admin_enqueue_scripts', function ($hook) {
            if (!preg_match("/.+wp-graphql-gutenberg-admin$/", $hook)) {
                return;
            }

            wp_enqueue_style('wp-components');

            Editor::enqueue_script();

            wp_localize_script(
                Editor::$script_name,
                'wpGraphqlGutenberg',
                [
                    'adminPostType' => BlockEditorPreview::post_type(),
                    'adminUrl' => get_admin_url()
                ]
            );
        });

        add_action('rest_api_init', function () {
            register_rest_route(
                'wp-graphql-gutenberg/v1',
                '/stale-posts',
                array(
                    'methods' => 'GET',
                    'callback' => function () {
                        $args = [
                            'post_type' => array_merge(Utils::get_editor_post_types()),
                            'posts_per_page' => -1,
                            'post_status' => 'any'
                        ];

                        $query = new \WP_Query($args);

                        $data = [];

                        foreach ($query->get_posts() as $post) {
                            if ($this->is_stale($post)) {
                                $data[] = [
                                    'id' => $post->ID,
                                    'post_content' => $post->post_content
                                ];
                            }

                            foreach (wp_get_post_revisions($post) as $revision) {
                                if ($this->is_stale($revision)) {
                                    $data[] = [
                                        'id' => $revision->ID,
                                        'post_content' => $revision->post_content
                                    ];
                                }
                            }
                        }

                        return rest_ensure_response($data);
                    },
                    'permission_callback' => function () {
                        return apply_filters('graphql_gutenberg_user_can_update_stale_content',  in_array('administrator',  wp_get_current_user()->roles));
                    },
                    'schema' => [
                        '$schema' =>
                        'http://json-schema.org/draft-04/schema#',
                        // The title property marks the identity of the resource.
                        'title' => 'Posts which support editor',
                        'type' => 'array',
                        // In JSON Schema you can specify object properties in the properties attribute.
                        'items' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'id' => [
                                        'type' => 'integer'
                                    ],
                                    'post_content' => [
                                        'type' => 'string'
                                    ]
                                ]
                            ]
                        ]
                    ]
                )
            );
        });
    }
}
