<?php

namespace Bond\Services;

use Bond\Settings\Languages;
use Bond\Utils\Cast;
use Bond\Utils\Query;
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

    protected string $written_language;

    public function __construct()
    {
        $this->addTranslatePostHook();
        // TODO add options pages too

        // add_action('acf/save_post', function ($post_id) {
        //     if ($post_id === 'options') {
        //     }
        // });

        $this->setWrittenLanguage(Languages::getDefault());
    }

    public function getWrittenLanguage(): string
    {
        return $this->written_language;
    }

    public function setWrittenLanguage(string $code)
    {
        $this->written_language = $code;
    }

    public function setService(string $service)
    {
        $this->service = $service;
    }

    public function hasService(): bool
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

    // API

    public function get(
        $string,
        string $language_code = null,
        string $written_language = null,
        string $context = null,

    ): string {
        $string = (string) $string;

        // we won't translate empty strings
        if (empty($string)) {
            return $string;
        }
        // Fallback to gettext
        if (!$this->hasService()) {
            if ($context) {
                return _x($string, $context, app()->id());
            }
            return __($string, app()->id());
        }

        // fallbacks to current language
        $language_code = Languages::code($language_code);

        // defaults
        if (!$written_language) {
            $written_language = $this->getWrittenLanguage();
        }

        // if is dev translate all languages
        if (app()->isDevelopment()) {
            $res = $string;

            foreach (Languages::codes() as $code) {
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
        // don't call inside here

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




    // Translate Posts Hook
    // TODO maybe should go into a specific class, App\Multilanguage

    public function addTranslatePostHook()
    {
        \add_action('Bond/translate_post', [$this, 'translatePostHook'], 1);
    }

    public function removeTranslatePostHook()
    {
        \remove_action('Bond/translate_post', [$this, 'translatePostHook'], 1);
    }

    public function translatePostHook(int $post_id)
    {
        if (!Languages::isMultilanguage()) {
            return;
        }

        // Translate all fields
        // auto activated, later can allow config
        $this->translateAllFields($post_id);

        // Mulilanguage titles and slugs
        $this->ensureTitleAndSlug($post_id);
    }


    public function translateAllFields(int $post_id)
    {
        if (!$this->hasService() || !app()->hasAcf()) {
            return;
        }

        // TODO it's not working well for file fields with url inside Flex fields
        // could use get_field_objects($post_id, false) to reconstruct the keys, with the values not formated
        $fields = \get_fields($post_id);
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
            \update_field($name, $value, $post_id);
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

                        // key
                        $lang_key = $unlocalized_key . Languages::fieldsSuffix($c);

                        // value
                        $v = $target[$lang_key] ?? '';
                        if (empty($v) || !is_string($v)) {
                            continue;
                        }
                        if (Str::isEmail($v) || Str::isUrl($v)) {
                            continue;
                        }

                        // translated
                        $t = $this->fromTo($c, $code, $v);
                        if ($t) {
                            $translated[$key] = $t;
                            $changed++;
                            break 2;
                        }
                    }
                }
            }
        }

        return $translated;
    }


    protected array $wp_titles = [];

    public function updateWpTitles($post_type = true)
    {
        if ($post_type === true) {
            $this->wp_titles = ['all'];
        } elseif ($post_type === false) {
            $this->wp_titles = [];
        } else {
            $this->wp_titles[] = $post_type;
        }
    }

    public function ensureTitleAndSlug(int $post_id)
    {
        if (!app()->hasAcf()) {
            return;
        }

        $post = Cast::wpPost($post_id);
        if (!$post) {
            return;
        }
        // not for front page
        if ($post->post_type === 'page' && \is_front_page()) {
            return;
        }

        $codes = Languages::codes();

        // get default language's title
        $default_code = Languages::getDefault();
        $default_suffix = Languages::fieldsSuffix($default_code);
        $default_title = \get_field('title' . $default_suffix, $post->ID);

        // or get the next best match
        if (empty($default_title)) {
            foreach ($codes as $code) {
                if ($code === $default_code) {
                    continue;
                }

                $suffix = Languages::fieldsSuffix($code);
                $title = \get_field('title' . $suffix, $post->ID);

                // if found, we will translate and set the default_title
                if (!empty($title)) {
                    $default_title = $this->fromTo($code, $default_code, $title);

                    \update_field(
                        'title' . $default_suffix,
                        $default_title,
                        $post->ID
                    );
                    break;
                }
            }
        }

        // no title yet, just skip
        if (empty($default_title)) {
            return;
        }

        // if WP title is different, update it
        if (
            $post->post_title !== $default_title
            && (in_array('all', $this->wp_titles)
                || in_array($post->post_type, $this->wp_titles))
        ) {
            \wp_update_post([
                'ID' => $post->ID,
                'post_title' => $default_title,
            ]);
            $post->post_title = $default_title;
        }

        // we don't allow empty titles
        foreach ($codes as $code) {
            if ($code === $default_code) {
                continue;
            }

            $suffix = Languages::fieldsSuffix($code);
            $title = \get_field('title' . $suffix, $post->ID);

            if (empty($title)) {
                \update_field(
                    'title' . $suffix,
                    $this->fromTo($default_code, $code, $default_title) ?: $default_title,
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
            $suffix = Languages::fieldsSuffix($code);

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
            if (Languages::isDefault($code)) {

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
