<?php

namespace WPGraphQLGutenberg\Schema\Types\Scalar;

use GraphQL\Type\Definition\CustomScalarType;

class Scalar {
	public static function BlockAttributesObject() {
		static $type = null;

		if ( $type === null ) {
			$type = register_graphql_scalar([
				'name'      => 'BlockAttributesObject',
				'serialize' => function ( $value ) {
					return json_encode( $value );
				},
			]);
		}

		return 'BlockAttributesObject';
	}

	public static function BlockAttributesArray() {
		static $type = null;

		if ( $type === null ) {
			$type = register_graphql_scalar([
				'name'      => 'BlockAttributesArray',
				'serialize' => function ( $value ) {
					return json_encode( $value );
				},
			]);
		}

		return 'BlockAttributesArray';
	}
}
