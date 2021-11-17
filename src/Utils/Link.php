<?php

namespace Bond\Utils;

use Bond\Settings\Language;
use Bond\Utils\Cast;
use Bond\Utils\Query;

class Link
{
    // protected $app;
    // public function __construct(App $app)
    // {
    //     $this->app = $app;
    // }


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


    public static function current(string $language_code = null): string
    {
        if (\is_front_page()) {
            return static::path(null, $language_code);
        }
        if (\is_search()) {
            return static::search($language_code);
        }
        if (\is_archive()) {
            $post_type = \get_query_var('post_type');
            return static::postType($post_type, $language_code);
        }
        if (\is_singular()) {
            global $post;
            return static::post($post, $language_code);
        }
        // TODO add more

        return static::path(null, $language_code);
    }

    public static function post(
        $post,
        string $language_code = null
    ): string {

        $post = Cast::post($post);
        return $post ? $post->link($language_code) : '';
    }

    public static function postType(
        $post_type,
        string $language_code = null
    ): string {

        $postType = Cast::postTypeClass($post_type);
        return $postType ? $postType::link($language_code) : '';
    }


    public static function term(
        $term,
        string $language_code = null
    ): string {

        $term = Cast::term($term);
        return $term ? $term->link($language_code) : '';
    }

    public static function taxonomy(
        $taxonomy,
        string $language_code = null
    ): string {

        $taxonomy = Cast::taxonomyClass($taxonomy);
        return $taxonomy ? $taxonomy::link($language_code) : '';
    }


    public static function search(string $language_code = null): string
    {
        return static::path(
            config('app.search_path') ?? 'search',
            $language_code
        );
    }

    public static function path($path = null, string $language_code = null): string
    {
        // ensures it's a language code
        // fallbacks to current language if invalid
        $language_code = Language::code($language_code);


        if (empty($path)) {
            return Language::urlPrefix($language_code) ?: '/';
        }

        $parts = [];

        foreach ((array) $path as $term) {
            $term = trim($term, '/');
            if (empty($term)) {
                continue;
            }
            if ($t = tx($term, 'url', $language_code)) {
                $parts[] = $t;
            }
        }

        return Language::urlPrefix($language_code)
            .  '/' . implode('/', $parts);
    }

    public static function fallback(
        $post,
        string $language_code = null
    ): string {

        $post = Cast::post($post);
        if (!$post) {
            return '';
        }

        if ($post->post_type === 'page') {

            // try parent page
            $child = Query::firstPageChild($post->ID);
            if ($child) {
                return $child->link($language_code);
            }
        }

        return static::postType(
            $post->post_type,
            $language_code
        );
    }


    // Common formatters

    public static function forPosts(
        $post,
        string $language_code = null
    ): string {

        $post = Cast::post($post);
        if (!$post) {
            return '';
        }

        // ensures it's a language code
        // fallbacks to current language if invalid
        $language_code = Language::code($language_code);

        // is draft
        if (!$post->post_name) {
            return '/?post_type=' . $post->post_type . '&p=' . $post->ID . '&preview=true&lang=' . $language_code;
        }

        // hierarchical (pages)
        if (\is_post_type_hierarchical($post->post_type)) {

            // testing here, this should be disabled:
            // if is not multilanguage, let WP handle
            if (!Language::isMultilanguage()) {
                // TODO check a way to get the full URI here, without the need to return
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
                return Language::urlPrefix($language_code) ?: '/';
            }

            // ACF slugs are considered to be fully qualified
            return Language::urlPrefix($language_code)
                . '/' . $post->slug($language_code);
        }

        // regular posts
        if (Language::isMultilanguage()) {

            return Language::urlPrefix($language_code)
                . '/' . tx($post->post_type, 'url', $language_code)
                . '/' . $post->slug($language_code);
        }

        return '/' . $post->post_type
            . '/' . $post->post_name;
    }

    public static function forPostTypes(
        $post_type,
        string $language_code = null
    ): string {

        if (empty($post_type)) {
            return '';
        }

        // ensures it's a language code
        // fallbacks to current language if invalid
        $language_code = Language::code($language_code);


        if (is_array($post_type)) {
            $post_type = $post_type[0];
        }
        if ($post_type === 'page') {
            // home page
            return static::path(null, $language_code);
        }

        if (Language::isMultilanguage()) {
            return Language::urlPrefix($language_code)
                . '/' . tx($post_type, 'url', $language_code);
        }

        return '/' . $post_type;
    }



    public static function forTerms(
        $term,
        string $language_code = null
    ): string {

        $term = Cast::term($term);
        if (!$term) {
            return '';
        }

        return static::search($language_code)
            . '/?' . $term->taxonomy . '=' . $term->slug($language_code);
    }

    public static function forTaxonomies(
        $taxonomy,
        string $language_code = null
    ): string {

        if (empty($taxonomy)) {
            return '';
        }

        // ensures it's a language code
        // fallbacks to current language if invalid
        $language_code = Language::code($language_code);

        if (is_array($taxonomy)) {
            $taxonomy = $taxonomy[0];
        }
        return static::search($language_code)
            . '/?taxonomy=' . $taxonomy;
    }
}
