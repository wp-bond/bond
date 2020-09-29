<?php

namespace Bond;

use Bond\Settings\Admin;
use Bond\Settings\Api;
use Bond\Settings\Bond;
use Bond\Settings\Html;
use Bond\Settings\Languages;
use Bond\Settings\Wp;
use Bond\Support\Fluent;
use Exception;

class Config extends Fluent
{
    protected App $container;

    public function __construct(App $container)
    {
        $this->container = $container;
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
            $this->{$key . 'Settings'}();
        }
    }

    protected function languagesSettings()
    {
        if ($this->languages->codes) {
            foreach ($this->languages->codes as $code => $values) {
                Languages::add($code, (array) $values);
            }
        }
        Languages::setCurrentFromRequest();
    }

    protected function translationSettings()
    {
        $translation = $this->container->get('translation');

        if ($this->translation->service) {
            $translation->setService($this->translation->service);
        }

        if (isset($this->translation->translate_on_save)) {
            $translation->translateOnSavePost($this->translation->translate_on_save);
        }
    }

    protected function appSettings()
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

    protected function cacheSettings()
    {
        // empty, here for subclasses override
    }

    protected function imageSettings()
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


    protected function wpSettings()
    {
        // Settings
        Wp::updateSettings();

        if ($this->wp->disable_user_registration) {
            Wp::disableUserRegistration();
        }

        // Protects WP redirect on front pages
        if (
            Languages::isMultilanguage()
            || $this->wp->disable_front_page_redirect
        ) {
            Wp::disableFrontPageRedirect();
        }

        if ($this->wp->force_https) {
            Wp::forceHttps();
        }
        if ($this->wp->disable_sitemaps) {
            Wp::disableSitemaps();
        }
    }

    protected function servicesSettings()
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

    protected function adminSettings()
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

    protected function htmlSettings()
    {
        if ($this->html->disable_rss) {
            Html::disableRss();
        }
        if ($this->html->enable_rss) {
            Html::enableRss();
        }
        if ($this->html->disable_emojis) {
            Html::disableEmojis();
        }
        if ($this->html->disable_shortlink) {
            Html::disableShortlink();
        }
        if ($this->html->disable_wp_embed) {
            Html::disableWpEmbed();
        }
        if ($this->html->disable_block_library) {
            Html::disableBlockLibrary();
        }
        if ($this->html->reset_body_classes) {
            Html::resetBodyClasses();
        }
        if ($this->html->disable_jetpack_includes) {
            Html::disableJetpackIncludes();
        }
        if ($this->html->disable_admin_bar) {
            Html::disableAdminBar();
        }
    }

    protected function apiSettings()
    {
        if ($this->api->disable) {
            Api::disable();
        }
        if ($this->api->prefix) {
            Api::changePrefix($this->api->prefix);
        }
        if ($this->api->disable_header) {
            Api::disableHeader();
        }
        if ($this->api->disable_default_routes) {
            Api::disableDefaultRoutes();
        }
        if ($this->api->disable_root_route) {
            Api::disableRootRoute();
        }
        if ($this->api->disable_oembed) {
            Api::disableOembed();
        }
        if ($this->api->only_logged_in) {
            Api::onlyLoggedIn();
        }
    }
}
