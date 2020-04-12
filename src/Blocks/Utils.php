<?php

namespace WPGraphQLGutenberg\Blocks;

require_once ABSPATH . 'wp-admin/includes/admin.php';

class Utils
{

    public static function visit_blocks($blocks, $callback)
    {
        return array_map(function ($block) use ($callback) {
            $visited_block = $callback($block);
            $visited_block['innerBlocks'] = self::visit_blocks($visited_block['innerBlocks'], $callback);

            return $visited_block;
        }, $blocks);
    }

    public static function get_editor_post_types()
    {
        return apply_filters('graphql_gutenberg_editor_post_types', array_filter(get_post_types_by_support('editor'), function (
            $post_type
        ) {
            return use_block_editor_for_post_type($post_type);
        }));
    }
}
