<?php

namespace Bond;

use Bond\Settings\Admin;
use Bond\Settings\Api;
use Bond\Settings\Bond;
use Bond\Settings\Html;
use Bond\Settings\Languages;
use Bond\Settings\Wp;
use Bond\Support\Fluent;
use Bond\Utils\Translate;
use Exception;

class Config extends Fluent
{

    public function __construct()
    {
        $this->app = [];
        $this->cache = [];
        $this->languages = [];
        $this->translation = [];
        $this->image = [
            'sizes' => [
                'thumbnail' => [150, 150, true],
                'medium' => [300, 300],
                'medium_large' => [768, 0],
                'large' => [1024, 1024],
            ],
        ];
        $this->wp = [];
        $this->services = [];
        $this->admin = [];
        $this->html = [];
        $this->api = [];
    }

    // Boot
    public function load(string $path = null)
    {
        // path
        if (!$path) {
            $path = app()->configPath();
        }
        if (!file_exists($path) || !is_dir($path)) {
            throw new Exception('Check your config path!');
        }

        // Load Locale and Translation first, so the following already have the ability to translate strings

        foreach ([
            'languages',
            'translation',
            'app',
            'cache',
            'image',
            'wp',
            'services',
            'admin',
            'html',
            'api',
        ] as $key) {
            $file = $path . DIRECTORY_SEPARATOR . $key . '.php';

            // add values
            if (file_exists($file)) {
                $this->add([
                    $key => require $file,
                ]);
            }

            // setup
            $this->{'setup' . ucfirst($key)}();
        }
    }

    protected function setupLanguages()
    {
        if ($this->languages->codes) {
            foreach ($this->languages->codes as $code => $values) {
                Languages::add($code, (array) $values);
            }
        }
        Languages::setCurrentFromRequest();
    }

    protected function setupTranslation()
    {
        Translate::setService($this->translation->service ?: '');

        if (isset($this->translation->translate_on_save)) {
            Translate::onSavePost($this->translation->translate_on_save);
        }
    }

    protected function setupApp()
    {
        // Timezone
        if ($this->app->timezone) {

            // found a major bug/feature on WordPress itself
            // it seems to set the date_default_timezone_set to UTC itself
            // so changing again here will create time to be wrong everywhere (ACF, etc)

            // will read source WP code to understand more what to do
            // may just remove, but will need doc to let users know

            // date_default_timezone_set($this->app->timezone);
        }

        // Save post/terms hook
        Bond::addSavePostHook();
        Bond::addSaveTermHook();

        if ($this->app->map_links) {
            Bond::mapLinks();
        }
        // TODO will change name
        if ($this->app->set_multilanguage_titles_and_slugs) {
            Bond::ensureTitlesAndSlugs($this->app->set_multilanguage_titles_and_slugs);
        }
    }

    protected function setupCache()
    {
        // empty, here for subclasses override
    }

    protected function setupImage()
    {
        Wp::sanitizeFilenames(); // always

        if ($this->image->quality) {
            Wp::setImageQuality($this->image->quality);
        }

        if ($this->image->sizes) {
            Wp::addImageSizes((array) $this->image->sizes);
        }

        if ($this->image->editor_sizes) {
            Admin::setEditorImageSizes((array) $this->image->editor_sizes);
        }
    }


    protected function setupWp()
    {
        // Settings
        Wp::updateSettings();

        // Protects WP redirect on front pages
        if (
            Languages::isMultilanguage()
            || $this->wp->front_page_redirect === false
        ) {
            Wp::preventFrontPageRedirect();
        }

        if ($this->wp->force_https) {
            Wp::forceHttps();
        }
    }

    protected function setupServices()
    {
        // ACF
        if (app()->hasAcf()) {
            // Add Google Maps API Key
            if ($key = $this->get('services.google_maps.key')) {
                \add_filter('acf/fields/google_map/api', function ($api) use ($key) {
                    $api['key'] = $key;
                    return $api;
                });
            }
        }
    }

    protected function setupAdmin()
    {
        if ($this->admin->theming) {
            Admin::enableTheming();
        }
        if ($this->admin->hide_posts) {
            Admin::hidePosts();
        }

        // actions that can run only on backend
        if (\is_admin()) {
            if ($this->admin->hide_title) {
                Admin::hideTitle($this->admin->hide_title);
            }
        }
    }

    protected function setupHtml()
    {
        if ($this->html->rss === false) {
            Html::disableRss();
        } elseif ($this->html->rss) {
            Html::enableRss();
        }
        if ($this->html->emojis === false) {
            Html::disableEmojis();
        }
        if ($this->html->shortlink === false) {
            Html::disableShortlink();
        }
        if ($this->html->wp_embed === false) {
            Html::disableWpEmbed();
        }
        if ($this->html->block_library === false) {
            Html::disableBlockLibrary();
        }
        if ($this->html->body_classes === false) {
            Html::resetBodyClasses();
        }
        if ($this->html->jetpack_includes === false) {
            Html::disableJetpackIncludes();
        }
        if ($this->html->admin_bar === false) {
            Html::disableAdminBar();
        }
    }

    protected function setupApi()
    {
        if ($this->api->disable) {
            Api::disable();
        }
        if ($this->api->header === false) {
            Api::disableHeader();
        }
        if ($this->api->default_routes === false) {
            Api::disableDefaultRoutes();
        }
        if ($this->api->only_logged_in) {
            Api::onlyLoggedIn();
        }
    }
}
