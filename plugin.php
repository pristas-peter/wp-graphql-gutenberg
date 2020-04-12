<?php

/**
 * Plugin Name: WP GraphQL Gutenberg
 * Plugin URI: https://github.com/pristas-peter/wp-graphql-gutenberg
 * Description: Enable blocks in WP GraphQL.
 * Author: pristas-peter
 * Author URI:
 * Version: 0.2.0
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 */

namespace WPGraphQLGutenberg;

if (!defined('ABSPATH')) {
    die('Silence is golden.');
}

if (!class_exists('WPGraphQLGutenberg')) {
    final class WPGraphQLGutenberg
    {
        private static $instance;
        public static function instance()
        {
            if (!isset(self::$instance)) {
                self::$instance = new WPGraphQLGutenberg();
            }

            return self::$instance;
        }

        private function setup_autoload()
        {
            /**
             * WP_GATSBY_AUTOLOAD can be set to "false" to prevent the autoloader from running.
             * In most cases, this is not something that should be disabled, but some environments
             * may bootstrap their dependencies in a global autoloader that will autoload files
             * before we get to this point, and requiring the autoloader again can trigger fatal errors.
             *
             * The codeception tests are an example of an environment where adding the autoloader again causes issues
             * so this is set to false for tests.
             */
            if (defined('WP_GRAPHQL_GUTENBERG_AUTOLOAD') && true === WP_GRAPHQL_GUTENBERG_AUTOLOAD) {
                // Autoload Required Classes.
                include_once WP_GRAPHQL_GUTENBERG_PLUGIN_DIR . 'vendor/autoload.php';
            }
        }

        private function setup_constants()
        {
            // // Plugin version.
            if (!defined('WP_GRAPHQL_GUTENBERG_VERSION')) {
                define('WP_GRAPHQL_GUTENBERG_VERSION', '1.0.0');
            }

            // Plugin Folder Path.
            if (!defined('WP_GRAPHQL_GUTENBERG_PLUGIN_DIR')) {
                define('WP_GRAPHQL_GUTENBERG_PLUGIN_DIR', plugin_dir_path(__FILE__));
            }

            // Plugin Folder URL.
            if (!defined('WP_GRAPHQL_GUTENBERG_PLUGIN_URL')) {
                define('WP_GRAPHQL_GUTENBERG_PLUGIN_URL', plugin_dir_url(__FILE__));
            }

            // Plugin Root File.
            if (!defined('WP_GRAPHQL_GUTENBERG_PLUGIN_FILE')) {
                define('WP_GRAPHQL_GUTENBERG_PLUGIN_FILE', __FILE__);
            }

            // Whether to autoload the files or not.
            if (!defined('WP_GRAPHQL_GUTENBERG_AUTOLOAD')) {
                define('WP_GRAPHQL_GUTENBERG_AUTOLOAD', true);
            }

            // Whether to run the plugin in debug mode. Default is false.
            if (!defined('WP_GRAPHQL_GUTENBERG_DEBUG')) {
                define('WP_GRAPHQL_GUTENBERG_DEBUG', false);
            }
        }


        public function init()
        {
            $this->setup_constants();
            $this->setup_autoload();

            new \WPGraphQLGutenberg\PostTypes\BlockEditorPreview();
            new \WPGraphQLGutenberg\Admin\Editor();
            new \WPGraphQLGutenberg\Admin\Settings();
            new \WPGraphQLGutenberg\Rest\Rest();
            new \WPGraphQLGutenberg\Schema\Schema();
        }
    }
}

WPGraphQLGutenberg::instance()->init();
