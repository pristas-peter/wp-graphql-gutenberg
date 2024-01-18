<?php

namespace WPGraphQLGutenberg\Schema\Types;

use WPGraphQLGutenberg\Blocks\Registry;
use WPGraphQLGutenberg\Schema\Types\Scalar\Scalar;
use WPGraphQLGutenberg\Schema\Utils;

class BlockTypes {
	public static function format_block_name( $block_name ) {
		$name = implode(
			array_map(function ( $val ) {
				return ucfirst( $val );
			}, preg_split( '/(\/|\?|_|=|-)/', $block_name ))
		);

		if ( preg_match( '/Block$/', $name ) ) {
			return $name;
		}

		return $name . 'Block';
	}

	public static function format_attributes( $prefix ) {
		return $prefix . 'Attributes';
	}

	protected static function get_query_type( $name, $query, $prefix ) {
		$type = $prefix . ucfirst( $name );

		$fields = self::create_attributes_fields( $query, $type );

		register_graphql_object_type($type, [
			'fields' => $fields,
		]);

		return $type;
	}

	protected static function get_attribute_type( $name, $attribute, $prefix ) {
		$type = null;

		if ('templateLock' == $name) {
			return $type;
		}
		
		if ( isset( $attribute['type'] ) ) {
			switch ( $attribute['type'] ) {
				case 'string':
					$type = 'String';
					break;
				case 'boolean':
					$type = 'Boolean';
					break;
				case 'number':
					$type = 'Float';
					break;
				case 'integer':
					$type = 'Int';
					break;
				case 'array':
					if ( isset( $attribute['query'] ) ) {
						$type = [ 'list_of' => self::get_query_type( $name, $attribute['query'], $prefix ) ];
					} elseif ( isset( $attribute['items'] ) ) {
						$of_type = self::get_attribute_type( $name, $attribute['items'], $prefix );

						if ( null !== $of_type ) {
							$type = [ 'list_of' => $of_type ];
						} else {
							$type = Scalar::BlockAttributesArray();
						}
					} else {
						$type = Scalar::BlockAttributesArray();
					}
					break;
				case 'object':
					$type = Scalar::BlockAttributesObject();
					break;
			}
		} elseif ( isset( $attribute['source'] ) ) {
			$type = 'String';
		}

		if ( null !== $type ) {
			$default_value = $attribute['default'] ?? null;

			if ( isset( $default_value ) ) {
				$type = [ 'non_null' => $type ];
			}
		} elseif ( WP_DEBUG ) {
			// phpcs:ignore
			trigger_error( sprintf( __( 'Could not determine type of attribute "%1$s" in "%2$s"', 'wp-graphql-gutenberg' ), esc_html( $name ), esc_html( $prefix ) ), E_USER_WARNING );
		}

		return $type;
	}

	protected static function create_attributes_fields( $attributes, $prefix ) {
		$fields = [];

		foreach ( $attributes as $name => $attribute ) {
			$type = self::get_attribute_type( $name, $attribute, $prefix );

			if ( isset( $type ) ) {
				$default_value = $attribute['default'] ?? null;

				$fields[ self::normalize_attribute_name( $name ) ] = [
					'type'    => $type,
					'resolve' => function ( $attributes, $args, $context, $info ) use ( $name, $default_value ) {
						$value = $attributes[ $name ] ?? $default_value;
						return self::normalize_attribute_value( $value, $attributes['__type'][ $name ]['type'] );
					},
				];
			}
		}

		return $fields;
	}

	protected static function normalize_attribute_name( $name ) {
		return lcfirst( str_replace( ' ', '', ucwords( str_replace( [ '-', '_' ], ' ', $name ) ) ) );
	}

	protected static function normalize_attribute_value( $value, $type ) {
		switch ( $type ) {
			case 'string':
				return (string) $value;
			case 'number':
				return (float) $value;
			case 'boolean':
				return (bool) $value;
			case 'integer':
				return (int) $value;
			default:
				return $value;
		}
	}

