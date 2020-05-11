<?php

namespace Bond;

use Bond\Utils\Link;
use Bond\Utils\Query;

// TODO work in progress
abstract class Taxonomy
{
    public static string $taxonomy;

    public static function link(string $language_code = null): string
    {
        return Link::forTaxonomies(static::$taxonomy, $language_code);
    }

    // helpers

    public static function name(bool $singular = false): string
    {
        return Query::taxonomyName(static::$taxonomy, $singular);
    }

    // TODO
    // public static function count()
}
