<?php
/**
 * Plugin Name: WP GraphQL Gutenberg
 * Plugin URI: https://github.com/pristas-peter/wp-graphql-gutenberg
 * Description: Enable blocks in WP GraphQL.
 * Author: pristas-peter
 * Author URI: 
 * Version: 0.0.1
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 *
 */


namespace WPGraphQLGutenberg;
use WPGraphQL\WPSchema;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;
use WPGraphQL\TypeRegistry;
use \WP_Block_Type_Registry;
use \WP_Error;
use \WP_Query;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once(ABSPATH . 'wp-admin/includes/admin.php');


if ( ! class_exists( 'WPGraphQLGutenberg' ) ) {
    final class WPGraphQLGutenberg {
		private static $field_name = 'wp_graphql_gutenberg';
		private static $block_types_option_name = 'wp_graphql_gutenberg_block_types';
        private static $block_editor_script_name = 'wp-graphql-gutenberg-script';
        private static $block_editor_script_file = 'dist/blocks.build.js';
		private static $instance;
		
        public static function instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new WPGraphQLGutenberg();
			}

			return self::$instance;
		}

		protected static function format_graphql_block_type_name($block_name) {
			return implode(array_map(function($val) {
				return ucfirst($val);
			}, preg_split("/(\/|\?|_|=|-)/", $block_name . 'Block')));
		}

		protected static function format_graphql_attributes_type_name($prefix) {
			return $prefix . 'Attributes';
		}

		protected static function are_attribute_definitions_equal($a, $b) {
			return json_encode([$a['type'], isset($a['default'])]) === json_encode([$b['type'], isset($b['default'])]);
		}

		private $graphql_block_interface_type;
		private $graphql_block_type_per_block_name;
		private $graphql_attribute_type_configs_per_block_name = [];

		protected function get_graphql_block_interface_type() {
			if (!isset($this->graphql_block_interface_type)) {
				$this->graphql_block_interface_type = new InterfaceType([
					'name' => 'Block',
					'description' => __('Gutenberg block interface', 'wp-graphql-gutenberg'),
					'fields' => function() {
						return [
							'isValid' => [
								'type' => Type::nonNull(Type::boolean()),
								'description' => __('Block validation assumes an idempotent operation from source block to serialized block', 'wp-graphql-gutenberg'),
							],
							'name' => [
								'type' => Type::nonNull(Type::string()),
								'description' => __('Name of the block.', 'wp-graphql-gutenberg')
							],
							'originalContent' => [
								'type' => Type::nonNull(Type::string()),
								'description' => __('Original HTML content.', 'wp-graphql-gutenberg')
							],
							'innerBlocks' => [
								'type' => Type::nonNull(Type::listOf($this->get_graphql_block_interface_type())),
								'description' => __('Inner blocks.', 'wp-graphql-gutenberg')
							]
						];
					},
					'resolveType' => function ($value) {
						return $this->get_graphql_block_type_per_block_name()[$value['name']];
					}
				]);
			}

			return $this->graphql_block_interface_type;
		}

		protected function generate_graphql_attributes_fields($attributes, $block_type_name) {
			$fields = [];

			foreach($attributes as $attribute_name => $attribute) {
				$type = NULL;

				switch ($attribute['type']) {
					case 'string':
						$type = Type::string();
						break;
					case 'boolean':
						$type = Type::boolean();
						break;
					case 'number':
						$type = Type::float();
						break;
					case 'integer':
						$type = Type::int();
						break;
					case 'array':
						$type = Type::listOf(Type::nonNull(Type::string()));
						break;
				}

				if (isset($type)) {
					if (isset($attribute['default'])) {
						$type = Type::nonNull($type);
					}

					$fields[$attribute_name] = [
						'type' => $type,
					];
				} else if (WP_DEBUG) {
					// trigger_error('Could not determine type of attribute "' . $attribute_name . '" in "' . $block_type_name . '" block type.', E_USER_WARNING);
				}
			}

			return $fields;
		}

		protected function generate_graphql_attributes_configs($block_type, $use_cache = true) {
			if ($use_cache && isset($this->graphql_attribute_type_configs_per_block_name[$block_type['name']])) {
				return $this->graphql_attribute_type_configs_per_block_name[$block_type['name']];
			}

			$prefix = self::format_graphql_block_type_name($block_type['name']);
			
			$versions = [];
			$configs = [];

			if (isset($block_type['deprecated'])) {
				foreach(array_reverse($block_type['deprecated']) as $deprecation) {
					if (isset($deprecation['attributes'])) {
						array_push($versions, $deprecation['attributes']);
					}
				}
			}

			if (isset($block_type['attributes'])) {
				array_push($versions, $block_type['attributes']);
			}

			$previous_version = NULL;
			$previous_version_fields = NULL;
			$previous_version_field_names = NULL;

			$versions_count = count($versions);
	
			for ($i = 0; $i < $versions_count; $i++) {
				$version = $versions[$i];
				$is_current_version = $i === ($versions_count - 1);
				
				$has_breaking_change = false;

				if (isset($previous_version)) {
					foreach($version as $name => $definition) {
						if (isset($previous_version[$name])) {
							if (!self::are_attribute_definitions_equal($previous_version[$name], $definition)) {
								$has_breaking_change = true;
								break;
							}
						}   
					}
				}

				
				$current_fields = $this->generate_graphql_attributes_fields($version, $block_type['name']);

				$fields = array_merge(
					isset($previous_version_fields) ? $previous_version_fields : [],
					$current_fields
				);

				if ($has_breaking_change || $is_current_version) {
					$version_name = self::format_graphql_attributes_type_name($prefix);
					
					$length = count($configs);
					
					if ($length > 0) {
						$version_name = $version_name . 'V' . strval($length + 1);
					}
				}

				if ($has_breaking_change && !$is_current_version && count($previous_version_fields)) {
					array_push($configs, [
						'name' => $version_name,
						'fields' => $previous_version_fields
					]);
				}

				$current_field_names = array_keys($current_fields);
				
				if (isset($previous_version_field_names)) {
					foreach($previous_version_field_names as $previous_field_name) {
						if (!in_array($previous_field_name, $current_field_names)) {
							$fields[$previous_field_name]['isDeprecated'] = true;
							$fields[$previous_field_name]['deprecationReason'] = __('Deprecated without breaking change.');
						}
					}
				}

				if ($is_current_version && count($fields)) {
					array_push($configs, [
						'name' => $version_name,
						'fields' => $fields
					]);
				}

				$previous_version = $version;
				$previous_version_fields = $fields;
				$previous_version_field_names = array_keys($fields);
			}


			$this->graphql_attribute_type_configs_per_block_name[$block_type['name']] = $configs;
			return $configs;
		}

		protected function generate_graphql_block_type($block_type) {
			$name = self::format_graphql_block_type_name($block_type['name']);
			$fields = [];

			$attributes_types = array_map(function ($config) {
				register_graphql_object_type($config['name'], $config);
				return TypeRegistry::get_type($config['name']);

			}, $this->generate_graphql_attributes_configs($block_type));


			$length = count($attributes_types);

			if ($length === 1) {
				$fields['attributes'] = $attributes_types[0];
			} else if ($length > 1) {
				$union_type_name = self::format_graphql_attributes_type_name($name) . 'Union';
				register_graphql_union_type($union_type_name, [
					'types' => $attributes_types,
					'resolveType' => function($value) {
						return TypeRegistry::get_type($value['__typename']);
					}
				 ]);
				 
				$fields['attributes'] = TypeRegistry::get_type($union_type_name);
			}
			
			register_graphql_object_type($name, [
				'fields' => function() use (&$fields, &$block_type) {
					$block_interface = $this->get_graphql_block_interface_type();

					$fields = array_merge($fields, [
						$block_interface->getField('name'),
						$block_interface->getField('innerBlocks'),
						$block_interface->getField('isValid'),
						$block_interface->getField('originalContent')
					]);

					$registry = WP_Block_Type_Registry::get_instance();
					$server_block_type = $registry->get_registered($block_type['name']);

					if (isset($server_block_type) && $server_block_type->is_dynamic()) {
						$fields['renderedContent'] = [
							'type' => Type::nonNull(Type::string()),
							'resolve' => function($value) use (&$server_block_type) {
								return $server_block_type->render($value['attributes']);
							},
							'description' => __('Server side rendered content.', 'wp-graphql-gutenberg')
						];
					}

					return $fields;
				},
				'description' => $block_type['name'] . ' block',
				'interfaces' => function() {
					return [$this->get_graphql_block_interface_type()];
				}
			]);

			return TypeRegistry::get_type($name);
		}

		protected function get_graphql_block_type_per_block_name() {
			if (!isset($this->graphql_block_type_per_block_name)) {
				$this->graphql_block_type_per_block_name = [];

				foreach (get_option(WPGraphQLGutenberg::$block_types_option_name) as $block_type) {
					if ($block_type['name'] === 'core/block') {
						continue;
					}

					$this->graphql_block_type_per_block_name[$block_type['name']] = $this->generate_graphql_block_type($block_type);
				}
			}

			return $this->graphql_block_type_per_block_name;
		}
		

        protected function get_editor_post_types() {
            return array_filter(get_post_types_by_support('editor'), function ($post_type) {
                return use_block_editor_for_post_type($post_type);
            });
		}

        protected function get_latest_attributes_type_typename($block_type) {
			$configs = $this->generate_graphql_attributes_configs($block_type);

			$length = count($configs);

			if ($length > 0) {
				$last_version = $configs[$length - 1];
			
				if (isset($last_version)) {
					return $last_version['name'];
				}
			}
		}

		private function set_block_attributes_typename(&$block, &$block_types_per_name) {
			$block['attributes']['__typename'] = $this->get_latest_attributes_type_typename($block_types_per_name[$block['name']]);

			foreach ($block['innerBlocks'] as &$inner_block) {
				$this->set_block_attributes_typename($inner_block, $block_types_per_name);
			}

		}

        protected function setup_rest() {
            add_action( 'rest_api_init', function () {
                $editor_post_types = $this->get_editor_post_types();

                foreach ($editor_post_types as $post_type) {
                    register_rest_field( $post_type, WPGraphQLGutenberg::$field_name, array(
                        'update_callback' => function( $value, $post ) {
							$block_types = $value['block_types'];

							$ret = update_option(WPGraphQLGutenberg::$block_types_option_name, $block_types, false);

							if ( is_wp_error($ret) ) {
                                return new WP_Error(
                                'wp_graphql_gutenberg_block_types_update_failed',
                                __( 'Failed to update block types option.' ),
                                array( 'status' => 500 )
                                );
							}

							$block_types_per_name = array_reduce($block_types, function(&$arr, $block_type) {
								$arr[$block_type['name']] = $block_type;
								return $arr;
							}, []);
							
							$post_content_blocks = $value['post_content_blocks'];
							
							foreach($post_content_blocks as &$block) {
								$this->set_block_attributes_typename($block, $block_types_per_name);
							}

                            $ret = update_post_meta($post->ID, WPGraphQLGutenberg::$field_name, $post_content_blocks);
                            if ( false === $ret ) {
                                return new WP_Error(
                                'wp_graphql_gutenberg_post_content_update_failed',
                                __( 'Failed to update post content blocks meta field.' ),
                                array( 'status' => 500 )
                                );
							}

							foreach($value['reusable_blocks'] as $id => $block) {
								$this->set_block_attributes_typename($block, $block_types_per_name);

								$ret = update_post_meta($id, WPGraphQLGutenberg::$field_name, $block);

								if ( false === $ret ) {
									return new WP_Error(
									'wp_graphql_gutenberg_wp_block_update_failed',
									sprintf(__( 'Failed to update reusable block meta field for wp_block %d.' ), $id),
									array( 'status' => 500 )
									);
								}
							}

                            return true;
						},
						'permission_callback' => function () {
							return current_user_can('edit_others_posts');
						},
                        'schema' => array(
                            'description' => __( 'Parsed blocks.' ),
                            'type'        => 'object'
                        ),
                    ));
				}
				
				register_rest_route( 'wp-graphql-gutenberg/v1', '/editor-posts', array(
					'methods' => 'GET',
					'callback' => function() use (&$editor_post_types) {
						$query = new WP_Query(array(
							'post_type' => $editor_post_types,
							'fields' => 'ids'
						));
		
						return $query->posts;
					},
					'permission_callback' => function () {
						return current_user_can('edit_others_posts');
					},
					'schema' => [
						'$schema'              => 'http://json-schema.org/draft-04/schema#',
						// The title property marks the identity of the resource.
						'title'                => 'Posts which support editor',
						'type'                 => 'array',
						// In JSON Schema you can specify object properties in the properties attribute.
						'items'           => [
							'type' => 'number'
						]
					]
				));
            });
        }

        protected function setup_block_editor() {
            add_action( 'enqueue_block_editor_assets', function () {
                wp_enqueue_script(
                    WPGraphQLGutenberg::$block_editor_script_name,
                    plugins_url(WPGraphQLGutenberg::$block_editor_script_file, __FILE__ ),
                    array( 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-editor', 'wp-api-fetch', 'lodash', 'wp-dom-ready' )
                );
            });
		}

		protected function setup_graphql() {
			add_action('graphql_register_types', function () {
				foreach($this->get_editor_post_types() as $post_type) {
					if (array_search($post_type, \WPGraphQL::$allowed_post_types, true)) {
						register_graphql_field( $post_type, 'blocks', [
							'type' => Type::listOf($this->get_graphql_block_interface_type()),
							'description' => 'Gutenberg blocks',
							'resolve' => function( $post ) {
								$blocks = get_post_meta($post->ID, self::$field_name, true);
								$blocks = is_array($blocks) ? $blocks : [];

								return array_map(function($block) {
									if ($block['name'] === 'core/block') {
										$id = $block['attributes']['ref'];
										return get_post_meta($id, self::$field_name, true);
									}

									return $block;
								}, $blocks);
							}
						]);
					}
				}
			});

			add_filter('graphql_schema_config', function($config) {
				if (!isset($config['types'])) {
					$config['types'] = [];
				}

				foreach($this->get_graphql_block_type_per_block_name() as $name => $block_type) {
					array_push($config['types'], $block_type);
				}

				return $config;
			});
		}

		protected function setup_admin() {
			add_action( 'admin_menu', function() {
				add_menu_page(
					__( 'GraphQL Gutenberg', 'wp-graphql-gutenberg' ),
					'GraphQL Gutenberg',
					'manage_options',
					'wp-graphql-gutenberg-admin',
					function() {
						echo '<div class="wrap"><div id="wp-graphql-gutenberg-admin"></div></div>';
					},
					'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0MDAgNDAwIj48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNNTcuNDY4IDMwMi42NmwtMTQuMzc2LTguMyAxNjAuMTUtMjc3LjM4IDE0LjM3NiA4LjN6Ii8+PHBhdGggZmlsbD0iI0UxMDA5OCIgZD0iTTM5LjggMjcyLjJoMzIwLjN2MTYuNkgzOS44eiIvPjxwYXRoIGZpbGw9IiNFMTAwOTgiIGQ9Ik0yMDYuMzQ4IDM3NC4wMjZsLTE2MC4yMS05Mi41IDguMy0xNC4zNzYgMTYwLjIxIDkyLjV6TTM0NS41MjIgMTMyLjk0N2wtMTYwLjIxLTkyLjUgOC4zLTE0LjM3NiAxNjAuMjEgOTIuNXoiLz48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNNTQuNDgyIDEzMi44ODNsLTguMy0xNC4zNzUgMTYwLjIxLTkyLjUgOC4zIDE0LjM3NnoiLz48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNMzQyLjU2OCAzMDIuNjYzbC0xNjAuMTUtMjc3LjM4IDE0LjM3Ni04LjMgMTYwLjE1IDI3Ny4zOHpNNTIuNSAxMDcuNWgxNi42djE4NUg1Mi41ek0zMzAuOSAxMDcuNWgxNi42djE4NWgtMTYuNnoiLz48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNMjAzLjUyMiAzNjdsLTcuMjUtMTIuNTU4IDEzOS4zNC04MC40NSA3LjI1IDEyLjU1N3oiLz48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNMzY5LjUgMjk3LjljLTkuNiAxNi43LTMxIDIyLjQtNDcuNyAxMi44LTE2LjctOS42LTIyLjQtMzEtMTIuOC00Ny43IDkuNi0xNi43IDMxLTIyLjQgNDcuNy0xMi44IDE2LjggOS43IDIyLjUgMzEgMTIuOCA0Ny43TTkwLjkgMTM3Yy05LjYgMTYuNy0zMSAyMi40LTQ3LjcgMTIuOC0xNi43LTkuNi0yMi40LTMxLTEyLjgtNDcuNyA5LjYtMTYuNyAzMS0yMi40IDQ3LjctMTIuOCAxNi43IDkuNyAyMi40IDMxIDEyLjggNDcuN00zMC41IDI5Ny45Yy05LjYtMTYuNy0zLjktMzggMTIuOC00Ny43IDE2LjctOS42IDM4LTMuOSA0Ny43IDEyLjggOS42IDE2LjcgMy45IDM4LTEyLjggNDcuNy0xNi44IDkuNi0zOC4xIDMuOS00Ny43LTEyLjhNMzA5LjEgMTM3Yy05LjYtMTYuNy0zLjktMzggMTIuOC00Ny43IDE2LjctOS42IDM4LTMuOSA0Ny43IDEyLjggOS42IDE2LjcgMy45IDM4LTEyLjggNDcuNy0xNi43IDkuNi0zOC4xIDMuOS00Ny43LTEyLjhNMjAwIDM5NS44Yy0xOS4zIDAtMzQuOS0xNS42LTM0LjktMzQuOSAwLTE5LjMgMTUuNi0zNC45IDM0LjktMzQuOSAxOS4zIDAgMzQuOSAxNS42IDM0LjkgMzQuOSAwIDE5LjItMTUuNiAzNC45LTM0LjkgMzQuOU0yMDAgNzRjLTE5LjMgMC0zNC45LTE1LjYtMzQuOS0zNC45IDAtMTkuMyAxNS42LTM0LjkgMzQuOS0zNC45IDE5LjMgMCAzNC45IDE1LjYgMzQuOSAzNC45IDAgMTkuMy0xNS42IDM0LjktMzQuOSAzNC45Ii8+PC9zdmc+'
				);

			});

			add_action( 'admin_enqueue_scripts', function() {
				wp_enqueue_style(
					'wp-components'
				);

				wp_enqueue_script(
					WPGraphQLGutenberg::$block_editor_script_name,
					plugins_url(WPGraphQLGutenberg::$block_editor_script_file, __FILE__ ),
					array( 'wp-i18n', 'wp-element', 'wp-core-data', 'wp-data', 'wp-api-fetch', 'lodash', 'wp-dom-ready', 'wp-components' )
				);
			});
		}

        public function setup() {
            $this->setup_rest();
			$this->setup_block_editor();
			$this->setup_graphql();
			$this->setup_admin();
        }
    }
}

