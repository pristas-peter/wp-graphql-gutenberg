<?php

namespace WPGraphQLGutenberg\Blocks;

class BlocksJSON {
	public static function sanitize_filtered_properties( $value ) {
		$properties = [];

		if ( is_string( $value ) ) {
			$properties = preg_split( '/[\r\n,]+/', $value );
		} elseif ( is_array( $value ) ) {
			$properties = $value;
		}

		return array_values(
			array_unique(
				array_filter(
					array_map(
						function ( $property ) {
							return trim( sanitize_text_field( $property ) );
						},
						$properties
					),
					function ( $property ) {
						return '' !== $property;
					}
				)
			)
		);
	}

	public static function filter_properties( $value, $properties ) {
		if ( empty( $properties ) ) {
			return $value;
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				if ( is_string( $key ) && in_array( $key, $properties, true ) ) {
					unset( $value[ $key ] );
					continue;
				}

				$value[ $key ] = self::filter_properties( $item, $properties );
			}

			return $value;
		}

		if ( is_object( $value ) ) {
			foreach ( get_object_vars( $value ) as $key => $item ) {
				if ( in_array( $key, $properties, true ) ) {
					unset( $value->$key );
					continue;
				}

				$value->$key = self::filter_properties( $item, $properties );
			}
		}

		return $value;
	}

	public static function encode_blocks( $blocks, $model ) {
		/**
		 * Filters the list of block properties to remove before encoding blocks as JSON.
		 *
		 * Return an array of property names, or a comma/newline-delimited string of property names,
		 * that should be removed recursively from the blocks payload before it is encoded.
		 *
		 * @param array|string $properties Property names to remove from the blocks payload.
		 * @param mixed        $model      The model associated with the blocks being encoded.
		 * @param mixed        $blocks     The original blocks payload before properties are removed.
		 */
		$properties      = apply_filters( 'graphql_gutenberg_blocks_json_filtered_properties', [], $model, $blocks );
		$filtered_blocks = self::filter_properties( $blocks, self::sanitize_filtered_properties( $properties ) );

		return wp_json_encode(
			/**
			 * Filters the blocks payload before it is JSON encoded.
			 *
			 * Return the blocks data that should be passed to `wp_json_encode()`.
			 *
			 * @param mixed $filtered_blocks The blocks payload after property filtering has been applied.
			 * @param mixed $model           The model associated with the blocks being encoded.
			 * @param mixed $blocks          The original blocks payload before any filtering.
			 */
			apply_filters( 'graphql_gutenberg_blocks_json', $filtered_blocks, $model, $blocks )
		);
	}
}
