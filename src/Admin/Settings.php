<?php

namespace WPGraphQLGutenberg\Admin;

use WPGraphQLGutenberg\Admin\Editor;
use WPGraphQLGutenberg\PostTypes\BlockEditorPreview;

class Settings {
	public function __construct() {
		add_action('admin_menu', function () {
			add_submenu_page(
				'graphiql-ide',
				__( 'GraphQL Gutenberg', 'wp-graphql-gutenberg' ),
				'GraphQL Gutenberg',
				'manage_options',
				'wp-graphql-gutenberg-admin',
				function () {
					echo '<div class="wrap"><div id="wp-graphql-gutenberg-admin"></div></div>';
				}
			);
		});

		add_action('admin_enqueue_scripts', function ( $hook ) {
			if ( ! preg_match( '/.+wp-graphql-gutenberg-admin$/', $hook ) ) {
				return;
			}

			wp_enqueue_style( 'wp-components' );

			Editor::enqueue_script();

			wp_localize_script(Editor::$script_name, 'wpGraphqlGutenberg', [
				'adminPostType' => BlockEditorPreview::post_type(),
				'adminUrl'      => get_admin_url(),
			]);
		});
	}
}