add_action('init', function() {
    WPGraphQLGutenberg::instance()->setup();
});


// /**
//  * Enqueue block JavaScript and CSS for the editor
//  */
// function my_block_plugin_editor_scripts() {
	
//     // Enqueue block editor JS
//     wp_enqueue_script(
//         'my-block-editor-js',
//         plugins_url( '/blocks/custom-block/index.js', __FILE__ ),
//         [ 'wp-blocks', 'wp-element', 'wp-components', 'wp-i18n' ],
//         filemtime( plugin_dir_path( __FILE__ ) . 'blocks/custom-block/index.js' )	
//     );

//     // Enqueue block editor styles
//     wp_enqueue_style(
//         'my-block-editor-css',
//         plugins_url( '/blocks/custom-block/editor-styles.css', __FILE__ ),
//         [ 'wp-blocks' ],
//         filemtime( plugin_dir_path( __FILE__ ) . 'blocks/custom-block/editor-styles.css' )	
//     );

// }

// // Hook the enqueue functions into the editor
// add_action( 'enqueue_block_editor_assets', 'my_block_plugin_editor_scripts' );

// /**
//  * Enqueue frontend and editor JavaScript and CSS
//  */
// function my_block_plugin_scripts() {
	
//     // Enqueue block JS
//     wp_enqueue_script(
//         'my-block-js',
//         plugins_url( '/blocks/custom-block/scripts.js', __FILE__ ),
//         [ 'wp-blocks', 'wp-element', 'wp-components', 'wp-i18n' ],
//         filemtime( plugin_dir_path( __FILE__ ) . 'blocks/custom-block/scripts.js' )	
//     );

