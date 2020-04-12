<?php

namespace WPGraphQLGutenberg\Schema\Types\Scalar;

use GraphQL\Type\Definition\CustomScalarType;

class Scalar
{
    public static function BlockAttributesObject()
    {
        static $type = null;

        if ($type === null) {
            $type = new CustomScalarType([
                'name' => 'BlockAttributesObject',
                'serialize' => function ($value) {
                    return json_encode($value);
                }
            ]);
        }

        return $type;
    }

    public static function BlockAttributesArray()
    {
        static $type = null;

        if ($type === null) {
            $type = new CustomScalarType([
                'name' => 'BlockAttributesArray',
                'serialize' => function ($value) {
                    return json_encode($value);
                }
            ]);
        }

        return $type;
    }
}
