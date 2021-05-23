<?php

namespace Bond\Utils;

use Bond\Settings\Language;
use Bond\Support\Fluent;
use Bond\Support\FluentList;
use ReflectionClass;

class Obj
{
    public static function localize(
        object $target,
        string $language,
        bool $create_new = true
    ) {

        if ($create_new) {
            $class = get_class($target);

            return self::localizeProperties(
                $target,
                new $class,
                Language::code($language)
            );
        }

        return self::localizeProperties(
            $target,
            $target,
            Language::code($language)
        );
    }

    private static function localizeProperties(
        $source,
        $target,
        string $code
    ) {
        foreach ($source as $key => $value) {

            // recurse
            if (is_array($value)) {
                $target[$key] = self::localizeProperties($value, [], $code);
                continue;
            }
            if (
                $value instanceof Fluent
                || $value instanceof FluentList
            ) {
                $target[$key] = $value->localized();
                continue;
            }

            // localize
            foreach (Language::codes() as $c) {
                $suffix = Language::fieldsSuffix($c);

                if (str_ends_with($key, $suffix)) {

                    // if current, store it
                    if ($c === $code) {
                        $unlocalized_key = substr($key, 0, -strlen($suffix));
                        $target[$unlocalized_key] = $value;
                    }
                    // else just skip
                    continue 2;
                }
            }

            // just add non localized values
            // if not already added by the localization above
            if (!isset($target[$key])) {
                $target[$key] = $value;
            }
        }

        return $target;
    }

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