//     // Enqueue block editor styles
//     wp_enqueue_style(
//         'my-block-css',
//         plugins_url( '/blocks/custom-block/styles.css', __FILE__ ),
//         [ 'wp-blocks' ],
//         filemtime( plugin_dir_path( __FILE__ ) . 'blocks/custom-block/styles.css' )	
//     );

// }

// // Hook the enqueue functions into the frontend and editor
// add_action( 'enqueue_block_assets', 'my_block_plugin_scripts' );




// /**
//  * Enqueue Gutenberg block assets for both frontend + backend.
//  *
//  * @uses {wp-editor} for WP editor styles.
//  * @since 1.0.0
//  */
// function wp_graphql_gutenberg_cgb_block_assets() { // phpcs:ignore
// 	// Styles.
// 	wp_enqueue_style(
// 		'wp_graphql_gutenberg-cgb-style-css', // Handle.
// 		plugins_url( 'dist/blocks.style.build.css', dirname( __FILE__ ) ), // Block style CSS.
// 		array( 'wp-editor' ) // Dependency to include the CSS after it.
// 		// filemtime( plugin_dir_path( __DIR__ ) . 'dist/blocks.style.build.css' ) // Version: File modification time.
// 	);
// }

// // Hook: Frontend assets.
// add_action( 'enqueue_block_assets', 'wp_graphql_gutenberg_cgb_block_assets' );

