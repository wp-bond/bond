<?php

namespace Bond;

use Bond\Utils\Cache;
use Bond\Utils\Cast;
use Bond\Utils\Link;
use Bond\Utils\Query;
use Bond\Utils\Register;
use Bond\Utils\Str;

// TODO change to non static now we can rely on app container
// it's better as we can use the constructor and it's more flexible

abstract class PostType
{
    public static string $post_type;
    public static array $taxonomies = [];
    public static string $name;
    public static string $singular_name;
    public static array $register_options = [];

    // we may elect some more props here


    public static function link(string $language_code = null): string
    {
        return Link::forPostTypes(static::$post_type, $language_code);
    }

    public static function register()
    {
        if (!isset(static::$post_type)) {
            return;
        }

        // these are already registered by WP
        if (in_array(static::$post_type, [
            'post',
            'page',
            'attachment',
        ])) {
            return;
        }

        // auto set names
        // we won't handle plural, but at least helps
        if (!isset(static::$name)) {
            static::$name = Str::title((static::$post_type), true);
        }
        if (!isset(static::$singular_name)) {
            static::$singular_name = Str::title((static::$post_type), true);
        }

        // register post type
        Register::postType(
            static::$post_type,
            array_merge([
                'name' => static::$name,
                'singular_name' => static::$singular_name,
                'taxonomies' => static::$taxonomies,
            ], static::$register_options)
        );
    }

    public static function addToView()
    {
        if (static::$post_type === 'page') {
            \add_action(
                'Bond/ready/page',
                [static::class, 'single']
            );
        } else {
            \add_action(
                'Bond/ready/archive-' . static::$post_type,
                [static::class, 'archive']
            );
            \add_action(
                'Bond/ready/single-' . static::$post_type,
                [static::class, 'single']
            );
        }
    }

    // TODO IDEA, we could automatically provide directly in View
    // just the posts actually, since we don't need to rely on global $post / $posts;
    public static function archive()
    {
        global $posts;
        view()->items = Cast::posts($posts)->values('archive');
    }

    public static function single()
    {
        global $post;

        if ($p = Cast::post($post)) {
            view()->add($p->values('single'));
        }
    }

    // helpers

    public static function name(bool $singular = false): string
    {
        return $singular
            ? static::$singular_name ?? Query::postTypeName(static::$post_type, true)
            : static::$name ?? Query::postTypeName(static::$post_type);
    }

    public static function count()
    {
        return Cache::json(
            static::$post_type . '/count',
            -1,
            function () {
                return Query::count(static::$post_type);
            }
        );
    }


    public static function all(array $params = []): Posts
    {
        $fn = function () use ($params) {
            $query_args = [
                'post_type' => static::$post_type,
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'no_found_rows' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'orderby' => 'date',
                'order' => 'DESC',
                // order as class vars? could help to set the archive columns too
            ];
            $query_args = array_merge($query_args, $params);
            $query = new \WP_Query($query_args);

            return Cast::posts($query->posts);
        };

        if (config('cache.enabled')) {
            $cache_key = static::$post_type
                . '/all'
                . (!empty($params) ? '-' . md5(Str::slug($params)) : '');

            return Cache::php($cache_key, -1, $fn);
        }

        return $fn();
    }
}
