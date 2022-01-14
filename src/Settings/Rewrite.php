<?php

namespace Bond\Settings;

use Bond\Utils\Link;
use Bond\Utils\Str;

// TODO review some rules to maybe add ^ at the beginning

class Rewrite
{
    public static function reset()
    {
        // only needed on admin
        if (!Wp::isAdmin()) {
            return;
        }

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

        // cleanup
        \add_filter('rewrite_rules_array', function (array $rules) {

            unset($rules['favicon\.ico$']);
            unset($rules['robots\.txt$']);
            unset($rules['.*wp-(atom|rdf|rss|rss2|feed|commentsrss2)\.php$']);

            return $rules;
        });
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
        // only needed on admin
        if (!Wp::isAdmin()) {
            return;
        }

        // Multilanguage Pages
        if (Language::isMultilanguage()) {

            foreach (static::languagePrefixes() as $code => $prefix) {

                // skip if there is not need for url prefix
                // as the last rewrite below matches all
                if (!Language::shouldAddToUrl($code)) {
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
        // only needed on admin
        if (!Wp::isAdmin()) {
            return;
        }

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



    public static function rss(
        string $name = 'rss',
        string $url = 'rss',
        bool $multilanguage = false
    ) {
        // only needed on admin
        if (!Wp::isAdmin()) {
            return;
        }

        $url = trim($url, '/');

        if ($multilanguage) {
            foreach (static::languagePrefixes() as $code => $prefix) {
                \add_rewrite_rule(
                    $prefix . $url . '/?$',
                    'index.php?feed=' . $name,
                    'top'
                );
            }
        } else {
            \add_rewrite_rule(
                $url . '/?$',
                'index.php?feed=' . $name,
                'top'
            );
        }
    }


    public static function postType(
        string $post_type,
        ?array $path = null,
        bool $paged = false,
        bool $year = false,
        array $extra_params = []
    ) {
        static::archive(
            $post_type,
            $path,
            $paged,
            $year,
            $extra_params
        );
        static::single(
            $post_type,
            $path,
            $extra_params
        );
    }


    public static function archive(
        string $post_type,
        ?array $path = null,
        bool $paged = false,
        bool $year = false,
        array $extra_params = []
    ) {
        // only needed on admin
        if (!Wp::isAdmin()) {
            return;
        }

        if (is_null($path)) {
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

            // front page
            \add_rewrite_rule(
                $_path . '/?$',
                'index.php?post_type=' . $post_type
                    . $params_string,
                'top'
            );
        }
    }


    public static function single(
        string $post_type,
        ?array $path = null,
        array $extra_params = []
    ) {
        // only needed on admin
        if (!Wp::isAdmin()) {
            return;
        }

        if (is_null($path)) {
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
            $order = 'bottom';

            if ($_path) {
                $_path .= '/';
                $order = 'top';
            }

            // rewrite

            \add_rewrite_rule(
                $_path . '([^/]+)/?$',
                'index.php?' . $post_type . '=$matches[1]'
                    . $params_string,
                $order
            );
        }
    }

    public static function rewrite(
        array $path = [],
        array $params = []
    ) {
        // only needed on admin
        if (!Wp::isAdmin()) {
            return;
        }

        foreach (static::languagePrefixes() as $code => $prefix) {

            // translate
            $_path = $path;
            array_walk($_path, [static::class, 'twalk'], $code);
            $_path = implode('/', $_path);

            // vars
            $_path = $prefix . $_path;
            $params_string = static::params($params);
            $params_string = trim($params_string, '&');

            // rewrite
            \add_rewrite_rule(
                $_path . '/?$',
                'index.php?' . $params_string,
                'top'
            );
        }
    }


    public static function page(
        $page,
        $path = null,
        array $extra_params = []
    ) {
        // only needed on admin
        if (!Wp::isAdmin()) {
            return;
        }

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
            $param = Language::isDefault($code) ? 'pagename' : 'page';

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
        $codes = array_reverse(Language::codes());

        $result = [];
        foreach ($codes as $code) {

            $prefix = '';

            if (Language::shouldAddToUrl($code)) {
                $prefix = trim(Language::urlPrefix($code), '/') . '/';
            }

            $result[$code] = $prefix;
        }
        return $result;
    }
}
