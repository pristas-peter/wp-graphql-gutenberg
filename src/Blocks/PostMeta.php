<?php

namespace WPGraphQLGutenberg\Blocks;

use WPGraphQLGutenberg\Blocks\Utils;


if (!defined('WP_GRAPHQL_GUTENBERG_DATA_META_FIELD_NAME')) {
    define('WP_GRAPHQL_GUTENBERG_DATA_META_FIELD_NAME', 'wp_graphql_gutenberg_data');
}

class PostMeta
{
    public static function is_data_stale($model, $data)
    {
        if ($data['post_content'] !== get_post($model->ID)->post_content) {
            return true;
        }

        return false;
    }


    public static function prepare_meta($id, $post_content, $blocks, $registry)
    {
        return [
            'post_content' => $post_content,
            'blocks' => Utils::visit_blocks($blocks, function ($block) use ($id, $blocks, $post_content, $registry) {
                $block['__type'] =  $registry[$block['name']];
                $block['__post_id'] = $id;
                return apply_filters('graphql_gutenberg_prepare_block_meta', $block, $blocks, $post_content);
            }),
            'registry' => $registry
        ];
    }

    public static function update_post($id, $post_content, $blocks, $registry)
    {

        $meta = wp_slash(json_encode(self::prepare_meta(
            $id,
            $post_content,
            $blocks,
            $registry
        )));

        if ($id === 1) {
            $a = $meta;
        }

        update_metadata(
            'post',
            $id,
            WP_GRAPHQL_GUTENBERG_DATA_META_FIELD_NAME,
            $meta
        );
    }

    public static function get_post($id)
    {

        $meta = get_metadata(
            'post',
            $id,
            WP_GRAPHQL_GUTENBERG_DATA_META_FIELD_NAME,
            true
        );

        if ($meta) {
            return json_decode($meta, true);
        }

        return null;
    }

    public static function update_batch($batch, $registry)
    {
        foreach ($batch as $id => $data) {
            self::update_post($id, $data['post_content'], $data['blocks'], $registry);
        }
    }
}
