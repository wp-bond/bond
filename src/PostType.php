<?php

namespace Bond;

use Bond\Utils\Cache;
use Bond\Utils\Cast;
use Bond\Utils\Link;
use Bond\Utils\Query;

abstract class PostType
{
    public static string $post_type;
    public static array $taxonomies = [];

    // we may elect some more props here



    public static function link(string $language_code = null): string
    {
        return Link::forPostTypes(static::$post_type, $language_code);
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

    public static function archive()
    {
        global $posts;
        $items = Cast::posts($posts)->values('archive');
        view()->add(compact('items'));
    }

    public static function single()
    {
        global $post;
        view()->add(Cast::post($post)->values('single'));
    }

    // helpers

    public static function name(bool $singular = false): string
    {
        return Query::postTypeName(static::$post_type, $singular);
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

    // idea
    // public static function getAll()
    // {
    //     return Cache::php(
    //         static::$post_type . '/all',
    //         -1,
    //         function () {
    //             $query_args = [
    //                 'post_type' => static::$post_type,
    //                 'posts_per_page' => -1,
    //                 'post_status' => 'publish',
    //                 'no_found_rows' => true,
    //                 'update_post_meta_cache' => false,
    //                 'update_post_term_cache' => false,
    //                 'orderby' => 'menu_order title',
    //                 'order' => 'ASC',
    //             ];
    //             $query = new \WP_Query($query_args);

    //             return Cast::posts($query->posts);
    //         }
    //     );
    // }
}
