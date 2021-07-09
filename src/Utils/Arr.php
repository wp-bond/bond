<?php

namespace Bond\Utils;

use ArrayAccess;
use Bond\Support\Fluent;
use Bond\Support\FluentList;

/**
 * Provides basic helpers for Arrays.
 *
 * We include PHP 8.1 polyfill so you can use native array_is_list to determine if an array is associative or not.
 */
class Arr
{

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
    public static function only(array $array, array $keys): array
    {
        return array_intersect_key($array, array_flip((array) $keys));
    }
}
