<?php

namespace WPGraphQLGutenberg\Blocks;
use ArrayAccess;
use GraphQLRelay\Relay;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\Validator;
use voku\helper\HtmlDomParser;

class Block implements ArrayAccess {
	public static function create_blocks($blocks, $post_id, $registry, $parent = null) {
		$result = [];
		$order = 0;

		foreach ($blocks as $block) {
			if (empty($block['blockName'])) {

				if (json_encode($block['innerHTML']) === '"\n\n"') {
					continue;
				}

				$block['blockName'] = 'core/freeform';
			}

			$result[] = new Block($block, $post_id, $registry, $order, $parent);
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
					$source_node = !empty($value['selector']) ? $node->findOne($value['selector']) : $node;

					if ($source_node) {
						if (!empty($value['multiline'])) {
							$tag = $value['multiline'];

							$value = '';

							foreach ($source_node->childNodes as $childNode) {

								$childNode = new \voku\helper\SimpleHtmlDom($childNode);

								if (strtolower($childNode->tag) !== $tag) {
									continue;
								}

								$value = $value . $childNode->outerhtml;
							}
							
							$result[$key] = $value;
						} else {
							$result[$key] = $source_node->innerhtml;
						}

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
					$result[$key] = $node->tag;
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

	public function __construct($data, $post_id, $registry, $order, $parent) {
		$this->innerBlocks = self::create_blocks($data['innerBlocks'], $post_id, $registry, $this);

		$this->name = $data['blockName'];
		$this->postId = $post_id;
		$this->blockType = $registry[$this->name];
		$this->originalContent = self::strip_newlines($data['innerHTML']);
		$this->saveContent = self::parse_inner_content($data);
		$this->order = $order;
		$this->get_parent = function () use (&$parent) {
			return $parent;
		};

		$result = self::parse_attributes($data, $this->blockType);

		$this->attributes = $result['attributes'];
		$this->attributesType = $result['type'];
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
