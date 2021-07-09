<?php

namespace Bond;

use Bond\Fields\Acf\FieldGroup;
use Bond\Utils\Cache;
use Bond\Utils\Cast;
use Bond\Utils\Link;
use Bond\Utils\Query;
use Bond\Utils\Register;
use Bond\Utils\Str;

// TODO work in progress
abstract class Taxonomy
{
    public static string $taxonomy;
    public static string $name;
    public static string $singular_name;
    public static array $register_options = [];

    public static function link(string $language_code = null): string
    {
        return Link::forTaxonomies(static::$taxonomy, $language_code);
    }

    public static function register()
    {
        if (!isset(static::$taxonomy)) {
            return;
        }

        // auto set names
        // we won't handle plural, but at least helps
        if (!isset(static::$name)) {
            static::$name = Str::title((static::$taxonomy), true);
        }
        if (!isset(static::$singular_name)) {
            static::$singular_name = Str::title((static::$taxonomy), true);
        }

        // register taxonomy
        Register::taxonomy(
            static::$taxonomy,
            array_merge([
                'name' => static::$name,
                'singular_name' => static::$singular_name,
            ], static::$register_options)
        );
    }


    // helpers

    public static function fieldGroup(string $title): FieldGroup
    {
        return (new FieldGroup(static::$taxonomy))
            ->title($title)
            ->location([
                'tax' => static::$taxonomy
            ]);
    }

    public static function name(bool $singular = false): string
    {
        return $singular
            ? static::$singular_name ?? Query::taxonomyName(static::$taxonomy, true)
            : static::$name ?? Query::taxonomyName(static::$taxonomy);
    }

    // TODO
    // public static function count()

    public static function all(array $params = []): Terms
    {
        $fn = function () use ($params) {
            return Cast::terms(Query::wpTerms(
                static::$taxonomy,
                $params
            ));
        };

        if (config('cache.enabled')) {
            $cache_key = static::$taxonomy
                . '/all'
                . (!empty($params) ? '-' . Str::kebab($params) : '');

            return Cache::php($cache_key, -1, $fn);
        }

        return $fn();
    }
}
