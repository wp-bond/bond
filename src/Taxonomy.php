<?php

namespace Bond;

use Bond\Fields\Acf\FieldGroup;
use Bond\Settings\Admin;
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

    public static function link(string $language = null): string
    {
        return Link::forTaxonomies(static::$taxonomy, $language);
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
        return Query::allTerms(static::$taxonomy, $params);
    }


    protected static function setColumns(array $columns)
    {
        app()->adminColumns()->setTaxonomyColumns(static::$taxonomy, $columns);
    }

    protected static function addColumnHandler(
        string $name,
        callable $handler = null,
        string|int $width = 0,
        string $css = null
    ) {
        app()->adminColumns()->addHandler($name, $handler, $width, $css);
    }

    protected static function setDefaultFields(array $fields)
    {
        Admin::setTaxonomyFields(static::$taxonomy, $fields);
    }
}
