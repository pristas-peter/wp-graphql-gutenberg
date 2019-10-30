<?php

/**
 * Plugin Name: WP GraphQL Gutenberg
 * Plugin URI: https://github.com/pristas-peter/wp-graphql-gutenberg
 * Description: Enable blocks in WP GraphQL.
 * Author: pristas-peter
 * Author URI:
 * Version: 0.1.0
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 *
 */

namespace WPGraphQLGutenberg;

use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\CustomScalarType;
use \WP_Block_Type_Registry;
use \WP_Error;
use \WP_Query;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit();
}

require_once ABSPATH . 'wp-admin/includes/admin.php';

if (!class_exists('WPGraphQLGutenberg')) {
    final class WPGraphQLGutenberg
    {
        private static $field_name = 'wp_graphql_gutenberg';
        private static $block_types_option_name = 'wp_graphql_gutenberg_block_types';
        private static $block_editor_script_name = 'wp-graphql-gutenberg';
        private static $block_editor_script_file = 'dist/blocks.build.js';

        private static $attributes_object_type;
        private static $attributes_array_type;
        private static $json_array_type;

        private static $instance;
        public static function instance()
        {
            if (!isset(self::$instance)) {
                self::$instance = new WPGraphQLGutenberg();
            }

            return self::$instance;
        }

        public static function get_attributes_object_type()
        {
            if (!isset(self::$attributes_object_type)) {
                self::$attributes_object_type = new CustomScalarType([
                    'name' => 'BlockAttributesObject',
                    'serialize' => function ($value) {
                        return json_encode($value);
                    }
                ]);
            }

            return self::$attributes_object_type;
        }

        public static function get_attributes_array_type()
        {
            if (!isset(self::$attributes_array_type)) {
                self::$attributes_array_type = new CustomScalarType([
                    'name' => 'BlockAttributesArray',
                    'serialize' => function ($value) {
                        return json_encode($value);
                    }
                ]);
            }

            return self::$attributes_array_type;
        }

        public static function get_block_json_array_type()
        {
            if (!isset(self::$json_array_type)) {
                self::$json_array_type = new CustomScalarType([
                    'name' => 'BlockJsonArray',
                    'serialize' => function ($value) {
                        return json_encode($value);
                    }
                ]);
            }

            return self::$json_array_type;
        }


        public static function format_graphql_block_type_name($block_name)
        {
            return implode(
                array_map(function ($val) {
                    return ucfirst($val);
                }, preg_split("/(\/|\?|_|=|-)/", $block_name))
            ) . 'Block';
        }

        public static function format_graphql_attributes_type_name($prefix)
        {
            return $prefix . 'Attributes';
        }

        protected static function are_attribute_definitions_equal($a, $b)
        {
            return json_encode([$a['type'], isset($a['default'])]) ===
                json_encode([$b['type'], isset($b['default'])]);
        }

        private $graphql_block_interface_type;
        private $graphql_block_type_per_block_name;
        private $graphql_supported_posts_union_type;
        private $graphql_attribute_type_configs_per_block_name = [];

        private $type_registry;

        public function get_post_resolver($post_id)
        {
            return apply_filters(
                'graphql_gutenberg_post_resolver',
                [\WPGraphQL\Data\DataSource::class, 'resolve_post_object'],
                $post_id
            );
        }
        public function get_graphql_block_interface_type_config($type_name) {
            return [
                'description' => __(
                    'Gutenberg block interface',
                    'wp-graphql-gutenberg'
                ),
                'fields' =>  [
                    'isValid' => [
                        'type' => Type::nonNull(Type::boolean()),
                        'description' => __(
                            'Block validation assumes an idempotent operation from source block to serialized block',
                            'wp-graphql-gutenberg'
                        )
                    ],
                    'name' => [
                        'type' => Type::nonNull(Type::string()),
                        'description' => __(
                            'Name of the block.',
                            'wp-graphql-gutenberg'
                        )
                    ],
                    'originalContent' => [
                        'type' => Type::nonNull(Type::string()),
                        'description' => __(
                            'Original HTML content.',
                            'wp-graphql-gutenberg'
                        )
                    ],
                    'saveContent' => [
                        'type' => Type::nonNull(Type::string()),
                        'description' => __(
                            'Original HTML content with inner blocks.',
                            'wp-graphql-gutenberg'
                        )
                    ],
                    'innerBlocks' => [
                        'type' => ['non_null' => [
                            'list_of' => $type_name
                        ]],
                        'description' => __(
                            'Inner blocks.',
                            'wp-graphql-gutenberg'
                        )
                    ],
                    'parent' => [
                        'type' => $this->get_graphql_supported_posts_union_type(),
                        'description' => __(
                            'Prent post.',
                            'wp-graphql-gutenberg'
                        ),
                        'resolve' => function (
                            $source,
                            $args,
                            $context,
                            $info
                        ) {
                            $id = $source['parent'];

                            if (!isset($id)) {
                                return null;
                            }

                            $resolver = $this->get_post_resolver($id);
                            return $resolver($id, $context);
                        }
                    ],
                    'parentId' => [
                        'type' => Type::int(),
                        'description' => __(
                            'Parent post id.',
                            'wp-graphql-gutenberg'
                        ),
                        'resolve' => function ($source) {
                            return $source['parent'];
                        }
                    ]
                ],
                'resolveType' => function ($value) {
                    return $this->type_registry->get_type($this->get_graphql_block_typename_per_block_name()[$value['name']]);
                }
            ];
        }

        public function get_graphql_block_interface_type()
        {
            if (!isset($this->graphql_block_interface_type)) {
                $type_name= 'Block';
                register_graphql_interface_type($type_name, $this->get_graphql_block_interface_type_config($type_name));
                $this->graphql_block_interface_type = $type_name;
            }

            return $this->graphql_block_interface_type;
        }

        protected function get_editor_post_types()
        {
            return array_filter(get_post_types_by_support('editor'), function (
                $post_type
            ) {
                return use_block_editor_for_post_type($post_type);
            });
        }

        public function get_editor_graphql_types()
        {
            return apply_filters(
                'graphql_gutenberg_editor_graphql_types',
                array_map(function ($post_type) {
                    return get_post_type_object(
                        $post_type
                    )->graphql_single_name;
                }, \WPGraphQL::get_allowed_post_types())
            );
        }

        protected function get_graphql_supported_posts_union_type()
        {
            if (!isset($this->graphql_supported_posts_union_type)) {
                $types = $this->get_editor_graphql_types();

                $type_name = 'PostObjectTypesUnion';

                register_graphql_union_type($type_name, [
                    'typeNames' => $types,
                    'resolveType' => function ($post) {
                        $post_type = get_post_type_object(
                            get_post_type($post->ID)
                        );
                        $type = apply_filters(
                            'graphql_gutenberg_post_type_graphql_name',
                            $post_type->graphql_single_name ?? $post_type->name,
                            $post_type
                        );
                        return $this->type_registry->get_type($type);
                    }
                ]);
                $this->graphql_supported_posts_union_type = $type_name;
            }

            return $this->graphql_supported_posts_union_type;
        }

        protected function generate_graphql_attributes_fields(
            $attributes,
            $block_type_name
        ) {
            $fields = [];

            foreach ($attributes as $attribute_name => $attribute) {
                $type = null;

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
                        $type = self::get_attributes_array_type();
                        break;
                    case 'object':
                        $type = self::get_attributes_object_type();
                        break;
                }

                if (isset($type)) {
                    if (isset($attribute['default'])) {
                        $type = Type::nonNull($type);
                    }

                    $fields[$attribute_name] = [
                        'type' => $type
                    ];
                } elseif (WP_DEBUG) {
                    trigger_error(
                        'Could not determine type of attribute "' .
                            $attribute_name .
                            '" in "' .
                            $block_type_name .
                            '" block type.',
                        E_USER_WARNING
                    );
                }
            }

            return $fields;
        }

        protected function generate_graphql_attributes_configs(
            $block_type,
            $use_cache = true
        ) {
            if (
                $use_cache &&
                isset($this->graphql_attribute_type_configs_per_block_name[$block_type['name']])
            ) {
                return $this->graphql_attribute_type_configs_per_block_name[$block_type['name']];
            }

            $prefix = self::format_graphql_block_type_name($block_type['name']);

            $versions = [];
            $configs = [];

            if (isset($block_type['deprecated'])) {
                foreach (array_reverse($block_type['deprecated'])
                    as $deprecation) {
                    if (isset($deprecation['attributes'])) {
                        array_push($versions, $deprecation['attributes']);
                    }
                }
            }

            if (isset($block_type['attributes'])) {
                array_push($versions, $block_type['attributes']);
            }

            $previous_version = null;
            $previous_version_fields = null;
            $previous_version_field_names = null;

            $versions_count = count($versions);

            for ($i = 0; $i < $versions_count; $i++) {
                $version = $versions[$i];
                $is_current_version = $i === $versions_count - 1;

                $has_breaking_change = false;

                if (isset($previous_version)) {
                    foreach ($version as $name => $definition) {
                        if (isset($previous_version[$name])) {
                            if (
                                !self::are_attribute_definitions_equal(
                                    $previous_version[$name],
                                    $definition
                                )
                            ) {
                                $has_breaking_change = true;
                                break;
                            }
                        }
                    }
                }

                $current_fields = $this->generate_graphql_attributes_fields(
                    $version,
                    $block_type['name']
                );

                $fields = array_merge(
                    isset($previous_version_fields)
                        ? $previous_version_fields
                        : [],
                    $current_fields
                );

                if ($has_breaking_change || $is_current_version) {
                    $version_name = self::format_graphql_attributes_type_name(
                        $prefix
                    );

                    $length = count($configs);

                    if ($length > 0) {
                        $version_name =
                            $version_name . 'V' . strval($length + 1);
                    }
                }

                if (
                    $has_breaking_change &&
                    !$is_current_version &&
                    count($previous_version_fields)
                ) {
                    array_push($configs, [
                        'name' => $version_name,
                        /**
                         * graphql_gutenberg_block_attributes_fields
                         * Filters the fields for block attributes type.
                         *
                         * @param array     $fields           Fields config.
                         * @param string    $type_name        GraphQL type name.
                         * @param array     $attributes 	  Block type attributes definition.
                         * @param array     $block_type 	  Block type definition.
                         * @param object    $type_registry 	  Type registry.
                         */
                        'fields' => apply_filters(
                            'graphql_gutenberg_block_attributes_fields',
                            $previous_version_fields,
                            $version_name,
                            $version,
                            $block_type,
                            $this->type_registry
                        )
                    ]);
                }

                $current_field_names = array_keys($current_fields);

                if (isset($previous_version_field_names)) {
                    foreach ($previous_version_field_names
                        as $previous_field_name) {
                        if (
                            !in_array(
                                $previous_field_name,
                                $current_field_names
                            )
                        ) {
                            $fields[$previous_field_name]['isDeprecated'] = true;
                            $fields[$previous_field_name]['deprecationReason'] = __('Deprecated without breaking change.');
                        }
                    }
                }

                if ($is_current_version && count($fields)) {
                    array_push($configs, [
                        'name' => $version_name,
                        'fields' => apply_filters(
                            'graphql_gutenberg_block_attributes_fields',
                            $fields,
                            $version_name,
                            $version,
                            $block_type,
                            $this->type_registry,
                        )
                    ]);
                }

                $previous_version = $version;
                $previous_version_fields = $fields;
                $previous_version_field_names = array_keys($fields);
            }

            $this->graphql_attribute_type_configs_per_block_name[$block_type['name']] = $configs;
            return $configs;
        }

        protected function generate_graphql_block_type($block_type)
        {
            $name = self::format_graphql_block_type_name($block_type['name']);
            $fields = [];

            $attributes_types = array_map(function ($config) {
                register_graphql_object_type($config['name'], $config);
                return $config['name'];
            }, $this->generate_graphql_attributes_configs($block_type));

            $length = count($attributes_types);

            if ($length === 1) {
                $fields['attributes'] = ['type' => $attributes_types[0]];
            } elseif ($length > 1) {
                $union_type_name =
                    self::format_graphql_attributes_type_name($name) . 'Union';
                register_graphql_union_type($union_type_name, [
                    'types' => $attributes_types,
                    'resolveType' => function ($value) {
                        return $this->type_registry->get_type($value['__typename']);
                    }
                ]);

                $fields['attributes'] = ['type' => $union_type_name];
            }

            $registry = WP_Block_Type_Registry::get_instance();
            $server_block_type = $registry->get_registered(
                $block_type['name']
            );

            if (
                isset($server_block_type) &&
                $server_block_type->is_dynamic()
            ) {
                $fields['renderedContent'] = [
                    'type' => Type::nonNull(Type::string()),
                    'resolve' => function ($value) use (
                        &$server_block_type
                    ) {
                        return $server_block_type->render(
                            $value['attributes']
                        );
                    },
                    'description' => __(
                        'Server side rendered content.',
                        'wp-graphql-gutenberg'
                    )
                ];
            }
            /**
             * graphql_gutenberg_block_type_fields
             * Filters the fields for block type.
             *
             * @param array    $fields           Fields config.
             * @param array     $block_type 	  Block type definition.
             */
            $fields = apply_filters(
                'graphql_gutenberg_block_type_fields',
                array_merge($fields, $this->get_graphql_block_interface_type_config($this->get_graphql_block_interface_type())['fields']),
                $block_type,
                $this->type_registry
            );

            register_graphql_object_type($name, [
                'fields' => $fields,
                'description' => $block_type['name'] . ' block',
                'interfaces' => [$this->get_graphql_block_interface_type()]
            ]);

            return $name;
        }

        protected function get_graphql_block_typename_per_block_name()
        {
            if (!isset($this->graphql_block_type_per_block_name)) {
                $this->graphql_block_type_per_block_name = [];

                foreach (get_option(WPGraphQLGutenberg::$block_types_option_name)
                    as $block_type) {
                    if ($block_type['name'] === 'core/block') {
                        continue;
                    }

                    $this->graphql_block_type_per_block_name[$block_type['name']] = $this->generate_graphql_block_type($block_type);
                }
            }

            return $this->graphql_block_type_per_block_name;
        }

        protected function get_latest_attributes_type_typename($block_type)
        {
            $configs = $this->generate_graphql_attributes_configs($block_type);

            $length = count($configs);

            if ($length > 0) {
                $last_version = $configs[$length - 1];

                if (isset($last_version)) {
                    return $last_version['name'];
                }
            }
        }

        private function prepare_block(&$block, &$block_types_per_name)
        {
            $block['attributes']['__typename'] = $this->get_latest_attributes_type_typename(
                $block_types_per_name[$block['name']]
            );

            $block['innerBlocks'] = array_map(
                function (&$inner_block) use (
                    &$block_types_per_name
                ) {
                    return $this->prepare_block(
                        $inner_block,
                        $block_types_per_name
                    );
                },
                $block['innerBlocks']
            );
            /**
             * graphql_gutenberg_prepare_block
             * Filters block data before saving to post meta.
             *
             * @param array    $data             		Data.
             * @param array     $block_types_per_name 	GraphQL types named array for blocks.
             */
            return apply_filters(
                'graphql_gutenberg_prepare_block',
                $block,
                $block_types_per_name
            );
        }

        protected function setup_rest()
        {
            add_action('rest_api_init', function () {
                $editor_post_types = $this->get_editor_post_types();

                foreach (array_merge($editor_post_types, ['wp_block'])
                    as $post_type) {
                    register_rest_field(
                        $post_type,
                        WPGraphQLGutenberg::$field_name,
                        array(
                            'update_callback' => function ($value, $post) {
                                $block_types = $value['block_types'];

                                $ret = update_option(
                                    WPGraphQLGutenberg::$block_types_option_name,
                                    $block_types,
                                    false
                                );

                                if (is_wp_error($ret)) {
                                    return new WP_Error(
                                        'wp_graphql_gutenberg_block_types_update_failed',
                                        __(
                                            'Failed to update block types option.'
                                        ),
                                        array('status' => 500)
                                    );
                                }

                                if (
                                    isset($value['post_content_blocks']) ||
                                    isset($value['reusable_blocks']) ||
                                    isset($value['reusable_block'])
                                ) {
                                    $block_types_per_name = array_reduce(
                                        $block_types,
                                        function (&$arr, $block_type) {
                                            $arr[$block_type['name']] = $block_type;
                                            return $arr;
                                        },
                                        []
                                    );

                                    if (isset($value['post_content_blocks'])) {
                                        $post_content_blocks = array_map(
                                            function (&$block) use (
                                                &$block_types_per_name
                                            ) {
                                                return $this->prepare_block(
                                                    $block,
                                                    $block_types_per_name
                                                );
                                            },
                                            $value['post_content_blocks']
                                        );

                                        $ret = update_post_meta(
                                            $post->ID,
                                            WPGraphQLGutenberg::$field_name,
                                            $post_content_blocks
                                        );
                                        if (false === $ret) {
                                            return new WP_Error(
                                                'wp_graphql_gutenberg_post_content_update_failed',
                                                __(
                                                    'Failed to update post content blocks meta field.'
                                                ),
                                                array('status' => 500)
                                            );
                                        }
                                    }

                                    if (isset($value['reusable_blocks'])) {
                                        foreach ($value['reusable_blocks']
                                            as $id => $block) {
                                            $ret = update_post_meta(
                                                $id,
                                                WPGraphQLGutenberg::$field_name,
                                                $this->prepare_block(
                                                    $block,
                                                    $block_types_per_name
                                                )
                                            );

                                            if (false === $ret) {
                                                return new WP_Error(
                                                    'wp_graphql_gutenberg_wp_block_update_failed',
                                                    sprintf(
                                                        __(
                                                            'Failed to update reusable block meta field for wp_block %d.'
                                                        ),
                                                        $id
                                                    ),
                                                    array('status' => 500)
                                                );
                                            }
                                        }
                                    }

                                    if (isset($value['reusable_block'])) {
                                        $ret = update_post_meta(
                                            $post->ID,
                                            WPGraphQLGutenberg::$field_name,
                                            $this->prepare_block(
                                                $value['reusable_block'],
                                                $block_types_per_name
                                            )
                                        );

                                        if (false === $ret) {
                                            return new WP_Error(
                                                'wp_graphql_gutenberg_wp_block_update_failed',
                                                sprintf(
                                                    __(
                                                        'Failed to update reusable block meta field for wp_block %d.'
                                                    ),
                                                    $post->ID
                                                ),
                                                array('status' => 500)
                                            );
                                        }
                                    }
                                }
                                return true;
                            },
                            'permission_callback' => function () {
                                return current_user_can('edit_others_posts');
                            },
                            'schema' => array(
                                'description' => __('Parsed blocks.'),
                                'type' => 'object'
                            )
                        )
                    );
                }

                register_rest_route(
                    'wp-graphql-gutenberg/v1',
                    '/editor-posts',
                    array(
                        'methods' => 'GET',
                        'callback' => function () use (&$editor_post_types) {
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
                            '$schema' =>
                            'http://json-schema.org/draft-04/schema#',
                            // The title property marks the identity of the resource.
                            'title' => 'Posts which support editor',
                            'type' => 'array',
                            // In JSON Schema you can specify object properties in the properties attribute.
                            'items' => [
                                'type' => 'number'
                            ]
                        ]
                    )
                );
            });
        }

        protected function get_json_data_blocks($data)
        {
            $block_types = get_option(
                WPGraphQLGutenberg::$block_types_option_name
            );

            $block_types_per_name = array_reduce(
                $block_types,
                function (&$arr, $block_type) {
                    $arr[$block_type['name']] = $block_type;
                    return $arr;
                },
                []
            );

            return array_map(function (&$block) use (&$block_types_per_name) {
                return $this->prepare_block($block, $block_types_per_name);
            }, $data);
        }

        protected function resolve_blocks($blocks)
        {
            return array_map(
                function ($block) {
                    if ($block['name'] === 'core/block') {
                        $id = $block['attributes']['ref'];
                        return array_merge(
                            get_post_meta($id, self::$field_name, true),
                            [
                                'parent' => $block['parent']
                            ]
                        );
                    }

                    return $block;
                },
                is_array($blocks) ? $blocks : []
            );
        }

        protected function setup_block_editor()
        {
            add_action('enqueue_block_editor_assets', function () {
                wp_enqueue_script(
                    WPGraphQLGutenberg::$block_editor_script_name,
                    plugins_url(
                        WPGraphQLGutenberg::$block_editor_script_file,
                        __FILE__
                    ),
                    array(
                        'wp-blocks',
                        'wp-i18n',
                        'wp-element',
                        'wp-editor',
                        'wp-api-fetch',
                        'lodash',
                        'wp-dom-ready'
                    )
                );
            });
        }

        protected function setup_graphql()
        {
            add_action(
                'graphql_register_types',
                function ($type_registry) {

                    $this->type_registry = $type_registry;
                    $this->get_graphql_block_typename_per_block_name();
 
                    foreach ($this->get_editor_graphql_types() as $type) {
                        register_graphql_field($type, 'blocks', [
                            'type' => ['list_of' => $this->get_graphql_block_interface_type()],
                            'description' => 'Gutenberg blocks',
                            'args' => [
                                'json' => Type::string()
                            ],
                            'resolve' => function ($post, $args) {
                                if (!empty($args['json'])) {
                                    $blocks = $this->get_json_data_blocks(
                                        json_decode($args['json'], true)
                                    );
                                } else {
                                    $blocks = get_post_meta(
                                        $post->ID,
                                        self::$field_name,
                                        true
                                    );
                                }

                                return $this->resolve_blocks($blocks);
                            }
                        ]);

                        register_graphql_field($type, 'blocksRaw', [
                            'type' => self::get_block_json_array_type(),
                            'description' => 'Gutenberg blocks as json string',
                            'resolve' => function ($post, $args) {
                                return get_post_meta(
                                    $post->ID,
                                    self::$field_name,
                                    true
                                );
                            }
                        ]);
                    }

                    register_graphql_field('RootQuery', 'blocksBy', [
                        'type' => ['list_of' => $this->get_graphql_block_interface_type()],
                        'args' => [
                            'json' => Type::string()
                        ],
                        'resolve' => function ($root, $args, $context, $info) {
                            $data = !empty($args['json'])
                                ? json_decode($args['json'], true)
                                : [];
                            return $this->resolve_blocks(
                                $this->get_json_data_blocks($data)
                            );
                        }
                    ]);
                },
                100
            );

            add_filter(
                'graphql_schema_config',
                function ($config) {

                    $types = [];

                    foreach (
                        $this->get_graphql_block_typename_per_block_name()
                        as $block_type_name => $type_name
                    ) {
                        $types[] =  $config['typeLoader']($type_name);
                    }

                    $config['types'] = array_merge($config['types'] ?? [], $types);
                    return $config;
                },
                10
            );
        }

        protected function setup_admin()
        {
            add_action('admin_menu', function () {
                add_menu_page(
                    __('GraphQL Gutenberg', 'wp-graphql-gutenberg'),
                    'GraphQL Gutenberg',
                    'manage_options',
                    'wp-graphql-gutenberg-admin',
                    function () {
                        echo '<div class="wrap"><div id="wp-graphql-gutenberg-admin"></div></div>';
                    },
                    'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA0MDAgNDAwIj48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNNTcuNDY4IDMwMi42NmwtMTQuMzc2LTguMyAxNjAuMTUtMjc3LjM4IDE0LjM3NiA4LjN6Ii8+PHBhdGggZmlsbD0iI0UxMDA5OCIgZD0iTTM5LjggMjcyLjJoMzIwLjN2MTYuNkgzOS44eiIvPjxwYXRoIGZpbGw9IiNFMTAwOTgiIGQ9Ik0yMDYuMzQ4IDM3NC4wMjZsLTE2MC4yMS05Mi41IDguMy0xNC4zNzYgMTYwLjIxIDkyLjV6TTM0NS41MjIgMTMyLjk0N2wtMTYwLjIxLTkyLjUgOC4zLTE0LjM3NiAxNjAuMjEgOTIuNXoiLz48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNNTQuNDgyIDEzMi44ODNsLTguMy0xNC4zNzUgMTYwLjIxLTkyLjUgOC4zIDE0LjM3NnoiLz48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNMzQyLjU2OCAzMDIuNjYzbC0xNjAuMTUtMjc3LjM4IDE0LjM3Ni04LjMgMTYwLjE1IDI3Ny4zOHpNNTIuNSAxMDcuNWgxNi42djE4NUg1Mi41ek0zMzAuOSAxMDcuNWgxNi42djE4NWgtMTYuNnoiLz48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNMjAzLjUyMiAzNjdsLTcuMjUtMTIuNTU4IDEzOS4zNC04MC40NSA3LjI1IDEyLjU1N3oiLz48cGF0aCBmaWxsPSIjRTEwMDk4IiBkPSJNMzY5LjUgMjk3LjljLTkuNiAxNi43LTMxIDIyLjQtNDcuNyAxMi44LTE2LjctOS42LTIyLjQtMzEtMTIuOC00Ny43IDkuNi0xNi43IDMxLTIyLjQgNDcuNy0xMi44IDE2LjggOS43IDIyLjUgMzEgMTIuOCA0Ny43TTkwLjkgMTM3Yy05LjYgMTYuNy0zMSAyMi40LTQ3LjcgMTIuOC0xNi43LTkuNi0yMi40LTMxLTEyLjgtNDcuNyA5LjYtMTYuNyAzMS0yMi40IDQ3LjctMTIuOCAxNi43IDkuNyAyMi40IDMxIDEyLjggNDcuN00zMC41IDI5Ny45Yy05LjYtMTYuNy0zLjktMzggMTIuOC00Ny43IDE2LjctOS42IDM4LTMuOSA0Ny43IDEyLjggOS42IDE2LjcgMy45IDM4LTEyLjggNDcuNy0xNi44IDkuNi0zOC4xIDMuOS00Ny43LTEyLjhNMzA5LjEgMTM3Yy05LjYtMTYuNy0zLjktMzggMTIuOC00Ny43IDE2LjctOS42IDM4LTMuOSA0Ny43IDEyLjggOS42IDE2LjcgMy45IDM4LTEyLjggNDcuNy0xNi43IDkuNi0zOC4xIDMuOS00Ny43LTEyLjhNMjAwIDM5NS44Yy0xOS4zIDAtMzQuOS0xNS42LTM0LjktMzQuOSAwLTE5LjMgMTUuNi0zNC45IDM0LjktMzQuOSAxOS4zIDAgMzQuOSAxNS42IDM0LjkgMzQuOSAwIDE5LjItMTUuNiAzNC45LTM0LjkgMzQuOU0yMDAgNzRjLTE5LjMgMC0zNC45LTE1LjYtMzQuOS0zNC45IDAtMTkuMyAxNS42LTM0LjkgMzQuOS0zNC45IDE5LjMgMCAzNC45IDE1LjYgMzQuOSAzNC45IDAgMTkuMy0xNS42IDM0LjktMzQuOSAzNC45Ii8+PC9zdmc+'
                );
            });

            add_action('admin_enqueue_scripts', function () {
                wp_enqueue_style('wp-components');

                wp_enqueue_script(
                    WPGraphQLGutenberg::$block_editor_script_name,
                    plugins_url(
                        WPGraphQLGutenberg::$block_editor_script_file,
                        __FILE__
                    ),
                    array(
                        'wp-i18n',
                        'wp-element',
                        'wp-core-data',
                        'wp-data',
                        'wp-api-fetch',
                        'lodash',
                        'wp-dom-ready',
                        'wp-components'
                    )
                );
                wp_localize_script(WPGraphQLGutenberg::$block_editor_script_name, 'wpGraphqlGutenberg', [
                    'adminUrl' => get_admin_url()
                ]);
            });
        }

        public function setup()
        {
            $this->setup_rest();
            $this->setup_block_editor();
            $this->setup_graphql();
            $this->setup_admin();
        }
    }
}

add_action('init', function () {
    WPGraphQLGutenberg::instance()->setup();
});
