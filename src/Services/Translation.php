<?php

namespace Bond\Services;

use Bond\Settings\Languages;
use Bond\Utils\Str;

// AWS Translate
// https://docs.aws.amazon.com/translate/latest/dg/what-is.html#language-pairs

// Google Translate
// https://googleapis.github.io/google-cloud-php/#/docs/google-cloud/v0.130.0/translate/v2/translateclient

class Translation
{
    protected array $glossaries = [];

    protected string $service = '';

    protected string $storage_path;

    public function __construct()
    {
    }

    public function setService(string $service)
    {
        $this->service = $service;
    }

    public function hasService(): bool
    {
        if (!isset($this->service)) {
            $this->service = config('translation.service') ?? '';
        }
        return $this->service === 'google' || $this->service === 'aws';
    }

    public function setStoragePath(string $path)
    {
        $this->storage_path = $path;

        // create folder if needed
        if (!file_exists($this->storage_path)) {
            mkdir($this->storage_path, 0755, true);
        }
    }

    protected function languagePath(string $language_code): string
    {
        if (!isset($this->storage_path)) {
            $this->setStoragePath(app()->languagesPath());
        }
        return $this->storage_path
            . DIRECTORY_SEPARATOR . $language_code . '.json';
    }

    // API

    public function get(
        $string,
        string $language_code = null,
        string $context = null
    ): string {
        $string = (string) $string;

        // we won't translate empty strings
        if (empty($string)) {
            return $string;
        }

        // Skip if is not multilanguage
        if (!Languages::isMultilanguage()) {
            return $string;
        }

        // Fallback to gettext
        if (!$this->hasService()) {
            if ($context) {
                return _x($string, $context, app()->id());
            }
            return __($string, app()->id());
        }

        // fallback to current language
        $language_code = Languages::code($language_code);

        // if is dev translate all languages
        if (app()->isDevelopment()) {
            $res = $string;

            foreach (Languages::codes() as $code) {
                if (Languages::isDefault($code)) {
                    continue;
                }

                $t = $this->find($string, $code, $context);

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

        return $this->find($string, $language_code, $context);
    }

    public function fromTo(
        string $from_lang,
        string $to_lang,
        string $text
    ): string {
        if ($from_lang === $to_lang) {
            return $text;
        }
        return $this->translate($text, [
            'source' => $from_lang,
            'target' => $to_lang,
        ]);
    }

    public function to(string $lang, string $text): string
    {
        return $this->translate($text, [
            'target' => $lang,
        ]);
    }

    protected function find(
        string $string,
        string $language_code,
        string $context = null
    ): string {

        // Load glossary if not already
        $this->loadGlossary($language_code);

        // to know when to save the JSON back to disk
        $updated = false;

        // translate
        if ($context) {
            $t = $this->glossaries[$language_code]['_x'][$context][$string] ?? '';

            if (empty($t)) {
                $this->glossaries[$language_code]['_x'][$context][$string]
                    = $updated
                    = $t
                    = $this->fromTo(
                        Languages::getDefault(),
                        $language_code,
                        $string
                    );
            }
        } else {
            $t = $this->glossaries[$language_code][$string] ?? '';

            if (empty($t)) {
                $this->glossaries[$language_code][$string]
                    = $updated
                    = $t
                    = $this->fromTo(
                        Languages::getDefault(),
                        $language_code,
                        $string
                    );
            }
        }

        // save to disk
        if ($updated) {

            ksort($this->glossaries[$language_code], SORT_NATURAL);

            file_put_contents(
                $this->languagePath($language_code),
                json_encode($this->glossaries[$language_code], JSON_PRETTY_PRINT)
            );
        }

        return $t;
    }

    protected function loadGlossary(string $language_code)
    {
        if (!isset($this->glossaries[$language_code])) {
            $path = $this->languagePath($language_code);

            if (is_readable($path)) {
                $this->glossaries[$language_code] = json_decode(file_get_contents($path), true);
            } else {
                $this->glossaries[$language_code] = [];
            }
        }
    }

    protected function translate($text, array $options = []): string
    {
        if (empty($text) || !$this->hasService()) {
            return '';
        }
        if ($this->service === 'google') {
            return $this->translateGoogle($text, $options);
        }
        if ($this->service === 'aws') {
            return $this->translateAws($text, $options);
        }
        return '';
    }

    protected function translateGoogle($text, array $options = []): string
    {
        $result = $this->googleClient()->translate(
            $text,
            $options
        );
        return $result['text'] ?? '';
    }

    protected function googleClient()
    {
        static $client = null;
        if (!$client) {
            $client = new \Google\Cloud\Translate\V2\TranslateClient();
        }
        return $client;
    }

    protected function translateAws($text, array $options = []): string
    {
        $result = $this->awsClient()->translateText([
            'SourceLanguageCode' => $options['source'] ?? 'auto',
            'TargetLanguageCode' => $options['target'] ?? Languages::code(),
            'Text' => $text,
        ]);
        return $result['TranslatedText'] ?? '';
    }

    protected function awsClient()
    {
        // TODO use Config to pass these settings

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



    // WordPress Helpers
    public function onSavePost($post_types)
    {
        // runs on priority 9, before the default priority 10
        // so it's already translated if needed

        if (!app()->hasAcf()) {
            return;
        }

        if ($post_types === true) {
            \add_action(
                'Bond/save_post',
                [$this, 'allFields'],
                9
            );
        } elseif (is_array($post_types)) {
            foreach ($post_types as $post_type) {
                \add_action(
                    'Bond/save_post/' . $post_type,
                    [$this, 'allFields'],
                    9
                );
            }
        }
    }

    protected function allFields($post)
    {
        if (!Languages::isMultilanguage()) {
            return;
        }

        $fields = \get_fields($post->ID);
        if (empty($fields)) {
            return;
        }

        $updated = 0;
        $translated = $this->translateMissingFields(
            (array) $fields,
            true,
            $updated
        );
        // dd($translated, $updated);

        foreach ($translated as $name => $value) {
            \update_field($name, $value, $post->ID);
        }
    }

    protected function translateMissingFields(
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
                $result = $this->translateMissingFields($value, false, $changed);

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
                            $t = $this->fromTo($c, $code, $target[$lang_key]);

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
}
