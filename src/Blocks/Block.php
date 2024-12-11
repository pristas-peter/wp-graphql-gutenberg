<?php

namespace WPGraphQLGutenberg\Blocks;

use ArrayAccess;
use GraphQLRelay\Relay;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\Validator;
use voku\helper\HtmlDomParser;

class Block implements ArrayAccess {
	public $innerBlocks;
	public $name;
	public $postId;
	public $blockType;
	public $originalContent;
	public $saveContent;
	public $order;
	public $get_parent;
	public $attributes;
	public $attributesType;
	public $dynamicContent;

	public static function create_blocks( $blocks, $post_id, $registry, $parent = null ) {
		$result = [];
		$order = 0;
		foreach ( $blocks as $block ) {
			if ( empty( $block['blockName'] ) ) {
				if ( wp_json_encode( $block['innerHTML'] ) === '"\n\n"' ) {
					continue;
				}
				$block['blockName'] = 'core/freeform';
			}
			$result[] = new Block( $block, $post_id, $registry, $order, $parent );
			$order++;
		}
		return $result;
	}
	protected static function strip_newlines( $html ) {
		return preg_replace( '/^\n|\n$/', '', $html );
	}
	protected static function parse_inner_content( $data ) {
		$result = '';
		$index = 0;
		foreach ( $data['innerContent'] as $value ) {
			if ( null === $value ) {
				$result = $result . self::parse_inner_content( $data['innerBlocks'][ $index ] );
				$index++;
			} else {
				$result = $result . self::strip_newlines( $value );
			}
		}
		return $result;
	}
	protected static function source_attributes( $node, $type ) {
		$result = [];
		foreach ( $type as $key => $value ) {
			$source = $value['source'] ?? null;
			switch ( $source ) {
				case 'html':
					$source_node = ! empty( $value['selector'] ) ? $node->findOne( $value['selector'] ) : $node;
					if ( $source_node ) {
						if ( ! empty( $value['multiline'] ) ) {
							$tag = $value['multiline'];
							$value = '';
							foreach ( $source_node->childNodes as $childNode ) {
								$childNode = new \voku\helper\SimpleHtmlDom( $childNode );
								if ( strtolower( $childNode->tag ) !== $tag ) {
									continue;
								}
								$value = $value . $childNode->outerhtml;
							}
							$result[ $key ] = $value;
						} else {
							$result[ $key ] = $source_node->innerhtml;
						}
					}
					break;
				case 'attribute':
					$source_node = $value['selector'] ? $node->findOne( $value['selector'] ) : $node;
					if ( $source_node ) {
						$result[ $key ] = $source_node->getAttribute( $value['attribute'] );
					}
					break;
				case 'text':
					$source_node = $value['selector'] ? $node->findOne( $value['selector'] ) : $node;
					if ( $source_node ) {
						$result[ $key ] = $source_node->plaintext;
					}
					break;
				case 'tag':
					$result[ $key ] = $node->tag;
					break;
				case 'query':
					foreach ( $node->find( $value['selector'] ) as $source_node ) {
						$result[ $key ][] = self::source_attributes( $source_node, $value['query'] );
					}
					break;
				default:
				// @TODO: Throw exception
				// pass
			}
			if ( empty( $result[ $key ] ) && isset( $value['default'] ) ) {
				$result[ $key ] = $value['default'];
			}
		}
		return $result;
	}
	protected static function parse_attributes( $data, $block_type ) {
		$attributes = $data['attrs'];
		/**
		 * Filters the block attributes.
		 *
		 * @param array $attributes Block attributes.
		 * @param array $block_type Block type definition.
		 */
		$attributes = apply_filters( 'graphql_gutenberg_block_attributes', $attributes, $block_type );
		if ( null === $block_type ) {
			return [ 
				'attributes' => $attributes,
			];
		}
		$types = [ $block_type['attributes'] ];
		foreach ( $block_type['deprecated'] ?? [] as $deprecated ) {
			if ( ! empty( $deprecated['attributes'] ) ) {
				$types[] = $deprecated['attributes'];
			}
		}
		foreach ( $types as $type ) {
			// forked lines
			foreach ( $type as $key => $value ) {
				if ( $value['type'] === 'rich-text' ) {
					$type[ $key ]["source"] = "html";
					$type[ $key ]["type"] = "string";
				}
			}
			// forked ends here
			$schema = Schema::fromJsonString(
				wp_json_encode( [ 
					'type' => 'object',
					'properties' => $type,
					'additionalProperties' => false,
				] )
			);
			$validator = new Validator();
			// Convert $attributes to an object, handle both nested and empty objects.
			$attrs = empty( $attributes )
				? (object) $attributes
				: json_decode( wp_json_encode( $attributes ), false );
			$result = $validator->schemaValidation( $attrs, $schema );
			if ( $result->isValid() ) {
				// Avoid empty HTML, which can trigger an error on PHP 8.
				$html = empty( $data['innerHTML'] ) ? '<!-- -->' : $data['innerHTML'];
				return [ 
					'attributes' => array_merge(
						self::source_attributes( HtmlDomParser::str_get_html( $html ), $type ),
						$attributes
					),
					'type' => $type,
				];
			}
		}
		return [ 
			'attributes' => $attributes,
			'type' => $block_type['attributes'],
		];
	}
	public function __construct( $data, $post_id, $registry, $order, $parent ) {
		$inner_blocks = $data['innerBlocks'];
		// Handle reusable blocks.
		if ( 'core/block' === $data['blockName'] && isset( $data['attrs']['ref'] ) ) {
			$reusable_post = get_post( absint( $data['attrs']['ref'] ) );
			if ( ! empty( $reusable_post ) ) {
				$inner_blocks = parse_blocks( $reusable_post->post_content );
			}
		}
		$this->innerBlocks = self::create_blocks( $inner_blocks, $post_id, $registry, $this );
		$this->name = $data['blockName'];
		$this->postId = $post_id;
		$this->blockType = $registry[ $this->name ];
		$this->originalContent = self::strip_newlines( $data['innerHTML'] );
		$this->saveContent = self::parse_inner_content( $data );
		$this->order = $order;
		$this->get_parent = function () use (&$parent) {
			return $parent;
		};
		$result = self::parse_attributes( $data, $this->blockType );
		$this->attributes = $result['attributes'];
		$this->attributesType = $result['type'];
		$this->dynamicContent = $this->render_dynamic_content( $data );
	}
	private function render_dynamic_content( $data ) {
		$registry = \WP_Block_Type_Registry::get_instance();
		$server_block_type = $registry->get_registered( $this->name );
		if ( empty( $server_block_type ) || ! $server_block_type->is_dynamic() ) {
			return null;
		}
		return render_block( $data );
	}
	public function offsetExists( mixed $offset ): bool {
		return isset( $this->$offset );
	}
	public function offsetGet( mixed $offset ): mixed {
		return $this->$offset;
	}
	public function offsetSet( $offset, $value ): void {
		$this->$offset = $value;
	}
	public function offsetUnset( mixed $offset ): void {
		unset( $this->$offset );
	}
}
