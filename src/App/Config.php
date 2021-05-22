<?php

namespace Bond\App;

use Bond\Settings\Admin;
use Bond\Settings\Api;
use Bond\Settings\Html;
use Bond\Settings\Language;
use Bond\Settings\Wp;
use Bond\Support\Fluent;
use Bond\Utils\Image;
use Exception;

class Config extends Fluent
{
    protected App $container;
    public Fluent $app;
    public Fluent $cache;
    public Fluent $language;
    public Fluent $translation;
    public Fluent $multilanguage;
    public Fluent $image;
    public Fluent $meta;
    public Fluent $wp;
    public Fluent $services;
    public Fluent $admin;
    public Fluent $html;
    public Fluent $api;

    public function __construct(App $container)
    {
        $this->container = $container;
        $this->app = new Fluent();
        $this->cache = new Fluent();
        $this->language = new Fluent();
        $this->translation = new Fluent();
        $this->multilanguage = new Fluent();
        $this->image = new Fluent([
            'sizes' => [
                'thumbnail' => [150, 150, true],
                'medium' => [300, 300],
                'medium_large' => [768, 0],
                'large' => [1024, 1024],
            ],
        ]);
        $this->meta = new Fluent([
            'enabled' => true,
        ]);
        $this->wp = new Fluent();
        $this->services = new Fluent();
        $this->admin = new Fluent();
        $this->html = new Fluent();
        $this->api = new Fluent();
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
            'language',
            'translation',
            'multilanguage',
            'app',
            'cache',
            'image',
            'meta',
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

    protected function languageSettings()
    {
        if ($this->language->codes) {
            foreach ($this->language->codes as $code => $values) {
                Language::add($code, (array) $values);
            }
        }
        Language::setCurrentFromRequest();
    }

    protected function translationSettings()
    {
        $translation = $this->container->get('translation');

        if ($this->translation->written_language) {
            $translation->setWrittenLanguage($this->translation->written_language);
        }

        if ($this->translation->service) {
            $translation->setService($this->translation->service);
        }
    }

    protected function multilanguageSettings()
    {
        $multilanguage = $this->container->get('multilanguage');

        if ($this->multilanguage->post_types) {
            $multilanguage->postTypes($this->multilanguage->post_types);
        }

        if ($this->multilanguage->taxonomies) {
            $multilanguage->taxonomies($this->multilanguage->taxonomies);
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
            Image::setSizes((array) $this->image->sizes);
        }

        if ($this->image->editor_sizes) {
            Admin::setEditorImageSizes((array) $this->image->editor_sizes);
        }
    }

    protected function metaSettings()
    {
        // auto initialize Meta, registers WP hooks
        if ($this->meta->enabled && \wp_using_themes()) {
            $this->container->get('meta')->config($this->meta);
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
            Language::isMultilanguage()
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
        if ($this->admin->add_login_css) {
            Admin::addLoginCss();
        }
        if ($this->admin->add_admin_css) {
            Admin::addAdminCss();
        }
        if ($this->admin->add_editor_css) {
            Admin::addEditorCss();
        }
        if ($this->admin->disable_admin_color_picker) {
            Admin::disableAdminColorPicker();
        }
        if ($this->admin->remove_update_nag) {
            Admin::removeUpdateNag();
        }
        if (isset($this->admin->footer_credits)) {
            Admin::addFooterCredits();
        }
        if ($this->admin->remove_wp_version) {
            Admin::removeWpVersion();
        }
        if ($this->admin->hide_posts) {
            Admin::hidePosts();
        }
        if ($this->admin->replace_dashboard) {
            Admin::replaceDashboard();
        }
        if ($this->admin->remove_administration_menus) {
            Admin::removeAdministrationMenus();
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
        if ($this->html->reset_body_classes) {
            Html::resetBodyClasses();
        }
        if ($this->html->unwrap_paragraphs) {
            Html::unwrapParagraphs();
        }
        if ($this->html->h6_captions) {
            Html::h6Captions();
        }
        if ($this->html->cleanup_head) {
            Html::cleanupHead();
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
