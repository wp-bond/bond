<?php

namespace Bond\Utils;

use voku\helper\ASCII;

/**
 * Provides basic helpers for Strings.
 *
 * For more advanced use cases look for illuminate/support.
 *
 * Some methods here are adapted from Laravel's Illuminate\Support\Str class, credit goes to Laravel LLC / Taylor Otwell, MIT licence. Note: Don't rely this class to be equivalent to Laravel Str, some logic are intentionally different, for example snake case and slug.
 */
class Str
{


    public static function escJson($data): string
    {
        if (is_array($data) || is_object($data)) {
            $data = Obj::toArray($data, true);
        }
        return htmlspecialchars(json_encode($data), ENT_QUOTES);
    }




    public static function append(
        &$target,
        $value,
        $append_to_target = '',
        $append_before = '',
        $append_after = '',
        $fallback = ''
    ) {
        foreach ((array) $value as $string) {
            $string = static::returnIf(
                $string,
                $append_before,
                $append_after,
                $fallback
            );
            if ($string) {
                if ($target) {
                    $target .= $append_to_target;
                }
                $target .= $string;
            }
        }
    }

    public static function prepend(
        &$target,
        $value,
        $prepend_to_target = '',
        $append_before = '',
        $append_after = '',
        $fallback = ''
    ) {
        foreach ((array) $value as $string) {
            $string = static::returnIf(
                $string,
                $append_before,
                $append_after,
                $fallback
            );
            if ($string) {
                if ($target) {
                    $target = $prepend_to_target . $target;
                }
                $target = $string . $target;
            }
        }
    }

    public static function returnIf(
        string $string,
        string $append_before = '',
        string $append_after = '',
        string $fallback = ''
    ): string {
        if ($string !== '' && $string !== false && $string !== null) {
            return $append_before . $string . $append_after;
        }
        return $fallback;
    }



    public static function clean(
        $string,
        int $word_limit = 0,
        string $end = ' ...'
    ): string {
        $string = (string) $string;

        // remove wp shortcodes
        $string = \strip_shortcodes($string);

        // adds a space before every tag open so we don't get heading/paragraphs glued together when we strip tags
        $string = str_replace('<', ' <', $string);

        // strip tags
        $string = strip_tags($string);

        // convert space entities to normal spaces to help out some users
        $string = str_replace('&nbsp;', ' ', $string);

        // convert to html entities
        $string = htmlspecialchars($string);

        // convert space entities to regular spaces so we can remove double spaces - all other hmtl entities should be fine
        $string = str_replace('&nbsp;', ' ', $string);

        // removes double spaces, tabs or line breaks, and trim the result
        $string = trim(mb_ereg_replace('\s+', ' ', $string));

        // limit
        if ($word_limit) {
            return static::words($string, $word_limit, $end);
        }

        return $string;
    }




    // executes the same filters as WP the_content
    public static function filterContent($content): string
    {
        if (!empty($content)) {
            $content = \apply_filters('the_content', $content);
            $content = str_replace(']]>', ']]&gt;', $content);
        }
        return (string) $content;
    }


    public static function nbsp($string): string
    {
        return str_replace(' ', '&nbsp;', (string) $string);
    }


    public static function removeNbsp($string): string
    {
        static $hard_coded_nbsp = null;
        static $space = null;

        if (!$hard_coded_nbsp) {
            $hard_coded_nbsp = json_decode('"\u00A0"');
        }
        if (!$space) {
            $space = json_decode('"\u0020"');
        }

        return str_replace($hard_coded_nbsp, $space, (string) $string);
    }


    public static function br($string)
    {
        if ($string) {
            $string = preg_replace("/\n/", '<br>', $string);
            $string = preg_replace("/\r/", '', $string);
        }
        return $string;
    }


    public static function breakParagraphs($string, $replacement = '<br><br>')
    {
        if ($string) {
            //removes line breaks, and trim the result
            $string = trim(preg_replace('/\n+/', '', $string));

            //remove p tags
            $string = str_replace('</p><p>', $replacement, $string);
            $string = str_replace('<p>', '', $string);
            $string = str_replace('</p>', '', $string);
        }
        return $string;
    }

    /**
     * Limit the number of words in a string.
     */
    public static function words($value, int $words, string $end = ' …'): string
    {
        $value = (string) $value;

        preg_match('/^\s*+(?:\S++\s*+){1,' . $words . '}/u', $value, $matches);

        if (!isset($matches[0]) || mb_strlen($value) === mb_strlen($matches[0])) {
            return $value;
        }

        return rtrim($matches[0]) . $end;
    }

