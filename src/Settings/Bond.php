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



    // Save Posts Hook

    public static function addSavePostHook()
    {
        \add_action('save_post', [self::class, 'savePostHook'], 10, 2);
        \add_action('edit_attachment', [self::class, 'savePostHook']);
    }

    public static function removeSavePostHook()
    {
        \remove_action('save_post', [self::class, 'savePostHook'], 10, 2);
        \remove_action('edit_attachment', [self::class, 'savePostHook']);
    }

    public static function savePostHook($post_id, $post = null)
    {
        if (\wp_is_post_revision($post_id)) {
            return;
        }

        // remove action to prevent infinite loop
        static::removeSavePostHook();

        // turn off posts cache
        $original_state = config()->cache->posts ?? false;
        config()->cache->posts = false;

        // in case it's attachment, it misses the post object
        if (!$post) {
            $post = Cast::post($post_id);
        }

        // clear cache
        Cache::forget($post->post_type);
        Cache::forget('bond/posts');
        Cache::forget('global');

        // do action
        $post = Cast::post($post);
        do_action('Bond/save_post', $post);
        do_action('Bond/save_post/' . $post->post_type, $post);

        // turn on posts cache
        config()->cache->posts = $original_state;

        // re-add action
        static::addSavePostHook();
    }


    // Save Terms Hook

    public static function addSaveTermHook()
    {
        \add_action('edited_term', [self::class, 'saveTermHook'], 10, 3);
    }
    public static function removeSaveTermHook()
    {
        \remove_action('edited_term', [self::class, 'saveTermHook'], 10, 3);
    }

    public static function saveTermHook($term_id, $tt_id, $taxonomy)
    {
        // remove action to prevent infinite loop
        static::removeSaveTermHook();

        // turn off posts cache
        $original_state = config()->cache->terms ?? false;
        config()->cache->terms = false;

        // clear cache
        Cache::forget($taxonomy);
        Cache::forget('bond/terms');

        // do action
        $term = Cast::term($term_id);
        \do_action('Bond/save_term', $term);
        \do_action('Bond/save_term/' . $taxonomy, $term);

        // turn on posts cache
        config()->cache->terms = $original_state;

        // re-add action
        static::addSaveTermHook();
    }
}
