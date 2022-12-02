<?php

namespace WPGraphQLGutenberg\Schema\Types\Connection\Blocks;

use \WPGraphQL\Model\Post;
use \WPGraphQL\Data\Connection\PostObjectConnectionResolver;

class CoreImageBlockToMediaItemConnection {
	public function __construct() {
		add_action('graphql_register_types', function ( $type_registry ) {
			register_graphql_connection([
				'fromType'           => 'CoreImageBlock',
				'toType'             => 'MediaItem',
				'fromFieldName'      => 'mediaItem',
				'oneToOne'           => true,
				'connectionTypeName' => 'CoreImageBlockToMediaItemConnection',
				'resolve'            => function ( $source, $args, $context, $info ) {
					$queried_attachment = get_post( $source->attributes['id'] );
					if ( is_wp_error( $queried_attachment ) ) {
						return false;
					}
					$graphql_post = new Post( $queried_attachment );
					$resolver     = new PostObjectConnectionResolver(
						$graphql_post,
						[ 'where' => [ 'id' => $queried_attachment->ID ] ],
						$context,
						$info,
						'attachment'
					);
					$connection   = $resolver->one_to_one()->get_connection();
					return $connection;
				},
			]);
		});
	}
}
