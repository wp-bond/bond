<?php

namespace Bond\Utils;

use Bond\Settings\Languages;

// TODO AFTER we have admin panel to handle translation updates:
// we should namespace the translations with env
// for example en.local.json en.production.json en.staging.json
// so we could handle production translations without syncing from local

// AWS Translate
// https://docs.aws.amazon.com/translate/latest/dg/what-is.html#language-pairs

// Google Translate
// https://googleapis.github.io/google-cloud-php/#/docs/google-cloud/v0.130.0/translate/v2/translateclient

class Translate
{
    private static array $glossaries = [];

    private static string $service;


    public static function onSavePost($post_types)
    {
        // runs on priority 9, before the default priority 10
        // so it's already translated if needed

        if (!config()->hasAcf()) {
            return;
        }

        if ($post_types === true) {
            \add_action(
                'Bond/save_post',
                [static::class, 'allFields'],
                9
            );
        } elseif (is_array($post_types)) {
            foreach ($post_types as $post_type) {
                \add_action(
                    'Bond/save_post/' . $post_type,
                    [static::class, 'allFields'],
                    9
                );
            }
        }
    }

    public static function allFields($post)
    {
        if (!Languages::isMultilanguage()) {
            return;
        }

        $fields = \get_fields($post->ID);
        if (empty($fields)) {
            return;
        }

        $updated = 0;
        $translated = self::translateMissingFields(
            (array) $fields,
            true,
            $updated
        );
        // dd($translated, $updated);

        foreach ($translated as $name => $value) {
            \update_field($name, $value, $post->ID);
        }
    }

    private static function translateMissingFields(
        array $target,
        $narrow = true,
        &$changed = 0
    ): array {

        $translated = [];
        // dd($target);

        foreach ($target as $key => $value) {

            // recurse
            if (is_array($value)) {

                $before = $changed;
                $result = self::translateMissingFields($value, false, $changed);

                // include entire array if not narrow
                // or if something was translated
                if (!$narrow || $before !== $changed) {
                    $translated[$key] = $result;
                }
                continue;
            }

            // only empty strings will pass
            if ($value !== '') {
                if ($narrow) {
                    continue;
                } else {
                    $translated[$key] = $value;
                    continue;
                }
            }

            // for empty string that is suffixed with a lang
            // we will try to find a match in another language
            // and translate
            foreach (Languages::codes() as $code) {

                $suffix = Languages::fieldsSuffix($code);

                if (Str::endsWith($key, $suffix)) {

                    $unlocalized_key = substr($key, 0, -strlen($suffix));

                    foreach (Languages::codes() as $c) {
                        if ($c === $code) {
                            continue;
                        }
                        $lang_key = $unlocalized_key . Languages::fieldsSuffix($c);

                        if (!empty($target[$lang_key])) {
                            $t = Translate::fromTo($c, $code, $target[$lang_key]);

                            if ($t) {
                                $translated[$key] = $t;
                                $changed++;
                                break 2;
                            }
                        }
                    }
                }
            }
        }

        return $translated;
    }



    public static function setService(string $service)
    {
        self::$service = $service;
    }

    public static function hasService(): bool
    {
        if (!isset(self::$service)) {
            self::$service = config('translation.service') ?? '';
        }
        return self::$service === 'google' || self::$service === 'aws';
    }


    public static function get(
        $string,
        string $language_code = null,
        string $context = null
    ): string {
        $string = (string) $string;

        // we won't translate empty strings
        if (empty($string)) {
            return $string;
        }

        // Fallback to gettext
        if (!self::hasService()) {
            if ($context) {
                return _x($string, $context, config()->id());
            }
            return __($string, config()->id());
        }

        // fallback to current language
        $language_code = Languages::code($language_code);

        // if is dev translate all languages
        if (config()->isDevelopment()) {
            $res = $string;

            foreach (Languages::codes() as $code) {
                if (Languages::isDefault($code)) {
                    continue;
                }

                $t = self::find($string, $code, $context);

                if ($code === $language_code) {
                    $res = $t;
                }
            }
            return $res;
        }

        // Skip if is the default language
        if (Languages::isDefault($language_code)) {
            return $string;
        }

        return self::find($string, $language_code, $context);
    }



    private static function find(
        string $string,
        string $language_code = null,
        string $context = null
    ): string {

        // Load glossary if not already
        if (!isset(self::$glossaries[$language_code])) {
            $path = config()->rootThemePath() . '/languages/' . $language_code . '.json';

            if (is_readable($path)) {
                self::$glossaries[$language_code] = json_decode(file_get_contents($path), true);
            } else {
                self::$glossaries[$language_code] = [];
            }
        }

        // to know when to save the JSON back to disk
        $updated = false;

        // translate
        if ($context) {
            $t = self::$glossaries[$language_code]['_x'][$context][$string] ?? '';

            if (empty($t)) {
                self::$glossaries[$language_code]['_x'][$context][$string]
                    = $updated
                    = $t
                    = Translate::fromTo(
                        Languages::getDefault(),
                        $language_code,
                        $string
                    );
            }
        } else {
            $t = self::$glossaries[$language_code][$string] ?? '';

            if (empty($t)) {
                self::$glossaries[$language_code][$string]
                    = $updated
                    = $t
                    = Translate::fromTo(
                        Languages::getDefault(),
                        $language_code,
                        $string
                    );
            }
        }

        // save to disk
        if ($updated) {
            $path = config()->rootThemePath() . '/languages/' . $language_code . '.json';

            ksort(self::$glossaries[$language_code], SORT_NATURAL);

            file_put_contents($path, json_encode(self::$glossaries[$language_code], JSON_PRETTY_PRINT));
        }

        return $t;
    }



    public static function fromTo(
        string $from_lang,
        string $to_lang,
        string $text
    ): string {
        if ($from_lang === $to_lang) {
            return $text;
        }
        return self::translate($text, [
            'source' => $from_lang,
            'target' => $to_lang,
        ]);
    }

    public static function to(string $lang, string $text): string
    {
        return self::translate($text, [
            'target' => $lang,
        ]);
    }

    private static function translate($text, array $options = []): string
    {
        if (empty($text) || !self::hasService()) {
            return '';
        }
        if (self::$service === 'google') {
            return self::translateGoogle($text, $options);
        }
        if (self::$service === 'aws') {
            return self::translateAws($text, $options);
        }
        return '';
    }

    private static function translateGoogle($text, array $options = []): string
    {
        $result = self::googleClient()->translate(
            $text,
            $options
        );
        return $result['text'] ?? '';
    }

    private static function googleClient()
    {
        static $client = null;
        if (!$client) {
            $client = new \Google\Cloud\Translate\V2\TranslateClient();
        }
        return $client;
    }


    private static function translateAws($text, array $options = []): string
    {
        $result = self::awsClient()->translateText([
            'SourceLanguageCode' => $options['source'] ?? 'auto',
            'TargetLanguageCode' => $options['target'] ?? Languages::code(),
            'Text' => $text,
        ]);
        return $result['TranslatedText'] ?? '';
    }

    private static function awsClient()
    {
        static $client = null;
        if (!$client) {
            $client = new \Aws\Translate\TranslateClient([
                'region' => config('translation.credentials.aws.region'),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('translation.credentials.aws.key'),
                    'secret' => config('translation.credentials.aws.secret'),
                ],
            ]);
        }
        return $client;
    }
}
