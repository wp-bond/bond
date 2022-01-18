<?php

namespace Bond\Services;

use Bond\Settings\Language;
use Bond\Support\Fluent;
use Bond\Support\FluentList;
use Exception;

// AWS Translate
// https://docs.aws.amazon.com/translate/latest/dg/what-is.html#language-pairs

// Google Translate
// https://cloud.google.com/translate/docs/languages

class Translation implements ServiceInterface
{
    protected bool $enabled = false;
    protected string $service = 'wp';
    protected string $written_language = 'en';
    protected array $credentials = [];

    protected array $glossaries = [];
    protected string $storage_path;

    private ?string $last_gettext_locale = null;


    public function config(
        ?bool $enabled = null,
        ?string $written_language = null,
        ?string $service = null,
        ?array $credentials = null,
    ) {
        if (isset($enabled)) {
            if ($enabled) {
                $this->enable();
            } else {
                $this->disable();
            }
        }
        if (isset($written_language)) {
            $this->written_language = $written_language;
        }
        if (isset($service)) {
            $this->service = $service;
        }
        if (isset($credentials)) {
            $this->credentials = $credentials;
        }
    }

    public function enable()
    {
        if (!$this->enabled) {
            $this->enabled = true;

            // load WP GetText if needed
            $this->loadThemeGetText();
        }
    }

    public function disable()
    {
        if ($this->enabled) {
            $this->enabled = false;

            // unload WP GetText
            $this->unloadThemeGetText();
        }
    }

    public function getWrittenLanguage(): string
    {
        return $this->written_language;
    }

    public function hasTranslationApi(): bool
    {
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

    /**
     * Only loads if the translation service is 'wp' and if is enabled. So nothing will be loaded if the translation is being handled by Google or AWS.
     *
     * @see https://developer.wordpress.org/reference/functions/load_theme_textdomain/
     */
    public function loadThemeGetText()
    {
        global $locale;
        if ($locale !== $this->last_gettext_locale) {

            // unload WP GetText
            $this->unloadThemeGetText();

            // load WP gettext, if not handling translations automatically
            if ($this->enabled && $this->service === 'wp') {
                $this->last_gettext_locale = $locale;
                \load_theme_textdomain(app()->id(), app()->languagesPath());
            }
        }
    }

    public function unloadThemeGetText()
    {
        global $l10n;
        unset($l10n[app()->id()]);
        $this->last_gettext_locale = null;
    }


    // API

    public function get(
        $input,
        string $language_code = null,
        string $written_language = null,
        string $context = null
    ) {
        if (!$this->enabled) {
            return $input;
        }

        // skip if empty or numeric
        if (empty($input) || is_numeric($input)) {
            return $input;
        }

        // recurse if array|Fluent|FluentList
        if (
            is_array($input)
            || $input instanceof Fluent
            || $input instanceof FluentList
        ) {

            // create a new object to ouput
            if (is_array($input)) {
                $out = [];
            } elseif (
                $input instanceof Fluent
                || $input instanceof FluentList
            ) {
                $class = get_class($input);
                $out = new $class;
            }

            // translate each value recursively
            foreach ($input as $k => $in) {
                $out[$k] = $this->get(
                    $in,
                    $language_code,
                    $written_language,
                    $context
                );
            }
            return $out;
        }

        // translate string
        return $this->_get(
            (string) $input,
            $language_code,
            $written_language,
            $context
        );
    }

    private function _get(
        string $string,
        string $language_code = null,
        string $written_language = null,
        string $context = null

    ): string {

        // we won't translate empty or numeric strings
        if (empty($string) || is_numeric($string)) {
            return $string;
        }

        // Fallback to WP gettext
        if ($this->service === 'wp') {
            if ($context) {
                return _x($string, $context, app()->id());
            }
            return __($string, app()->id());
        }

        // skip if no service is available
        if (!$this->hasTranslationApi()) {
            return $string;
        }

        // ensures it's a language code
        // fallbacks to current language if invalid
        $language_code = Language::code($language_code);

        // defaults
        if (!$written_language) {
            $written_language = $this->written_language;
        }

        // if is dev translate all languages
        if (app()->isDevelopment()) {
            $res = $string;

            foreach (Language::codes() as $code) {
                if ($code === $written_language) {
                    continue;
                }

                $t = $this->find(
                    $string,
                    $code,
                    $written_language,
                    $context
                );

                if ($code === $language_code) {
                    $res = $t;
                }
            }
            return $res;
        }

        // Skip if already in the written language
        if ($language_code === $written_language) {
            return $string;
        }

        return $this->find(
            $string,
            $language_code,
            $written_language,
            $context
        );
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
        string $written_language,
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
                        $written_language,
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
                        $written_language,
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
        if (empty($text) || !$this->hasTranslationApi()) {
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
        // Google has these issues:
        // if the HTML formatter is used (default), we have problems with line breaks, either on textarea, or wysiwyg
        // if the TEXT formatter is used, the result is precise, but sometimes it adds spaces inside URLs, breaking then
        // prefer AWS for now, or fix manually the line breaks on textareas after translated

        // $options['format'] = 'text';

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
            $client = new \Google\Cloud\Translate\V2\TranslateClient([
                'key' => $this->credentials['google']['key'] ?? null,
            ]);
        }
        return $client;
    }

    protected function translateAws($text, array $options = []): string
    {
        // TEMP CODE to remove language locale
        // maybe look into aws sdk source code if there is a list of the supported languages
        $source = !empty($options['source'])
            ? Language::shortCode($options['source'])
            : 'auto';

        $target = Language::shortCode($options['target'] ?? Language::code());

        // TODO try to break long texts and translate in batches to overcome the size limit
        // TextSizeLimitExceededException

        // detect if is html, then break into nodes
        // if text, break into phrases by dots (if possible trying to avoid breaking urls.. maybe split by dot and space after, or even \n) --- then maybe split by words if the phrases were not enough

        // if html
        // would have to be smart enough to dig into nodes too, if we translate a <div>with a huge structure inside</div>, we must translate each part individually

        // to check if is html
        // if($string != strip_tags($string)) {
        // but check for false positives if a text constains "<text"
        // }
        // or this https://stackoverflow.com/a/45291897
        // or https://stackoverflow.com/a/10778067

        try {
            $result = $this->awsClient()->translateText([
                'SourceLanguageCode' => $source,
                'TargetLanguageCode' => $target,
                'Text' => $text,
            ]);
        } catch (Exception $e) {
            return '';
        }

        return $result['TranslatedText'] ?? '';
    }

    protected function awsClient()
    {
        // TODO use Config to pass these settings
        // don't call inside here

        static $client = null;
        if (!$client) {
            $client = new \Aws\Translate\TranslateClient([
                'region' => $this->credentials['aws']['region'] ?? 'us-east-1',
                'version' => 'latest',
                'use_aws_shared_config_files' => false,
                'credentials' => $this->credentials['aws'],
            ]);
        }
        return $client;
    }
}
