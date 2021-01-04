<?php

namespace Bond\Utils;

use ReflectionClass;

class Obj
{
    /**
     * Gets an object public properties out into an Array.
     * Does not do it recursivelly.
     */
    public static function vars($object, bool $skip_null = false): array
    {
        if (is_null($object)) {
            return [];
        }

        $values = is_array($object)
            ? $object
            : get_object_vars($object);

        if ($skip_null) {
            $filtered = [];
            foreach ($values as $key => $value) {
                if ($value === null) {
                    continue;
                }
                $filtered[$key] = $value;
            }
            return $filtered;
        }

        return $values;
    }


    /**
     * Converts any object/class found to Array. It does it recursivelly.
     *
     * Executes the toArray / getArrayCopy method if found, otherwise will get all public properties.
     */
    public static function toArray($object, bool $skip_null = false): array
    {
        $result = [];

        foreach ($object as $key => $value) {

            if ($skip_null && $value === null) {
                continue;
            }

            if (is_array($value) || is_a($value, 'stdClass')) {
                // recurse directly
                $result[$key] = static::toArray($value, $skip_null);
                //
            } elseif (is_object($value)) {

                if (method_exists($value, 'toArray')) {
                    $result[$key] = static::toArray($value->toArray(), $skip_null);
                    //
                } elseif (method_exists($value, 'getArrayCopy')) {
                    $result[$key] = static::toArray($value->getArrayCopy(), $skip_null);
                    //
                } else {
                    // extract public properties and recurse
                    $result[$key] = static::toArray(get_object_vars($value), $skip_null);
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }


    /**
     * Recursive clone.
     *
     * @see https://bugs.php.net/bug.php?id=49664
     * @param mixed $object
     * @return mixed
     * @throws ReflectionException
     */
    public static function clone($object)
    {
        if (is_array($object)) {
            foreach ($object as $key => $value) {
                $object[$key] = static::clone($value);
            }
            return $object;
        }

        if (!is_object($object)) {
            return $object;
        }

        if (!(new ReflectionClass($object))->isCloneable()) {
            return $object;
        }

        return clone $object;
    }
}
