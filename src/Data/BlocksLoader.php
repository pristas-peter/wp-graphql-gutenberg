<?php

namespace WPGraphQLGutenberg\Data;

use GraphQLRelay\Relay;
use WPGraphQL\Data\Loader\AbstractDataLoader;
use WPGraphQLGutenberg\Blocks\Block;
use WPGraphQLGutenberg\Blocks\Registry;
use WPGraphQLGutenberg\Blocks\Utils;

class BlocksLoader extends AbstractDataLoader {
	public function get_post_blocks($id) {
		static $cache = [];

		if (!empty($cache[$id])) {
			return $cache[$id];
		}

		$blocks = Block::create_blocks(parse_blocks(get_post($id)->post_content), $id, Registry::get_registry());
		$cache[$id] = $blocks;

		return $blocks;
	}

	public function get_id($id) {
		static $cache = [];

		if (!empty($cache[$id])) {
			return $id;
		}

		$decoded = Block::decode_id($id);

		$context = [
			'block' => null
		];

		Utils::visit_blocks($this->get_post_blocks($decoded['ID']), function ($block) use (&$decoded, &$context) {
			if ($block->attributes['wpGraphqlUUID'] === $decoded['UUID']) {
				$context['block'] = $block;
			}
		});

		$cache[$id] = $context['block'];

		return $context['block'];
	}

	public function __construct($registry) {
		$this->registry = $registry;
	}

	public function loadKeys(array $keys) {
		$result = [];

		foreach ($keys as $key) {
			$result[$key] = $this->get_id($key);
		}

		return $result;
	}
}

// class EnqueuedScriptLoader extends AbstractDataLoader {
// 	public function loadKeys( array $keys ) {
// 		global $wp_scripts;
// 		$loaded = [];
// 		foreach ( $keys as $key ) {
// 			if ( isset( $wp_scripts->registered[ $key ] ) ) {
// 				$loaded[ $key ] = $wp_scripts->registered[ $key ];
// 			} else {
// 				$loaded[ $key ] = null;
// 			}
// 		}
// 		return $loaded;
// 	}
// }
