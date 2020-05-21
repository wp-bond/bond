<?php

namespace Bond\Settings;

use Bond\Utils\Link;

class Rewrite
{
    public static function reset()
    {
        \add_filter('post_rewrite_rules', '__return_empty_array');
        \add_filter('date_rewrite_rules', '__return_empty_array');
        \add_filter('root_rewrite_rules', '__return_empty_array');
        \add_filter('comments_rewrite_rules', '__return_empty_array');
        \add_filter('search_rewrite_rules', '__return_empty_array');
        \add_filter('author_rewrite_rules', '__return_empty_array');
        \add_filter('page_rewrite_rules', '__return_empty_array');
        \add_filter('post_tag_rewrite_rules', '__return_empty_array');
        \add_filter('category_rewrite_rules', '__return_empty_array');
        \add_filter('post_format_rewrite_rules', '__return_empty_array');
    }


    public static function tag(string $name, bool $set_global = false)
    {
        \add_rewrite_tag('%' . $name . '%', '([^&]+)');


        if ($set_global) {
            \add_filter('pre_get_posts', function ($query) use ($name) {

                if (is_admin() || !$query->is_main_query()) {
                    return;
                }
                if (isset($query->query[$name])) {
                    $GLOBALS[$name] = $query->query[$name];
                }
            }, 9);
        }
    }


    public static function pages()
    {
        // Multilanguage Pages
        if (Languages::isMultilanguage()) {

            foreach (static::languagePrefixes() as $code => $prefix) {

                // skip if there is not need for url prefix
                // as the last rewrite below matches all
                if (!Languages::shouldAddToUrl($code)) {
                    continue;
                }

                // i18n Pages
                \add_rewrite_rule(
                    $prefix . '(.+?)/?$',
                    'index.php?page=$matches[1]',
                    'top'
                );

                // i18n Front pages
                \add_rewrite_rule(
                    $prefix . '?$',
                    'index.php?pagename=home',
                    'top'
                );
            }
        }

        // All other pages
        \add_rewrite_rule(
            '(.?.+?)/?$',
            'index.php?pagename=$matches[1]',
            'bottom'
        );
    }



    public static function search(
        $path = null,
        bool $paged = false,
        array $extra_params = []
    ) {
        if (!$path) {
            $path = Link::search();
        }

        if (!is_array($path)) {
            $path = explode('/', trim($path, '/'));
        }

        foreach (static::languagePrefixes() as $code => $prefix) {

            // translate
            $_path = $path;
            array_walk($_path, [static::class, 'twalk'], $code);
            $_path = implode('/', $_path);

            // vars
            $_path = $prefix . $_path;
            $params_string = static::params($extra_params);

            // rewrite

            // Search paged
            if ($paged) {
                add_rewrite_rule(
                    $_path . '/page/?([0-9]{1,})/?$',
                    'index.php?s= &paged=$matches[1]' . $params_string,
                    'top'
                );
            }

            // Search
            \add_rewrite_rule(
                $_path . '/?$',
                'index.php?s= ' . $params_string,
                'top'
            );
        }
    }



    public static function rss()
    {
        foreach (static::languagePrefixes() as $code => $prefix) {
            \add_rewrite_rule(
                $prefix . 'feed/?$',
                'index.php?feed=feed',
                'top'
            );
        }
    }


    public static function postType(
        string $post_type,
        array $path = [],
        bool $paged = false,
        bool $year = false,
        array $extra_params = []
    ) {

        if (empty($path)) {
            $path = [$post_type];
        }

        foreach (static::languagePrefixes() as $code => $prefix) {

            // translate
            $_path = $path;
            array_walk($_path, [static::class, 'twalk'], $code);
            $_path = implode('/', $_path);

            // vars
            $_path = $prefix . $_path;
            $params_string = static::params($extra_params);

            // rewrite

            // archive paged year
            if ($paged && $year) {
                \add_rewrite_rule(
                    $_path . '/([0-9]{4})/page/?([0-9]{1,})/?$',
                    'index.php?post_type=' . $post_type . '&year=$matches[1]&paged=$matches[2]'
                        . $params_string,
                    'top'
                );
            }

            // archive paged
            if ($paged) {
                \add_rewrite_rule(
                    $_path . '/page/?([0-9]{1,})/?$',
                    'index.php?post_type=' . $post_type . '&paged=$matches[1]'
                        . $params_string,
                    'top'
                );
            }

            // front page year
            if ($paged && $year) {
                \add_rewrite_rule(
                    $_path . '/([0-9]{4})/?$',
                    'index.php?post_type=' . $post_type . '&year=$matches[1]'
                        . $params_string,
                    'top'
                );
            }

            // single
            \add_rewrite_rule(
                $_path . '/([^/]+)/?$',
                'index.php?' . $post_type . '=$matches[1]'
                    . $params_string,
                'top'
            );

            // front page
            \add_rewrite_rule(
                $_path . '/?$',
                'index.php?post_type=' . $post_type . '&page_control=archive_front_page'
                    . $params_string,
                'top'
            );
        }
    }


    public static function page(
        $page,
        $path = null,
        array $extra_params = []
    ) {

        if (!is_array($page)) {
            $page = explode('/', trim($page, '/'));
        }
        if (empty($path)) {
            $path = $page;
        } else {
            if (!is_array($path)) {
                $path = explode('/', trim($path, '/'));
            }
        }

        foreach (static::languagePrefixes() as $code => $prefix) {

            // translate
            $_page = $page;
            $_path = $path;

            array_walk($_page, [static::class, 'twalk'], $code);
            array_walk($_path, [static::class, 'twalk'], $code);
            $_page = implode('/', $_page);
            $_path = implode('/', $_path);

            // vars
            $_path = $prefix . $_path;
            $params_string = static::params($extra_params);
            $param = Languages::isDefault($code) ? 'pagename' : 'page';

            // rewrite
            \add_rewrite_rule(
                $_path . '/?$',
                'index.php?' . $param . '=' . $_page . $params_string,
                'top'
            );
        }
    }


    protected static function params(array $params): string
    {
        return !empty($params) ?
            '&' . urldecode(http_build_query($params))
            : '';
    }


    protected static function twalk(&$value, $index, $code)
    {
        $value = tx($value, 'url', $code);
    }

    protected static function languagePrefixes(): array
    {
        // reverse the order to rewrite correctly
        $codes = array_reverse(Languages::codes());

        $result = [];
        foreach ($codes as $code) {

            $prefix = '';

            if (Languages::shouldAddToUrl($code)) {
                $prefix = trim(Languages::urlPrefix($code), '/') . '/';
            }

            $result[$code] = $prefix;
        }
        return $result;
    }
}
