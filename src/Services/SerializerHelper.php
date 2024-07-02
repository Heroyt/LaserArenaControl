<?php

namespace App\Services;

class SerializerHelper
{

    public static function handleCircularReference(object $object, string $format, array $context) : mixed {
        if (property_exists($object, 'code')) {
            return $object->code;
        }
        if (property_exists($object, 'id')) {
            return $object->id;
        }
        if (property_exists($object, 'name')) {
            return $object->name;
        }
        return null;
    }

}