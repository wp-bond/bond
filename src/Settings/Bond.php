<?php

namespace Bond\Settings;

use Bond\Post;
use Bond\Utils\Cache;
use Bond\Utils\Cast;
use Bond\Utils\Link;
use Bond\Utils\Query;
use Bond\Utils\Str;

class Bond
{

    // Links

    public static function mapLinks()
    {
        // posts
        $fn = function ($wp_link, $post) {
            return ($link = Link::post($post))
                ? Link::url($link)
                : $wp_link;
        };
        \add_filter('post_type_link', $fn, 10, 2);
        \add_filter('page_link', $fn, 10, 2);

        // posts archives
        \add_filter('post_type_archive_link', function ($wp_link, $post_type) {
            return ($link = Link::postType($post_type))
                ? Link::url($link)
                : $wp_link;
        }, 10, 2);

        // terms
        \add_filter('term_link', function ($wp_link, $term) {
            return ($link = Link::term($term))
                ? Link::url($link)
                : $wp_link;
        }, 10, 2);

        // there a no terms archive links in WP
        // no need to filter
    }
}
