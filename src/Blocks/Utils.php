<?php

namespace WPGraphQLGutenberg\Blocks;

require_once ABSPATH . 'wp-admin/includes/admin.php';

class Utils
{
	public static function visit_blocks($blocks, $callback)
	{
		return array_map(function ($block) use ($callback) {
			$inner_blocks = self::visit_blocks(
				$block['innerBlocks'],
				$callback
			);

			$visited_block = $callback($block);
			$visited_block['innerBlocks'] = $inner_blocks;

			return $visited_block;
		}, $blocks);
	}

	public static function get_editor_post_types()
	{
		return apply_filters(
			'graphql_gutenberg_editor_post_types',
			array_filter(get_post_types_by_support('editor'), function (
				$post_type
			) {
				return use_block_editor_for_post_type($post_type);
			})
		);
	}

	public static function apply_content_filters($content)
	{
		if (class_exists('Amazon_S3_And_CloudFront_Pro') || class_exists('Amazon_S3_And_CloudFront')) {
			$content = apply_filters('as3cf_filter_post_local_to_provider', $content);
		}

		return $content;
	}
}
