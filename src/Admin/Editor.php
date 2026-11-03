<?php

namespace WPGraphQLGutenberg\Admin;

class Editor {
	public static $script_name = 'wp-graphql-gutenberg';

	public static function enqueue_script() {
		$asset_file = include WP_GRAPHQL_GUTENBERG_PLUGIN_DIR . 'build/index.asset.php';

		wp_enqueue_script(
			self::$script_name,
			WP_GRAPHQL_GUTENBERG_PLUGIN_URL . 'build/index.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);
	}

	public function __construct() {
		add_action('enqueue_block_editor_assets', function () {
			self::enqueue_script();
		});

		// WordPress 7 isolates editor pages with Document-Isolation-Policy. In the iframe
		// of the "Update block registry" button, that hides window.frameElement. The last
		// priority keeps a theme or another plugin that enables this filter from undoing it.
		add_filter('wp_client_side_media_processing_enabled', function ( $enabled ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
			return is_admin() && 'true' === ( $_GET['wpGraphqlGutenbergServer'] ?? '' ) ? false : $enabled;
		}, PHP_INT_MAX);
	}
}
