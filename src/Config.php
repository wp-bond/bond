<?php

namespace Bond;

use Bond\Settings\Admin;
use Bond\Settings\Api;
use Bond\Settings\App;
use Bond\Settings\Html;
use Bond\Settings\Languages;
use Bond\Settings\Wp;
use Bond\Support\Fluent;
use Bond\Utils\Translate;
use Exception;
use Mobile_Detect;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Config extends Fluent
{
    protected bool $is_production;
    protected bool $is_development;
    protected bool $is_staging;

    protected bool $is_mobile;
    protected bool $is_tablet;
    protected bool $is_desktop;

    protected string $theme_id;


    public function __construct()
    {
        $this->app = [
            'env' => c('APP_ENV') ?: 'production',
            'id' => $this->themeId(),
            'url' => \untrailingslashit(c('WP_HOME') ?: \get_site_url()),
        ];
        $this->cache = [
            'path' => $this->rootThemePath() . '/cache',
        ];
        $this->languages = [];
        $this->translation = [
            'service' => null,
        ];
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

        // process the device detection
        $this->deviceDetect();

        //
        mb_internal_encoding('UTF-8');
    }

    public function id(): string
    {
        return $this->app->id;
    }

    public function url(): string
    {
        return $this->app->url;
    }

    public function themeId(): string
    {
        return $this->theme_id
            ?? $this->theme_id = \get_stylesheet();
    }

    public function themePath(): string
    {
        return \STYLESHEETPATH;
    }

    public function themeDir(): string
    {
        return '/' . \WP_CONTENT_FOLDER . '/themes/' . $this->themeId();
    }

    public function themeUrl(): string
    {
        return $this->url() . $this->themeDir();
    }

    // TODO consider changing to basePath
    public function rootThemePath(): string
    {
        return \ROOT_APP_PATH . '/themes/' . $this->themeId();
    }

    public function wpAdminUrl(): string
    {
        return defined('WP_SITEURL')
            ? \WP_SITEURL . '/wp-admin'
            : \untrailingslashit(\get_admin_url());
    }

    public function isProduction(): bool
    {
        return $this->is_production
            ?? $this->is_production = $this->app->env === 'production';
    }

    public function isDevelopment(): bool
    {
        return $this->is_development
            ?? $this->is_development = $this->app->env === 'development';
    }

    public function isStaging(): bool
    {
        return $this->is_staging
            ?? $this->is_staging = $this->app->env === 'staging';
    }

    public function isMobile(): bool
    {
        return $this->is_mobile;
    }

    public function isTablet(): bool
    {
        return $this->is_tablet;
    }

    public function isDesktop(): bool
    {
        return $this->is_desktop;
    }

    protected function deviceDetect()
    {
        // first honor Cloudfront
        $header = 'HTTP_CLOUDFRONT_IS_MOBILE_VIEWER';
        if (isset($_SERVER[$header])) {
            $this->is_mobile = (string) $_SERVER[$header] === 'true';
        }
        $header = 'HTTP_CLOUDFRONT_IS_TABLET_VIEWER';
        if (isset($_SERVER[$header])) {
            $this->is_tablet = (string) $_SERVER[$header] === 'true';
        }

        // else fallback to Mobile Detect lib
        if (!isset($this->is_mobile) || !isset($this->is_tablet)) {
            $detect = new Mobile_Detect();

            if (!isset($this->is_tablet)) {
                $this->is_tablet = $detect->isTablet();
            }
            if (!isset($this->is_mobile)) {
                $this->is_mobile = $detect->isMobile() && !$this->is_tablet;
            }
        }

        // we consider all others as Desktop
        $this->is_desktop = !$this->is_mobile && !$this->is_tablet;
    }

    public function hasAcf(): bool
    {
        return class_exists('ACF');
    }


    // Boot

    public function bootstrap(string $path = null)
    {
        if (!$path) {
            $path = $this->rootThemePath() . '/config';
        }
        if (!file_exists($path) || !is_dir($path)) {
            throw new Exception('Check your config path!');
        }

        // Load all in order
        // Locale and Translation first, so the others already have the ability to translate strings

        foreach ([
            'languages' => 'setupLanguages',
            'translation' => 'setupTranslation',
            'app' => 'setupApp',
            'cache' => 'setupCache',
            'image' => 'setupImage',
            'wp' => 'setupWp',
            'services' => 'setupServices',
            'admin' => 'setupAdmin',
            'html' => 'setupHtml',
            'api' => 'setupApi',
        ] as $key => $method) {

            if (file_exists($path . '/' . $key . '.php')) {
                $this->add([
                    $key => require $path . '/' . $key . '.php',
                ]);
            }

            $this->{$method}();
        }

        // Calls the boot/bootAdmin method on all classes at the App folder
        $this->bootApp();
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
        Translate::setService($this->translation->service ?? '');

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

        // initialize View, registers WP hooks
        \view();

        // initialize Meta, registers WP hooks
        \meta();

        // Save post/terms hook
        App::addSavePostHook();
        App::addSaveTermHook();

        // if has ACF
        if (!function_exists('\acf_add_local_field_group')) {
            // Add Google Maps API Key
            if ($key = $this->get('services.google_maps.key')) {
                \add_filter('acf/fields/google_map/api', function ($api) use ($key) {
                    $api['key'] = $key;
                    return $api;
                });
            }
        }

        $actions = [
            'map_links' => [
                '',
                'mapLinks',
            ],
            'set_multilanguage_titles_and_slugs' => [
                '',
                'ensureTitlesAndSlugs',
                1,
            ],
        ];
        $this->callMethods(App::class, 'app', $actions);
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

        // Protects WP redirect on multilanguage front pages
        if (Languages::isMultilanguage()) {
            Wp::preventFrontPageRedirect();
        }

        $actions = [
            'force_https' => [
                '',
                'forceHttps',
            ],
        ];
        $this->callMethods(Wp::class, 'wp', $actions);
    }

    protected function setupServices()
    {
        // empty, here for subclasses override
    }

    protected function setupAdmin()
    {
        // actions that can run both on frontend and backend
        $actions = [
            'theming' => [
                '',
                'enableTheming',
            ],
            'hide_posts' => [
                '',
                'hidePosts',
            ],
        ];
        $this->callMethods(Admin::class, 'admin', $actions);

        // actions that can run only on backend
        if (\is_admin()) {
            $actions = [
                'hide_title' => [
                    '',
                    'hideTitle',
                    1,
                ],
            ];
            $this->callMethods(Admin::class, 'admin', $actions);
        }
    }

    protected function setupHtml()
    {
        $actions = [
            'rss' => [
                'disableRss',
                'enableRss',
            ],
            'emojis' => [
                'disableEmojis',
            ],
            'shortlink' => [
                'disableShortlink',
            ],
            'wp_embed' => [
                'disableWpEmbed',
            ],
            'block_library' => [
                'disableBlockLibrary',
            ],
            'body_classes' => [
                'resetBodyClasses',
            ],
            'jetpack_includes' => [
                'disableJetpackIncludes',
            ],
            'admin_bar' => [
                'disableAdminBar',
            ],
        ];
        $this->callMethods(Html::class, 'html', $actions);
    }

    protected function setupApi()
    {
        $actions = [
            'disable' => [
                '',
                'disable',
            ],
            'header' => [
                'disableHeader',
            ],
            'default_routes' => [
                'disableDefaultRoutes',
            ],
            'only_logged_in' => [
                '',
                'onlyLoggedIn'
            ],
        ];
        $this->callMethods(Api::class, 'api', $actions);
    }

    protected function bootApp()
    {
        $app_path = $this->rootThemePath() . '/app';
        if (!is_dir($app_path)) {
            return;
        }

        $dir = new RecursiveDirectoryIterator(
            $app_path,
            RecursiveDirectoryIterator::SKIP_DOTS
        );
        $iterator = new RecursiveIteratorIterator($dir);

        foreach ($iterator as $file) {

            if ($file->getExtension() !== 'php') {
                continue;
            }

            // determine class name
            $classname = substr($file->getPathName(), strlen($app_path));
            $classname = str_replace('.php', '', $classname);
            $classname = '\\App' . str_replace('/', '\\', $classname);

            if (!class_exists($classname)) {
                continue;
            }

            // call the register static method
            if (method_exists($classname, 'boot')) {
                call_user_func($classname . '::boot');
            }

            // admin too
            if (\is_admin() && method_exists($classname, 'bootAdmin')) {
                call_user_func($classname . '::bootAdmin');
            }
        }
    }

    protected function callMethods($class, string $config, array $actions)
    {
        foreach ($actions as $action => $method) {

            if (!isset($this->{$config}->{$action})) {
                continue;
            }

            $value = $this->{$config}->{$action};

            $fn = $value ? ($method[1] ?? null) : ($method[0] ?? null);
            if (!$fn) {
                continue;
            }

            // check if we need to pass params
            $single_param = false;
            $many_params = false;
            if (isset($method[2])) {
                if ($method[2] === 1) {
                    $single_param = true;
                } elseif ($method[2] === 2) {
                    $many_params = true;
                }
            }

            // call method
            if ($many_params) {
                call_user_func_array([$class, $fn], $value);
            } elseif ($single_param) {
                $class::{$fn}($value);
            } else {
                $class::{$fn}();
            }
        }
    }
}