// /**
//  * Enqueue Gutenberg block assets for backend editor.
//  *
//  * @uses {wp-blocks} for block type registration & related functions.
//  * @uses {wp-element} for WP Element abstraction — structure of blocks.
//  * @uses {wp-i18n} to internationalize the block's text.
//  * @uses {wp-editor} for WP editor styles.
//  * @since 1.0.0
//  */
// function wp_graphql_gutenberg_cgb_editor_assets() { // phpcs:ignore
// 	// Scripts.
// 	wp_enqueue_script(
// 		'wp_graphql_gutenberg-cgb-block-js', // Handle.
// 		plugins_url( '/dist/blocks.build.js', dirname( __FILE__ ) ), // Block.build.js: We register the block here. Built with Webpack.
// 		array( 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-editor' ), // Dependencies, defined above.
// 		// filemtime( plugin_dir_path( __DIR__ ) . 'dist/blocks.build.js' ), // Version: File modification time.
// 		true // Enqueue the script in the footer.
// 	);

// 	// Styles.
// 	wp_enqueue_style(
// 		'wp_graphql_gutenberg-cgb-block-editor-css', // Handle.
// 		plugins_url( 'dist/blocks.editor.build.css', dirname( __FILE__ ) ), // Block editor CSS.
// 		array( 'wp-edit-blocks' ) // Dependency to include the CSS after it.
// 		// filemtime( plugin_dir_path( __DIR__ ) . 'dist/blocks.editor.build.css' ) // Version: File modification time.
// 	);
// }

// // Hook: Editor assets.
// add_action( 'enqueue_block_editor_assets', 'wp_graphql_gutenberg_cgb_editor_assets' );
