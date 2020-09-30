<?php

namespace Bond\Settings;

use Bond\Post;
use Bond\Utils\Cast;
use Bond\Utils\Query;
use Bond\Utils\Str;
use Carbon\Carbon;

// TODO test the use case of working with just a single language then change to multilanguage afterwards
// the default field will not have language suffixes
// Also the opposite, working multilanguage, than temporally turning off

class Languages
{
    private static array $languages = [
        'en' => [
            'short_code' => 'en',
            'locale' => 'en_US',
            'name' => 'English',
            'short_name' => 'EN',
            'fields_suffix' => 'en',
            'fields_label' => '(EN)',
        ]
    ];
    private static string $default;
    private static string $current = '';
    private static bool $default_in_url = false;
    private static bool $is_using_fallback = true;


    public static function add(string $code, array $values)
    {
        // remove fallback
        if (self::$is_using_fallback) {
            self::$languages = [];
            self::$is_using_fallback = false;
        }

        // TODO standarize all values
        // make sure there no missing fields

        $values['code'] = $code;
        self::$languages[$code] = $values;

        if (!isset(self::$default)) {
            self::setDefault($code);
        }
    }

    public static function isMultilanguage(): bool
    {
        return count(self::$languages) > 1;
    }

    public static function all(): array
    {
        return self::$languages;
    }

    public static function codes(): array
    {
        return array_keys(self::$languages);
    }

    public static function getDefault(): string
    {
        return self::$default;
    }

    public static function setDefault(string $code)
    {
        self::$default = $code;
    }

    public static function isDefault(string $code = null): bool
    {
        return self::getDefault() === ($code ?: self::code());
    }

    public static function getCurrent(): string
    {
        return self::code();
    }

    public static function setCurrent(?string $code)
    {
        $code = self::code($code) ?: self::getDefault();

        self::$current = $code;

        // set php and load language pack
        self::changeLocale(self::locale($code));
    }

    public static function isCurrent(string $code): bool
    {
        return self::code() === $code;
    }

    public static function setCurrentFromRequest()
    {
        $code = null;

        if (self::isMultilanguage()) {
            $postdata = file_get_contents('php://input');
            $postdata = !empty($postdata) ? json_decode($postdata, true) : [];

            if (!empty($postdata['lang'])) {
                $code = $postdata['lang'];
                //
            } elseif (!empty($_REQUEST['lang'])) {
                $code = $_REQUEST['lang'];
                //
            } elseif (\is_admin()) {
                $code = \get_user_locale();
                //
            } else {
                // let's look into the url path
                $uri = $_SERVER['REQUEST_URI'];
                $parts = explode('/', trim($uri, '/'));
                $code = Str::slug($parts[0] ?? '');
            }
        }

        self::setCurrent($code);
    }

    public static function changeLocale($to)
    {
        if (!$to) {
            $to = 'en_US';
        }
        global $locale, $l10n;

        $charset = app()->isDevelopment() ? '.UTF-8' : '.utf8';
        // TODO find a way to detect this

        // check environment charset with
        // if (isset($_GET['debug'])) {
        //     system('locale -a');
        // }

        // change PHP locale
        setlocale(LC_ALL, 'en_US' . $charset);
        if ($to !== 'en_US') {
            setlocale(LC_CTYPE, $to . $charset);
            setlocale(LC_TIME, $to . $charset);
        }

        // change Carbon locale
        Carbon::setLocale($to);

        // change WP locale
        $locale = $to;

        // clear WP i18n
        unset($l10n[app()->id()]);

        // load WP gettext, if not handling translations automatically
        if (!app('translation')->hasService()) {
            \load_theme_textdomain(app()->id(), app()->languagesPath());
        }
    }

    public static function resetLocale()
    {
        global $locale;
        if ($locale !== self::locale()) {
            return;
        }
        self::changeLocale(self::locale());
    }


    public static function shouldAddToUrl(string $code = null): bool
    {
        return self::usesDefaultInUrl() || !self::isDefault($code);
    }

    public static function usesDefaultInUrl(): bool
    {
        return self::$default_in_url;
    }

    public static function useDefaultInUrl(bool $value)
    {
        self::$default_in_url = $value;
    }

    // getters

    public static function code($code = null): string
    {
        if (!$code) {
            return self::$current;
        }
        return self::get('code', $code) ?: self::$current;
    }

    public static function shortCode($code = null): string
    {
        return self::get('short_code', $code);
    }

    public static function locale($code = null): string
    {
        return self::get('locale', $code);
    }

    public static function name($code = null): string
    {
        return self::get('name', $code);
    }

    public static function shortName($code = null): string
    {
        return self::get('short_name', $code);
    }

    public static function fieldsSuffix(string $code = null): string
    {
        if (!$code) {
            $code = self::code();
        }

        static $cache = [];
        if (isset($cache[$code])) {
            return $cache[$code];
        }

        $values = self::find($code);
        if ($values) {
            return $cache[$code] = $values['fields_suffix']
                ? '_' . $values['fields_suffix']
                : '';
        }

        return '';
    }

    public static function fieldsLabel(string $code = null): string
    {
        return self::get('fields_label', $code);
    }

    public static function htmlAttribute(string $code = null): string
    {
        return str_replace('_', '-', self::locale($code));
    }

    public static function urlPrefix($code = null): string
    {
        if (self::shouldAddToUrl($code)) {
            if ($short_code = self::get('short_code', $code)) {
                return '/' . $short_code;
            }
        }
        return '';
    }

    private static function get(string $param, string $code = null): string
    {
        if (!$code) {
            $code = self::code();
        }
        if ($values = self::find($code)) {
            return $values[$param] ?? '';
        }
        return '';
    }

    public static function find(?string $code): ?array
    {
        if (!$code) {
            return null;
        }

        if (isset(self::$languages[$code])) {
            return self::$languages[$code];
        }

        foreach (self::$languages as $values) {
            if ($values['short_code'] === $code) {
                return $values;
            }
            if ($values['locale'] === $code) {
                return $values;
            }
            if ($values['fields_suffix'] === $code) {
                return $values;
            }
            if ($values['short_name'] === $code) {
                return $values;
            }
            if ($values['name'] === $code) {
                return $values;
            }
        }

        return null;
    }



    // Mulilanguage titles and slugs

    public static function ensureTitlesAndSlugs($post_types)
    {
        if (!app()->hasAcf() || empty($post_types)) {
            return;
        }

        if ($post_types === true) { // all
            \add_action(
                'Bond/save_post',
                [static::class, 'setTitleAndSlug']
            );
        } else {
            $post_types = (array) $post_types;

            foreach ($post_types as $post_type) {
                \add_action(
                    'Bond/save_post/' . $post_type,
                    [static::class, 'setTitleAndSlug']
                );
            }
        }
    }

    public static function setTitleAndSlug(Post $post)
    {
        // not for front page
        if ($post->post_type === 'page' && \is_front_page()) {
            return;
        }

        $codes = self::codes();


        // get default language's title
        // or next best match
        $default_code = self::getDefault();
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
            $suffix = self::fieldsSuffix($code);

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
            $suffix = self::fieldsSuffix($code);

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
            if (self::isDefault($code)) {

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
