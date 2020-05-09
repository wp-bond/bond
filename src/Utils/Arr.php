<?php

namespace Bond\Utils;

use ArrayAccess;

/**
 * Provides basic helpers for Arrays.
 *
 * For more advanced use cases look for illuminate/support.
 *
 * Some methods here are adapted from Laravel's Illuminate\Support\Arr class, credit goes to Laravel LLC / Taylor Otwell. Note: Don't rely this class to be equivalent to Laravel Arr, some logic are intentionally different.
 */
class Arr
{
    /**
     * Determine if array is associative.
     */
    public static function isAssoc(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    public static function mapKeys(callable $f, array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $result[$f($key)] = is_array($value) ? static::mapKeys($f, $value) : $value;
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
}
