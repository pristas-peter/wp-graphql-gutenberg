<?php

namespace WPGraphQLGutenberg\Schema;

class Utils {
	public static function get_post_resolver( $id ) {
		return apply_filters(
			'graphql_gutenberg_post_resolver',
			[ \WPGraphQL\Data\DataSource::class, 'resolve_post_object' ],
			$id
		);
	}

	public static function get_taxonomy_resolver( $id ) {
		return apply_filters(
			'graphql_gutenberg_taxonomy_resolver',
			[ \WPGraphQL\Data\DataSource::class, 'resolve_taxonomy' ],
			$id
		);
	}

	public static function get_post_type_graphql_type( $post_type ) {
		return apply_filters(
			'graphql_gutenberg_editor_post_type_graphql_type',
			get_post_type_object( $post_type )->graphql_single_name,
			$post_type
		);
	}

	public static function get_post_graphql_type( $post ) {
		return self::get_post_type_graphql_type( $post->post_type );
	}

	public static function get_graphql_allowed_editor_post_types() {
		return apply_filters(
			'graphql_gutenberg_editor_post_types',
			array_filter(\WPGraphQLGutenberg\Blocks\Utils::get_editor_post_types(), function ( $post_type ) {
				return in_array( $post_type, \WPGraphQL::get_allowed_post_types(), true );
			})
		);
	}

	public static function get_editor_graphql_types() {
		return array_map(function ( $post_type ) {
			return self::get_post_type_graphql_type( $post_type );
		}, self::get_graphql_allowed_editor_post_types());
	}

	public static function ensure_capability( $resolver, $callback ) {
		return function ( $model, $args, $context, $info ) use ( $resolver, $callback ) {
			$cap = $callback( get_post_type_object( $model->post_type )->cap );

			if ( ! current_user_can( $cap ) ) {
				return null;
			}

			return $resolver( $model, $args, $context, $info );
		};
	}
}
