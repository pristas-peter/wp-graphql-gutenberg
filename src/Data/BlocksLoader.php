<?php

namespace WPGraphQLGutenberg\Data;

use GraphQL\Deferred;
use GraphQL\Error\ClientAware;
use WPGraphQLGutenberg\Blocks\PostMeta;
use WPGraphQLGutenberg\Blocks\Registry;

class StaleContentException extends \Exception implements ClientAware {
	public function isClientSafe() {
		return true;
	}

	public function getCategory() {
		return 'gutenberg';
	}
}

class BlocksLoader {
	private static function ensure_not_stale( $id, $data ) {
		if ( empty( $data ) ) {
			throw new StaleContentException( __( 'Blocks content is not sourced.', 'wp-graphql-gutenberg' ) );
		}

		if ( PostMeta::is_data_stale( $id, $data ) ) {
			throw new StaleContentException( __( 'Blocks content is stale.', 'wp-graphql-gutenberg' ) );
		}

		return $data;
	}

	private $server;
	private $is_loading         = false;
	private $post_content_by_id = [];

	public function __construct( $server ) {
		$this->server = $server;
	}

	public function add( $id ) {
		if ( $this->server->enabled() ) {
			$data         = PostMeta::get_post( $id );
			$registry     = Registry::get_registry();
			$post_content = get_post( $id )->post_content;

			if ( $data['post_content'] === $post_content && json_encode( $data['registry'] ) === json_encode( $registry ) ) {
				return;
			}

			$this->post_content_by_id[ $id ] = $post_content;
		}
	}

	public function load() {
		if ( $this->server->enabled() && ! empty( $this->post_content_by_id ) && ! $this->is_loading ) {
			$this->is_loading = true;
			$data             = $this->server->get_batch( $this->post_content_by_id );
			PostMeta::update_batch( $data['batch'], Registry::get_registry() );
		}
	}

	public function get( $id ) {
		$data = self::ensure_not_stale( $id, PostMeta::get_post( $id ) );
		return $data['blocks'];
	}
}