    /**
     * Limit the number of characters in a string.
     */
    public static function limit($value, int $limit, string $end = '…'): string
    {
        $value = (string) $value;

        if (mb_strwidth($value, 'UTF-8') <= $limit) {
            return $value;
        }

        return rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8')) . $end;
    }


    public static function domain($url): string
    {
        return str_replace('www.', '', parse_url(strtolower($url), PHP_URL_HOST));
    }


    public static function https($url)
    {
        return str_replace('http://', 'https://', $url);
    }


    /**
     * Transliterate a UTF-8 value to ASCII.
     *
     * @see https://github.com/illuminate/support
     *
     * @param  string  $value
     * @param  string  $language
     * @return string
     */
    public static function ascii($value, $language = 'en')
    {
        return ASCII::to_ascii((string) $value, $language);
    }


    public static function filename($filename)
    {
        return strtolower(ASCII::to_filename($filename));
    }

    public static function az($value)
    {
        $value = static::ascii($value);

        // Remove all characters that are not dashes, letters, numbers, or underscore.
        $value = preg_replace('![^\-\pL\pN\_]+!u', '', $value);

        return $value;
    }

    public static function azLower($value)
    {
        return static::lower(static::az($value));
    }


    /**
     * Generate a URL friendly "slug" from a given string.
     *
     * @param  string  $title
     * @param  string  $language
     * @return string
     */
    public static function slug($value, $language = 'en')
    {
        if (is_array($value)) {
            if (!empty($value)) {
                // needs improvement to convert any kind of array
                // and generate cleaner output
                if (isset($value[0])) {
                    $value = implode(' ', $value);
                } else {
                    $value = http_build_query($value);
                    $value = str_replace('=', '-', $value);
                    $value = str_replace('&', '-', $value);
                }
            } else {
                $value = '';
            }
        }
        $value = static::ascii($value, $language);

        // Convert all underscores into dashes
        $value = preg_replace('![_]+!u', '-', $value);

        // Convert all slashes into dashes
        $value = preg_replace('![\/]+!u', '-', $value);

        // Replace @ with the word 'at'
        $value = str_replace('@', '-at-', $value);

        // Remove all characters that are not dashes, letters, numbers, or whitespace.
        $value = preg_replace('![^\-\pL\pN\s]+!u', '', static::lower($value));

        // Replace all dashes characters and whitespace by a single separator
        $value = preg_replace('![\-\s]+!u', '-', $value);

        return trim($value, '-');
    }


    public static function upper($value)
    {
        return mb_strtoupper($value, 'UTF-8');
    }


    public static function lower($value)
    {
        return mb_strtolower($value, 'UTF-8');
    }


    public static function camel($string, $language = 'en')
    {
        return lcfirst(static::studly($string, $language));
    }


    public static function studly($string, $language = 'en')
    {
        $string = static::ascii($string, $language);

        $string = ucwords(str_replace(['-', '_'], ' ', $string));

        return str_replace(' ', '', $string);
    }


    public static function snake($value, $language = 'en')
    {
        if (!ctype_lower($value)) {
            $value = preg_replace('/\s+/u', '', ucwords($value));

            $value = static::lower(preg_replace('/(.)(?=[A-Z])/u', '$1_', $value));
        }

        return str_replace('-', '_', static::slug($value, $language));
    }


    public static function kebab($value, $language = 'en')
    {
        $value = static::ascii($value, $language);

        if (!ctype_lower($value)) {
            $value = preg_replace('/\s+/u', '', ucwords($value));

            $value = static::lower(preg_replace('/(.)(?=[A-Z])/u', '$1-', $value));
        }

        return $value;
    }


    public static function constant($string, $language = 'en')
    {
        return strtoupper(static::snake($string, $language));
    }


    public static function title($string, bool $remove_dashes = false): string
    {
        $string = (string) $string;

        if ($remove_dashes) {
            $string = str_replace(['-', '_'], ' ', $string);
        }

        return mb_convert_case($string, MB_CASE_TITLE, 'UTF-8');
    }

    public static function isUrl($string): bool
    {
        if (empty($string)) {
            return false;
        }
        $string = (string) $string;

        return filter_var($string, FILTER_VALIDATE_URL)
            && checkdnsrr($string, 'A');
    }

    public static function isEmail($string): bool
    {
        if (empty($string)) {
            return false;
        }
        return filter_var((string) $string, FILTER_VALIDATE_EMAIL);
    }
}
