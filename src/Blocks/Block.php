<?php

namespace WPGraphQLGutenberg\Blocks;
use ArrayAccess;
use GraphQLRelay\Relay;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\Validator;
use voku\helper\HtmlDomParser;

class Block implements ArrayAccess {
	public static function create_blocks($blocks, $post_id, $registry, $parent = null, $uuid_generator = null) {
		$result = [];
		$order = 0;

		$uuid_generator_instance = $uuid_generator ?? new UUIDGenerator($post_id);

		foreach ($blocks as $block) {
			if (empty($block['blockName'])) {
				continue;
			}

			$result[] = new Block($block, $post_id, $registry, $order, $parent, $uuid_generator_instance);
			$order++;
		}

		return $result;
	}

	protected static function strip_newlines($html) {
		return preg_replace('/^\n|\n$/', '', $html);
	}

	protected static function parse_inner_content($data) {
		$result = '';
		$index = 0;

		foreach ($data['innerContent'] as $value) {
			if ($value === null) {
				$result = $result . self::parse_inner_content($data['innerBlocks'][$index]);
				$index++;
			} else {
				$result = $result . self::strip_newlines($value);
			}
		}

		return $result;
	}

	protected static function source_attributes($node, $type) {
		$result = [];

		foreach ($type as $key => $value) {
			$source = $value['source'] ?? null;

			switch ($source) {
				case 'html':
					$source_node = $value['selector'] ? $node->findOne($value['selector']) : $node;

					if ($source_node) {
						$result[$key] = $source_node->innerhtml;
					}

					break;
				case 'attribute':
					$source_node = $value['selector'] ? $node->findOne($value['selector']) : $node;

					if ($source_node) {
						$result[$key] = $source_node->getAttribute($value['attribute']);
					}
					break;
				case 'text':
					$source_node = $value['selector'] ? $node->findOne($value['selector']) : $node;

					if ($source_node) {
						$result[$key] = $source_node->plaintext;
					}
					break;
				case 'tag':
					$result[$key] = $source_node->tag;
					break;

				case 'query':
					foreach ($node->find($value['selector']) as $source_node) {
						$result[$key][] = self::source_attributes($source_node, $value['query']);
					}
					break;

				default:
				// @TODO: Throw exception
				// pass
			}

			if (empty($result[$key]) && isset($value['default'])) {
				$result[$key] = $value['default'];
			}
		}

		return $result;
	}

	protected static function parse_attributes($data, $block_type) {
		$types = [$block_type['attributes']];
		$attributes = $data['attrs'];

		foreach ($block_type['deprecated'] ?? [] as $deprecated) {
			if (!empty($deprecated['attributes'])) {
				$types[] = $deprecated['attributes'];
			}
		}

		foreach ($types as $type) {
			$schema = Schema::fromJsonString(
				json_encode([
					'type' => 'object',
					'properties' => $type,
					'additionalProperties' => false
				])
			);

			$validator = new Validator();

			$result = $validator->schemaValidation((object) $attributes, $schema);

			if ($result->isValid()) {
				return [
					'attributes' => array_merge(
						self::source_attributes(HtmlDomParser::str_get_html($data['innerHTML']), $type),
						$attributes
					),
					'type' => $type
				];
			}
		}

		return [
			'attributes' => $attributes,
			'type' => $block_type['attributes']
		];
	}

	public function __construct($data, $post_id, $registry, $order, $parent, $uuid_generator) {
		$this->innerBlocks = self::create_blocks($data['innerBlocks'], $post_id, $registry, $this, $uuid_generator);

		$this->name = $data['blockName'];
		$this->postId = $post_id;
		$this->blockType = $registry[$this->name];
		$this->originalContent = self::strip_newlines($data['innerHTML']);
		$this->saveContent = self::parse_inner_content($data);
		$this->order = $order;
		$this->parent = $parent;

		$result = self::parse_attributes($data, $this->blockType);

		$this->attributes = $result['attributes'];
		$this->attributesType = $result['type'];

		if (empty($this->attributes['wpGraphqlUUID'])) {
			$this->attributes['wpGraphqlUUID'] = $uuid_generator->create_uuid();
		}

		$this->id = Relay::toGlobalId(
			'block',
			get_post_type($this->postId) . ':' . $this->postId . ':' . $this->attributes['wpGraphqlUUID']
		);
	}

	public static function decode_id($id) {
		$result = Relay::fromGlobalId($id);
		$parts = explode(':', $result['id']);

		return [
			'type' => $parts[0],
			'ID' => $parts[1],
			'UUID' => $parts[2]
		];
	}

	public function offsetExists($offset) {
		return isset($this->$offset);
	}

	public function offsetGet($offset) {
		return $this->$offset;
	}

	public function offsetSet($offset, $value) {
		$this->$offset = $value;
	}

	public function offsetUnset($offset) {
		unset($this->$offset);
	}
}
