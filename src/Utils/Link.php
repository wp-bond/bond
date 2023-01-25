<?php

namespace Bond\Utils;

use Bond\Settings\Language;
use Bond\Settings\Rewrite;
use Bond\Utils\Cast;
use Bond\Utils\Query;

class Link
{

    public static function url(string $url): string
    {
        if (strpos($url, '/') === 0) {
            return app()->url() . $url;
        }
        if (strpos($url, 'http') !== 0) {
            return app()->url() . '/' . $url;
        }
        return $url;
    }


    public static function current(string $language = null): string
    {
        if (\is_front_page()) {
            return static::path(null, $language);
        }
        if (\is_search()) {
            return static::search($language);
        }
        if (\is_archive()) {
            $post_type = \get_query_var('post_type');
            return static::postType($post_type, $language);
        }
        if (\is_singular()) {
            global $post;
            return static::post($post, $language);
        }
        // TODO add more

        return static::path(null, $language);
    }

    public static function post(
        $post,
        string $language = null
    ): string {

        $post = Cast::post($post);
        return $post ? $post->link($language) : '';
    }

    public static function postType(
        $post_type,
        string $language = null
    ): string {

        $postType = Cast::postTypeClass($post_type);
        return $postType ? $postType::link($language) : '';
    }


    public static function term(
        $term,
        string $language = null
    ): string {

        $term = Cast::term($term);
        return $term ? $term->link($language) : '';
    }

    public static function taxonomy(
        $taxonomy,
        string $language = null
    ): string {

        $taxonomy = Cast::taxonomyClass($taxonomy);
        return $taxonomy ? $taxonomy::link($language) : '';
    }


    public static function search(string $language = null): string
    {
        return static::path(
            Rewrite::$search_path ?? 'search',
            $language
        );
    }

    public static function path($path = null, string $language = null): string
    {
        // ensures it's a language code
        // fallbacks to current language if invalid
        $language = Language::code($language);


        if (empty($path)) {
            return Language::urlPrefix($language) ?: '/';
        }

        $parts = [];

        foreach ((array) $path as $term) {
            $term = trim($term, '/');
            if (empty($term)) {
                continue;
            }
            if ($t = tx($term, 'url', $language)) {
                $parts[] = $t;
            }
        }

        return Language::urlPrefix($language)
            .  '/' . implode('/', $parts);
    }

    public static function fallback(
        $post,
        string $language = null
    ): string {

        $post = Cast::post($post);
        if (!$post) {
            return '';
        }

        if ($post->post_type === 'page') {

            // try parent page
            $child = Query::firstPageChild($post->ID);
            if ($child) {
                return $child->link($language);
            }
        }

        return static::postType(
            $post->post_type,
            $language
        );
    }


    // Common formatters

    public static function forPosts(
        $post,
        string $language = null
    ): string {

        $post = Cast::post($post);
        if (!$post) {
            return '';
        }

        // ensures it's a language code
        // fallbacks to current language if invalid
        $language = Language::code($language);

        // is draft
        if (!$post->post_name) {
            return '/?post_type=' . $post->post_type . '&p=' . $post->ID . '&preview=true&lang=' . $language;
        }

        // hierarchical (pages)
        if (\is_post_type_hierarchical($post->post_type)) {

            if (!Language::isMultilanguage()) {
                $paths = [$post->post_name];

                $p = $post;
                while ($p->post_parent) {
                    $p = \get_post($p->post_parent);
                    if ($p) {
                        $paths[] = $p->post_name;
                    } else {
                        break;
                    }
                }

                return '/' . implode('/', array_reverse($paths));
            }

            // if slug is home, consider front page
            if ($post->post_name === 'home') {
                return Language::urlPrefix($language) ?: '/';
            }

            // ACF slugs are considered to be fully qualified
            return Language::urlPrefix($language)
                . '/' . $post->slug($language);
        }

        // regular posts
        if (Language::isMultilanguage()) {

            return Language::urlPrefix($language)
                . '/' . tx($post->post_type, 'url', $language)
                . '/' . $post->slug($language);
        }

        return '/' . $post->post_type
            . '/' . $post->post_name;
    }

    public static function forPostTypes(
        $post_type,
        string $language = null
    ): string {

        if (empty($post_type)) {
            return '';
        }

        // ensures it's a language code
        // fallbacks to current language if invalid
        $language = Language::code($language);


        if (is_array($post_type)) {
            $post_type = $post_type[0];
        }
        if ($post_type === 'page') {
            // home page
            return static::path(null, $language);
        }

        if (Language::isMultilanguage()) {
            return Language::urlPrefix($language)
                . '/' . tx($post_type, 'url', $language);
        }

        return '/' . $post_type;
    }


    public static function forTerms(
        $term,
        string $language = null
    ): string {

        $term = Cast::term($term);
        if (!$term) {
            return '';
        }

        return static::search($language)
            . '/?' . $term->taxonomy . '=' . $term->slug($language);
    }

    public static function forTaxonomies(
        $taxonomy,
        string $language = null
    ): string {

        if (empty($taxonomy)) {
            return '';
        }

        // ensures it's a language code
        // fallbacks to current language if invalid
        $language = Language::code($language);

        if (is_array($taxonomy)) {
            $taxonomy = $taxonomy[0];
        }
        return static::search($language)
            . '/?taxonomy=' . $taxonomy;
    }


    public static function forUsers(
        $user,
        string $language = null
    ): string {

        $user = Cast::user($user);
        if (!$user) {
            return '';
        }

        return static::search($language)
            . '/?user=' . $user->user_nicename;
    }
}
