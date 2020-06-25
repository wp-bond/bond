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
        \add_action('save_post', [self::class, 'savePostHook'], 10, 3);
    }

    public static function removeSavePostHook()
    {
        \remove_action('save_post', [self::class, 'savePostHook'], 10, 3);
    }

    public static function savePostHook($post_id, $post, $update)
    {
        if (\wp_is_post_revision($post_id)) {
            return;
        }

        // remove action to prevent infinite loop
        static::removeSavePostHook();

        // turn off posts cache
        $original_state = config()->cache->posts ?? false;
        config()->cache->posts = false;

        // clear cache
        Cache::forget($post->post_type);
        Cache::forget('bond/posts');

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




    // Mulilanguage titles and slugs

    public static function ensureTitlesAndSlugs($post_types)
    {
        if (!app()->hasAcf()) {
            return;
        }

        if ($post_types === true) {
            \add_action(
                'Bond/save_post',
                [static::class, 'setTitleAndSlug']
            );
        } elseif (is_array($post_types)) {
            foreach ($post_types as $post_type) {
                \add_action(
                    'Bond/save_post/' . $post_type,
                    [static::class, 'setTitleAndSlug']
                );
            }
        }
    }

    public static function setTitleAndSlug(
        Post $post
    ) {

        // not for front page
        if ($post->post_type === 'page' && \is_front_page()) {
            return;
        }

        $codes = Languages::codes();


        // get default language's title
        // or next best match
        $default_code = Languages::getDefault();
        $default_title = $post->get('title', $default_code);

        if (empty($default_title)) {
            foreach ($codes as $code) {
                $default_title = $post->get('title', $code);
                if (!empty($default_title)) {
                    $default_code = $code;
                    break;
                }
            }
        }

        // no title yet, just skip
        if (empty($default_title)) {
            return;
        }

        // if WP title is different, update it
        if ($post->post_title !== $default_title) {
            \wp_update_post([
                'ID' => $post->ID,
                'post_title' => $default_title,
            ]);
            $post->post_title = $default_title;
        }

        // we don't allow empty titles
        foreach ($codes as $code) {
            $suffix = Languages::fieldsSuffix($code);

            $title = \get_field('title' . $suffix, $post->ID);
            if (empty($title)) {
                \update_field(
                    'title' . $suffix,
                    app('translation')->fromTo($default_code, $code, $default_title) ?: $default_title,
                    $post->ID
                );
            }
        }


        // title is done
        // now it's time for the slug

        // not published yet, don't need to handle
        if (empty($post->post_name)) {
            return;
        }

        $is_hierarchical = \is_post_type_hierarchical($post->post_type);

        foreach ($codes as $code) {
            $suffix = Languages::fieldsSuffix($code);

            $slug = \get_field('slug' . $suffix, $post->ID);

            // remove parent pages
            if (strpos($slug, '/') !== false) {
                $slug = substr($slug, strrpos($slug, '/') + 1);
            }

            // get from title if empty
            if (empty($slug)) {
                $slug = \get_field('title' . $suffix, $post->ID);
            }

            // sanitize user input
            $slug = Str::slug($slug);


            // handle
            if (Languages::isDefault($code)) {

                // define full path if is hierarchical
                $parent_path = [];
                if ($is_hierarchical) {
                    $p = $post;

                    while ($p->post_parent) {
                        $p = \get_post($p->post_parent);
                        $parent_path[] = $p->post_name;
                    }
                    $parent_path = array_reverse($parent_path);

                    $post_path = implode('/', array_merge($parent_path, [$slug]));
                } else {
                    $post_path = $slug;
                }

                // always update both WP and ACF
                // WP always needs, as the user can change anytime
                // ACF needs the first time, or if programatically changed
                $id = \wp_update_post([
                    'ID' => $post->ID,
                    'post_name' => $slug,
                ]);
                if ($id) {

                    // sync with the actual WP slug
                    // the slug above might have changed in some scenarios, like multiple posts with same slug
                    // so we fetch again
                    $slug = Query::slug($id);

                    // join if hierarchical
                    if ($is_hierarchical) {
                        $post_path = implode('/', array_merge($parent_path, [$slug]));
                    } else {
                        $post_path = $slug;
                    }
                }
                \update_field('slug' . $suffix, $post_path, $post->ID);
            } else {


                // prepend parent path
                // only one level down, because the parent already has the translated slug
                if ($is_hierarchical) {
                    $parent_path = [];

                    if ($post->post_parent) {
                        $p = Cast::post($post->post_parent);
                        if ($p) {
                            $parent_path[] = $p->slug($code);
                        }
                    }

                    $post_path_intent = implode('/', array_merge($parent_path, [$slug]));
                } else {
                    $post_path_intent = $slug;
                }

                $post_path = $post_path_intent;

                // search for posts with same slug, and increment until necessary
                $i = 1;
                while (Query::wpPostBySlug(
                    $post_path,
                    $post->post_type,
                    $code,
                    [
                        'post__not_in' => [$post->ID],
                    ]
                )) {
                    $post_path = $post_path_intent . '-' . (++$i);
                }

                // done, update ACF field
                \update_field(
                    'slug' . $suffix,
                    $post_path,
                    $post->ID
                );
            }
        }
    }
}
