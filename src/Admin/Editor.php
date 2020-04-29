<?php

namespace WPGraphQLGutenberg\Admin;

class Editor {
	public static $script_name = 'wp-graphql-gutenberg';

	public static function enqueue_script() {
		$asset_file = include WP_GRAPHQL_GUTENBERG_PLUGIN_DIR . 'build/index.asset.php';

		wp_enqueue_script(
			Editor::$script_name,
			WP_GRAPHQL_GUTENBERG_PLUGIN_URL . 'build/index.js',
			$asset_file['dependencies'],
			$asset_file['version']
		);
	}

	function __construct() {
		add_action('enqueue_block_editor_assets', function () {
			self::enqueue_script();
		});
	}
}
