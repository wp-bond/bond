<?php

namespace Bond\Utils;

use ArrayAccess;
use Bond\Support\Fluent;
use Bond\Support\FluentList;
use WP_REST_Request;

/**
 * Provides basic helpers for Arrays.
 *
 * We include PHP 8.1 polyfill so you can use native array_is_list to determine if an array is associative or not.
 */
class Arr
{

    public static function array($value): array
    {
        if (is_null($value)) {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }
        if ($value instanceof WP_REST_Request) {
            return $value->get_params();
        }
        if (is_object($value)) {
            if (method_exists($value, 'toArray')) {
                return $value->toArray();
            }
            if (method_exists($value, 'getArrayCopy')) {
                return $value->getArrayCopy();
            }
            return get_object_vars($value);
        }
        return (array) $value;
    }

    public static function arrayRecursive($object, string $only = null): array
    {
        if (is_null($object)) {
            return [];
        }
        if (!is_object($object) && !is_array($object)) {
            return [];
        }

        $result = [];
        foreach ($object as $key => $value) {

            if (is_array($value)) {
                $result[$key] = static::arrayRecursive($value, $only);
            } elseif ($only) {
                if (is_a($value, $only)) {
                    $result[$key] = static::arrayRecursive($value, $only);
                } else {
                    $result[$key] = $value;
                }
            } elseif (is_object($value)) {
                if (method_exists($value, 'toArray')) {
                    $result[$key] = static::arrayRecursive($value->toArray(), $only);
                } elseif (method_exists($value, 'getArrayCopy')) {
                    $result[$key] = static::arrayRecursive($value->getArrayCopy(), $only);
                } else {
                    $result[$key] = static::arrayRecursive(get_object_vars($value), $only);
                }
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    public static function mapKeys(callable $f, array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $k = $f($key);

            if (is_array($value)) {
                $result[$k] = static::mapKeys($f, $value);
            } elseif ($value instanceof Fluent || $value instanceof FluentList) {
                $result[$k] = $value->mapKeys($f);
            } else {
                $result[$k] = $value;
            }
        }
        return $result;
    }

    public static function camelKeys(array $array): array
    {
        return static::mapKeys([Str::class, 'camel'], $array);
    }

    public static function snakeKeys(array $array): array
    {
        return static::mapKeys([Str::class, 'snake'], $array);
    }

    /**
     * Determine whether the given value is array accessible.
     *
     * @param  mixed  $value
     * @return bool
     */
    public static function accessible($value): bool
    {
        return is_array($value) || $value instanceof ArrayAccess;
    }

    // $keys array|string
    public static function only(array $values, array $keys): array
    {
        return array_intersect_key($values, array_flip((array) $keys));
    }

    public static function except(array $values, array $keys): array
    {
        return array_diff_key($values, array_flip((array) $keys));
    }

    public static function sortKeys(
        array $array,
        int $flags = SORT_NATURAL | SORT_FLAG_CASE
    ): array {

        ksort($array, $flags);

        foreach ($array as &$value) {
            if (is_array($value) && $value) {
                $value = static::sortKeys($value, $flags);
            }
        }

        return $array;
    }
}
