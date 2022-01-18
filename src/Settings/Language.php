<?php

namespace Bond\Settings;

use Bond\Utils\Str;
use Carbon\Carbon;

// TODO test the use case of working with just a single language then change to multilanguage afterwards
// the default field will not have language suffixes
// Also the opposite, working multilanguage, than temporally turning off

// TODO consider moving inside app() would make sense so we can have separate apps with different languages

class Language
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
            self::setDefaultCode($code);
        }
    }

    public static function setDefaultCode(string $code)
    {
        self::$default = $code;
    }

    public static function isMultilanguage(): bool
    {
        return count(self::$languages) > 1;
    }

    public static function all(): array
    {
        return self::$languages;
    }

    public static function count(): int
    {
        return count(self::$languages);
    }

    public static function codes(): array
    {
        return array_keys(self::$languages);
    }

    public static function defaultCode(): string
    {
        return self::$default;
    }

    public static function defaultShortCode(): string
    {
        return self::shortCode(self::defaultCode());
    }

    public static function defaultLocale(): string
    {
        return self::locale(self::defaultCode());
    }

    public static function defaultName(): string
    {
        return self::name(self::defaultCode());
    }

    public static function defaultShortName(): string
    {
        return self::shortName(self::defaultCode());
    }

    public static function defaultFieldsSuffix(): string
    {
        return self::fieldsSuffix(self::defaultCode());
    }

    public static function defaultFieldsLabel(): string
    {
        return self::fieldsLabel(self::defaultCode());
    }


    public static function isDefault(string $code = null): bool
    {
        return self::defaultCode() === ($code ?: self::code());
    }

    public static function getCurrent(): string
    {
        return self::code();
    }

    public static function setCurrent(?string $code)
    {
        $code = self::code($code) ?: self::defaultCode();

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
                $code = Str::kebab($parts[0] ?? '');
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

        // load theme's GetText if needed
        app()->translation()->loadThemeGetText();
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

    // https://developer.mozilla.org/en-US/docs/Web/HTML/Global_attributes/lang
    public static function tag(string $code = null): string
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

    public static function urlPrefixes(): array
    {
        $prefixes = [];
        foreach (self::codes() as $code) {
            $prefixes[$code] = self::urlPrefix($code);
        }
        return $prefixes;
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
}
