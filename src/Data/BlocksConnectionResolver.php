<?php

namespace WPGraphQLGutenberg\Data;

use WPGraphQL\Data\Connection\AbstractConnectionResolver;
use WPGraphQLGutenberg\Blocks\Block;
use WPGraphQLGutenberg\Blocks\Utils;

abstract class BlocksConnectionResolverType {
	const Root = 'Root';
	const Post = 'Post';
	const Block = 'Block';
}

class BlocksConnectionResolver extends AbstractConnectionResolver {
	public function __construct($source, $args, $context, $info, $type = BlocksConnectionResolverType::Post) {
		parent::__construct($source, $args, $context, $info);
		$this->type = $type;
	}

	public function get_loader_name() {
		return 'blocks';
	}

	public function get_query_args() {
		return [];
	}

	public function should_execute() {
		return true;
	}

	public function is_valid_offset($offset) {
		return true;
	}

	public function get_offset() {
		if (!empty($this->args['after'])) {
			return Block::decode_id(self::cursor_to_id($this->args['after']))['ID'];
		}

		if (!empty($this->args['before'])) {
			return Block::decode_id(self::cursor_to_id($this->args['before']))['ID'];
		}

		return 0;
	}

	public static function cursor_to_id($cursor) {
		$parts = explode(':', base64_decode($cursor));

		return $parts[1];
	}

	public function get_query() {
		return null;
	}

	public function get_ids() {
		$after = null;
		$before = null;

		if (!empty($this->args['after'])) {
			$after = self::cursor_to_id($this->args['after']);
		}

		if (!empty($this->args['before'])) {
			$before = self::cursor_to_id($this->args['before']);
		}

		$blocks = [];

		if ($this->type === BlocksConnectionResolverType::Post) {
			$blocks = $this->loader->get_post_blocks($this->source->ID);
		} elseif ($this->type === BlocksConnectionResolverType::Block) {
			$blocks = $this->source->innerBlocks;
		} else {
			$last = !empty($this->args['last']) ? $this->args['last'] : null;
			$first = !empty($this->args['first']) ? $this->args['first'] : null;
			$cursor_offset = $this->get_offset();

			$query_args['ignore_sticky_posts'] = true;
			$query_args['post_type'] = Utils::get_editor_post_types();
			$query_args['no_found_rows'] = true;
			$query_args['post_status'] = 'publish';
			$query_args['posts_per_page'] = min(max(absint($first), absint($last), 10), $this->query_amount) + 2;
			$query_args['graphql_cursor_offset'] = $cursor_offset;
			$query_args['graphql_cursor_compare'] = !empty($last) ? '>=' : '=<';
			$query_args['graphql_args'] = $this->args;
			$query_args['fields'] = 'ids';

			$query = new \WP_Query($query_args);
			$posts = $query->get_posts();

			foreach ($posts as $post) {
				Utils::visit_blocks($this->loader->get_post_blocks($post), function ($block) use (&$blocks) {
					$blocks[] = $block;
				});
			}
		}

		$ids = array_map(function ($block) {
			return $block->id;
		}, $blocks);

		if (!empty($after)) {
			do {
				$id = array_shift($ids);

				if ($id === $after) {
					break;
				}
			} while (count($ids));
		}

		if (!empty($before)) {
			$result = [];

			foreach ($ids as $id) {
				if ($id === $before) {
					break;
				}

				$result[] = $id;
			}

			$ids = $result;
		}

		if (!empty($this->args['last'])) {
			return array_reverse($ids);
		}

		return $ids;
	}

	protected function is_valid_model($model) {
		return true;
	}
}
