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
    public Fluent $image;
    public Fluent $meta;
    public Fluent $wp;
    public Fluent $services;
    public Fluent $admin;
    public Fluent $html;
    public Fluent $api;

    // we leave Language and Translation first, so the next ones already have the ability to translate strings
    protected array $configs = [
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
        'admin_columns',
        'html',
        'api',
        'sitemap',
        'rss',
        'view',
    ];

    public function __construct(App $container)
    {
        $this->container = $container;
        $this->app = new Fluent();
        $this->cache = new Fluent();
        $this->language = new Fluent();
        $this->image = new Fluent([
            'sizes' => [
                'thumbnail' => [150, 150, true],
                'medium' => [300, 300],
                'medium_large' => [768, 0],
                'large' => [1024, 1024],
            ],
        ]);
        $this->meta = new Fluent();
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

        // load config files
        foreach ($this->configs as $key) {
            $filepath = $path . DIRECTORY_SEPARATOR . $key . '.php';

            $data = is_file($filepath) ? require $filepath : null;

            if (!empty($data)) {

                // store config
                $this->add([
                    $key => $data
                ]);

                // configure
                if (method_exists($this, $key . 'Settings')) {
                    $this->{$key . 'Settings'}();
                } else {
                    // auto config from container
                    $this->container->get($key)->config(...$data);
                }
            }
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
        if (Wp::isCli()) {
            $this->cache->enabled = false;
        }

        if ($this->cache->class) {
            $this->container->addShared('cache', $this->cache->class);
        }

        $this->container->cache()->enabled($this->cache->enabled);

        $this->container->cache()->ttl($this->cache->ttl);

        if ($this->cache->path) {
            $this->container->cache()->path($this->cache->path);
        }
    }

    protected function imageSettings()
    {
        Wp::sanitizeFilenames(); // always

        if ($this->image->quality) {
            Wp::setImageQuality($this->image->quality);
        }

        if ($this->image->disable_limit) {
            Wp::disableImageLimit();
        }

        if ($this->image->sizes) {
            Wp::addImageSizes((array) $this->image->sizes);
            Image::setSizes((array) $this->image->sizes);
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
            Language::isMultilanguage()
            || $this->wp->disable_front_page_redirect
        ) {
            Wp::disableFrontPageRedirect();
        }

        if ($this->wp->force_https) {
            Wp::forceHttps();
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
        // TODO, pass all these into a Admin::config method

        if ($this->admin->add_login_css) {
            Admin::addLoginCss();
        }
        if ($this->admin->add_admin_css) {
            Admin::addAdminCss();
        }
        if ($this->admin->add_editor_css) {
            Admin::addEditorCss();
        }
        if ($this->admin->add_color_scheme) {
            Admin::addColorScheme();
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
