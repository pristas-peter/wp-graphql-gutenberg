<?php

namespace WPGraphQLGutenberg\Blocks;

if (!defined('WP_GRAPHQL_GUTENBERG_REGISTRY_OPTION_NAME')) {
    define('WP_GRAPHQL_GUTENBERG_REGISTRY_OPTION_NAME', 'wp_graphql_gutenberg_block_types');
}

class Registry
{

    public static function update_registry($block_types)
    {

        $registry = array_reduce(
            $block_types,
            function (&$arr, $block_type) {
                $arr[$block_type['name']] = $block_type;
                return $arr;
            },
            []
        );

        update_option(
            WP_GRAPHQL_GUTENBERG_REGISTRY_OPTION_NAME,
            $registry,
            false
        );

        return $registry;
    }

    public static function get_registry()
    {
        return get_option(WP_GRAPHQL_GUTENBERG_REGISTRY_OPTION_NAME) ?? null;
    }

    public static function delete_registry()
    {
        return delete_option(WP_GRAPHQL_GUTENBERG_REGISTRY_OPTION_NAME);
    }
}
