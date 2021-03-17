<?php

namespace Bond\Utils;

use ArrayAccess;
use Bond\Support\Fluent;
use Bond\Support\FluentList;

/**
 * Provides basic helpers for Arrays.
 *
 * For more advanced use cases look for illuminate/support.
 *
 * Some methods here are adapted from Laravel's Illuminate\Support\Arr class, credit goes to Laravel LLC / Taylor Otwell, MIT licence. Note: Don't rely this class to be equivalent to Laravel Arr, some logic are intentionally different.
 */
class Arr
{
    /**
     * Determine if array is associative.
     */
    public static function isAssoc(array $array): bool
    {
        if (!count($array)) {
            return false;
        }
        return array_keys($array) !== range(0, count($array) - 1);
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
}
