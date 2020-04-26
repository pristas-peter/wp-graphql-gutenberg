<?php

namespace WPGraphQLGutenberg\Schema\Types;

use WPGraphQLGutenberg\Blocks\Registry;
use WPGraphQLGutenberg\Schema\Types\Scalar\Scalar;
use WPGraphQLGutenberg\Schema\Utils;

class BlockTypes
{
    public static function format_block_name($block_name)
    {
        $name = implode(
            array_map(function ($val) {
                return ucfirst($val);
            }, preg_split("/(\/|\?|_|=|-)/", $block_name))
        );

        if (preg_match('/Block$/', $name)) {
            return $name;
        }

        return $name . 'Block';
    }

    public static function format_attributes($prefix)
    {
        return $prefix . 'Attributes';
    }

    protected static function create_attributes_fields(
        $attributes,
        $block_type
    ) {
        $fields = [];

        foreach ($attributes as $attribute_name => $attribute) {
            $type = null;

            if (isset($attribute['type'])) {
                switch ($attribute['type']) {
                    case 'string':
                        $type = 'String';
                        break;
                    case 'boolean':
                        $type = 'Boolean';
                        break;
                    case 'number':
                        $type = 'Float';
                        break;
                    case 'integer':
                        $type = 'Int';
                        break;
                    case 'array':
                        if (isset($attribute['source']) && $attribute['source'] === 'query') {
                            $type = ['list_of' => 'String'];
                        } else {
                            $type = Scalar::BlockAttributesArray();
                        }
                        break;
                    case 'object':
                        $type = Scalar::BlockAttributesObject();
                        break;
                }
            } else if (isset($attribute['source'])) {
                $type = 'String';
            }

            if (isset($type)) {
                $default_value = $attribute['default'] ?? null;

                if (isset($default_value)) {
                    $type = ['non_null' => $type];
                }

                $fields[$attribute_name] = [
                    'type' => $type,
                    'resolve' => function ($source) use ($attribute_name, $default_value) {
                        return $source[$attribute_name] ?? $default_value;
                    }
                ];
            } else if (WP_DEBUG) {
                trigger_error(
                    'Could not determine type of attribute "' .
                        $attribute_name .
                        '" in "' .
                        $block_type .
                        '" block type.',
                    E_USER_WARNING
                );
            }
        }

        return $fields;
    }

    protected static function register_attributes_types($block_type, $prefix)
    {
        $definitions = [$block_type['attributes']];

        if (isset($block_type['deprecated'])) {
            foreach (array_reverse($block_type['deprecated'])
                as $deprecation) {
                if (isset($deprecation['attributes'])) {
                    array_push($definitions, $deprecation['attributes']);
                }
            }
        }

        $types = [];
        $types_by_definition = [];
        $non_deprecated_definition_key = null;

        foreach ($definitions as $index => $definition) {
            $type = self::format_attributes($index === 0 ? $prefix : $prefix . 'DeprecatedV' . $index);

            register_graphql_object_type($type, [
                'fields' =>  apply_filters(
                    'graphql_gutenberg_block_attributes_fields',
                    self::create_attributes_fields($block_type['attributes'], $type, $definition, $block_type)
                )
            ]);

            $types[] = $type;

            $key = json_encode($definition);

            if ($key !== $non_deprecated_definition_key) {
                $types_by_definition[$key] = $type;
            }

            if ($index === 0) {
                $non_deprecated_definition_key = $key;
            }
        }

        if (count($types) > 1) {
            $type = self::format_attributes($prefix) . 'Union';

            register_graphql_union_type($type, [
                'typeNames' => $types,
                'resolveType' => function ($source) use ($types_by_definition, $non_deprecated_definition_key) {
                    $result = $types_by_definition[json_encode($source['__type']['attributes'])] ?? null;

                    if ($result === null) {
                        return $types_by_definition[$non_deprecated_definition_key];
                    }

                    return $result;
                }
            ]);

            return $type;
        }

        return $types[0];
    }

    protected static function register_block_type($block_type, $type_registry)
    {
        $name = self::format_block_name($block_type['name']);
        $fields = [
            'attributes' => [
                'type' => self::register_attributes_types($block_type, $name),
                'resolve' => function (
                    $source
                ) {
                    return array_merge($source['attributes'], [
                        '__type' => $source['__type']
                    ]);
                }
            ]
        ];

        /**
         * graphql_gutenberg_block_type_fields
         * Filters the fields for block type.
         *
         * @param array    $fields           Fields config.
         * @param array     $block_type       Block type definition.
         */
        $fields = apply_filters(
            'graphql_gutenberg_block_type_fields',
            $fields,
            // array_merge(
            //     $fields,
            //     $this->get_graphql_block_interface_type_config(
            //         $this->get_graphql_block_interface_type()
            //     )['fields']
            // ),
            $block_type,
            $type_registry
        );

        register_graphql_object_type($name, [
            'fields' => $fields,
            'description' => $block_type['name'] . ' block',
            'interfaces' => ['Block']
        ]);

        return $name;
    }

    function __construct()
    {
        add_action(
            'graphql_register_types',
            function ($type_registry) {
                add_filter('graphql_CoreBlock_fields', function ($fields) {
                    $fields['reusableBlock'] = [
                        'type' => ['non_null' => 'ReusableBlock'],
                        'resolve' => function ($source, $args, $context, $info) {
                            $id = $source['attributes']['ref'];
                            $resolve = Utils::get_post_resolver($id);

                            return $resolve($id, $context);
                        }
                    ];

                    return $fields;
                });

                $type_names = [];

                $registry = Registry::get_registry();

                foreach ($registry as $block_name => $block_type) {
                    $type_names[] = self::register_block_type($block_type, $type_registry);
                }

                add_filter(
                    'graphql_schema_config',
                    function ($config) use ($type_names, &$type_registry) {
                        $types = [
                            $type_registry->get_type(
                                'Block'
                            )
                        ];

                        foreach ($type_names
                            as  $type_name) {
                            $types[] = $config['typeLoader']($type_name);
                        }

                        $config['types'] = array_merge(
                            $config['types'] ?? [],
                            $types
                        );
                        return $config;
                    },
                    10
                );
            }
        );
    }
}