	protected static function register_attributes_types( $block_type, $prefix ) {
		$definitions = [];

		if ( count( $block_type['attributes'] ) ) {
			array_push( $definitions, $block_type['attributes'] );
		}

		if ( isset( $block_type['deprecated'] ) ) {
			foreach ( array_reverse( $block_type['deprecated'] ) as $deprecation ) {
				if ( isset( $deprecation['attributes'] ) ) {
					array_push( $definitions, $deprecation['attributes'] );
				}
			}
		}

		if ( ! count( $definitions ) ) {
			return null;
		}

		$types                         = [];
		$types_by_definition           = [];
		$non_deprecated_definition_key = null;

		foreach ( $definitions as $index => $definition ) {
			$type = self::format_attributes( 0 === $index ? $prefix : $prefix . 'DeprecatedV' . $index );

			$fields = apply_filters(
				'graphql_gutenberg_block_attributes_fields',
				self::create_attributes_fields( $block_type['attributes'], $type ),
				$definition,
				$block_type
			);

			if ( ! is_array( $fields ) || empty( $fields ) ) {
				continue;
			}

			register_graphql_object_type( $type, [
				'fields'          => $fields,
				'eagerlyLoadType' => true,
			]);

			$types[] = $type;

			$key = wp_json_encode( $definition );

			if ( $key !== $non_deprecated_definition_key ) {
				$types_by_definition[ $key ] = $type;
			}

			if ( 0 === $index ) {
				$non_deprecated_definition_key = $key;
			}
		}

		if ( count( $types ) > 1 ) {
			$type = self::format_attributes( $prefix ) . 'Union';

			register_graphql_union_type($type, [
				'typeNames'   => $types,
				'resolveType' => function ( $attributes ) use ( $types_by_definition ) {

					return $types_by_definition[ wp_json_encode( $attributes['__type'] ) ];

				},
			]);

			return $type;
		}

		return $types[0];
	}

	protected static function register_block_type( $block_type, $type_registry ) {
		$name = self::format_block_name( $block_type['name'] );

		$fields = [];

		$type = self::register_attributes_types( $block_type, $name );

		if ( $type ) {
			$fields['attributes'] = [
				'type'    => $type,
				'resolve' => function ( $block ) {
					return array_merge( $block->attributes, [ '__type' => $block->attributesType ] );
				},
			];
		}

		/**
		 * Filters the fields for block type.
		 *
		 * @param array    $fields           Fields config.
		 * @param array    $block_type       Block type definition.
		 */
		$fields = apply_filters( 'graphql_gutenberg_block_type_fields', $fields, $block_type, $type_registry );

		register_graphql_object_type($name, [
			'fields'          => $fields,
			'description'     => $block_type['name'] . ' block',
			'interfaces'      => [ 'Block' ],
			'eagerlyLoadType' => true,
		]);

		return $name;
	}

	public function __construct() {
		add_action('graphql_register_types', function ( $type_registry ) {
			add_filter('graphql_CoreBlock_fields', function ( $fields ) {
				$fields['reusableBlock'] = [
					'type'    => [ 'non_null' => 'Node' ],
					'resolve' => function ( $source, $args, $context, $info ) {
						$id      = $source['attributes']['ref'];
						$resolve = Utils::get_post_resolver( $id );

						return $resolve( $id, $context );
					},
				];

				return $fields;
			});

			$type_names = [];

			$registry = Registry::get_registry();

			foreach ( $registry as $block_name => $block_type ) {
				$type_names[] = self::register_block_type( $block_type, $type_registry );
			}

			register_graphql_union_type('BlockUnion', [
				'typeNames'   => $type_names,
				'resolveType' => function ( $block ) use ( $type_registry ) {
					return self::format_block_name( $block->name );
				},
			]);

		});
	}
}
