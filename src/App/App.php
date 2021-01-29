<?php

namespace Bond\App;

use Bond\PostType;
use Bond\Taxonomy;
use Bond\Services\Meta;
use Bond\Services\Translation;
use Bond\Utils\Cache;
use Bond\Utils\Cast;
use Bond\Utils\Link;
use League\Container\Container;
use League\Container\ReflectionContainer;
use Mobile_Detect;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class App extends Container
{
    /**
     * The current App
     */
    protected static App $instance;

    protected string $theme_id;
    protected string $base_path;
    protected string $env;
    protected bool $is_mobile;
    protected bool $is_tablet;
    protected bool $is_desktop;

    public function __construct()
    {
        // initialize the Container itself
        parent::__construct();

        //
        mb_internal_encoding('UTF-8');

        // set local vars
        $this->env = c('APP_ENV') ?: 'production';

        // register base bindings
        $this->registerBaseBindings();
    }

    public static function getInstance(): self
    {
        return static::$instance ??= new static;
    }

    public static function setInstance(App $app)
    {
        static::$instance = $app;
    }

    protected function registerBaseBindings()
    {
        // register itself as the main App
        static::setInstance($this);
        $this->share('app', $this);

        // register default helpers
        $this->share('config', new Config($this));
        $this->share('view', View::class);
        $this->share('meta', Meta::class);
        $this->share('translation', Translation::class);

        // register aliases
        foreach ([
            App::class => 'app',
            Config::class => 'config',
            View::class => 'view',
            Meta::class => 'meta',
            Translation::class => 'translation',
        ] as $alias => $definition) {
            $this->share($alias, function () use ($definition) {
                return $this->get($definition);
            });
        }

        // Reflection fallback
        $this->delegate(new ReflectionContainer());
    }

    public function bootstrap(string $base_path = null)
    {
        if ($base_path) {
            $this->setBasePath($base_path);
        }

        $this->get('config')->load($this->configPath());


        if (\wp_using_themes()) {

            // auto initialize View, registers WP hooks
            $this->get('view');
        }

        // Save post/terms hook
        $this->addSavePostHook();
        $this->addSaveTermHook();
        $this->addSaveUserHook();

        // Map Links
        $this->mapLinks();

        // Calls the boot/bootAdmin method on all classes at the App folder
        $this->bootAppFolder();
    }

    protected function bootAppFolder()
    {
        $path = $this->appPath();
        if (!is_dir($path)) {
            return;
        }

        // TODO the files are already autoload into App namespace
        // maybe not use the folder based? instead use namespace based?

        $dir = new RecursiveDirectoryIterator(
            $path,
            RecursiveDirectoryIterator::SKIP_DOTS
        );
        $iterator = new RecursiveIteratorIterator($dir);

        foreach ($iterator as $file) {

            if ($file->getExtension() !== 'php') {
                continue;
            }

            // determine class name
            $classname = substr($file->getPathName(), strlen($path));
            $classname = str_replace('.php', '', $classname);
            $classname = '\\App' . str_replace('/', '\\', $classname);

            if (!class_exists($classname)) {
                continue;
            }

            // add to View and register
            if (is_subclass_of($classname, PostType::class)) {
                if (method_exists($classname, 'addToView')) {
                    call_user_func($classname . '::addToView');
                }
                if (method_exists($classname, 'register')) {
                    call_user_func($classname . '::register');
                }
            }
            if (is_subclass_of($classname, Taxonomy::class)) {
                if (method_exists($classname, 'register')) {
                    call_user_func($classname . '::register');
                }
            }

            // TODO maybe require a Bootable interface too

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

    public function id(): string
    {
        return $this->get('config')->app->id ??= $this->themeId();
    }

    public function name(): string
    {
        return $this->get('config')->app->name ?: '';
    }

    public function url(): string
    {
        return $this->get('config')->app->url ??= \untrailingslashit(c('WP_HOME') ?: \get_site_url());
    }

    public function isProduction(): bool
    {
        return $this->env === 'production';
    }

    public function isStaging(): bool
    {
        return $this->env === 'staging';
    }

    public function isDevelopment(): bool
    {
        return $this->env === 'development';
    }

    public function isMobile(): bool
    {
        if (!isset($this->is_mobile)) {
            $this->deviceDetect();
        }
        return $this->is_mobile;
    }

    public function isTablet(): bool
    {
        if (!isset($this->is_tablet)) {
            $this->deviceDetect();
        }
        return $this->is_tablet;
    }

    public function isDesktop(): bool
    {
        if (!isset($this->is_desktop)) {
            $this->deviceDetect();
        }
        return $this->is_desktop;
    }

    public function basePath(): string
    {
        return $this->base_path;
    }

    public function setBasePath(string $path): self
    {
        $this->base_path = rtrim($path, '\/');
        return $this;
    }

    // LATER, if appPath is not used, remove
    public function appPath(): string
    {
        return $this->basePath()
            . DIRECTORY_SEPARATOR . 'app';
    }

    public function configPath(): string
    {
        return $this->basePath()
            . DIRECTORY_SEPARATOR . 'config';
    }

    public function cachePath(): string
    {
        return $this->basePath()
            . DIRECTORY_SEPARATOR . '.cache';
    }

    public function languagesPath(): string
    {
        return $this->basePath()
            . DIRECTORY_SEPARATOR . 'languages';
    }

    public function viewsPath(): string
    {
        return $this->basePath()
            . DIRECTORY_SEPARATOR . 'views';
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
        return '/' . c('WP_CONTENT_FOLDER', 'wp-content')
            . '/themes'
            . '/' . $this->themeId();
    }

    public function themeUrl(): string
    {
        return $this->url() . $this->themeDir();
    }

    public function wpAdminUrl(): string
    {
        return defined('WP_SITEURL')
            ? \WP_SITEURL  . '/wp-admin'
            : \untrailingslashit(\get_admin_url());
    }

    public function hasAcf(): bool
    {
        return class_exists('ACF');
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




    // Link mapping

    public function mapLinks()
    {
        // posts
        $fn = function ($wp_link, $post) {
            return ($link = Link::post($post))
                ? Link::url($link)
                : $wp_link;
        };
        \add_filter('post_type_link', $fn, 10, 2);
        \add_filter('page_link', $fn, 10, 2);

        // posts archives
        \add_filter('post_type_archive_link', function ($wp_link, $post_type) {
            return ($link = Link::postType($post_type))
                ? Link::url($link)
                : $wp_link;
        }, 10, 2);

        // terms
        \add_filter('term_link', function ($wp_link, $term) {
            return ($link = Link::term($term))
                ? Link::url($link)
                : $wp_link;
        }, 10, 2);

        // there are no terms archive links in WP
        // no need to filter
    }




    // Save Posts Hook

    public function addSavePostHook()
    {
        \add_action('save_post', [$this, 'savePostHook'], 10, 2);
        \add_action('edit_attachment', [$this, 'savePostHook']);
    }

    public function removeSavePostHook()
    {
        \remove_action('save_post', [$this, 'savePostHook'], 10, 2);
        \remove_action('edit_attachment', [$this, 'savePostHook']);
    }

    public function savePostHook($post_id, $post = null)
    {
        if (\wp_is_post_revision($post_id)) {
            return;
        }

        // remove action to prevent infinite loop
        $this->removeSavePostHook();

        // turn off cache
        $original_state = config()->cache->enabled ?? false;
        config()->cache->enabled = false;

        // in case it's attachment, it misses the post object
        if (!$post) {
            $post = Cast::wpPost($post_id);
        }

        // clear cache
        Cache::forget($post->post_type);
        Cache::forget('bond/posts');
        Cache::forget('global');


        // Translate before
        \do_action('Bond/translate_post', $post_id);

        // Now emit actions
        if (\has_action('Bond/save_post')) {
            \do_action('Bond/save_post', Cast::post($post));
        }
        if (\has_action('Bond/save_post/' . $post->post_type)) {
            \do_action('Bond/save_post/' . $post->post_type, Cast::post($post));
        }

        // TODO maybe consider the delete_post hook
        // OR a more specific usage Bond/post_publish Bond/post_draft ?

        // TODO review these hooks naming, "save" it not exactly right
        // could be updated, for when it's updated
        // inserted or created, when created the first time
        // deleted, when deleted

        // turn on posts cache
        config()->cache->enabled = $original_state;

        // re-add action
        $this->addSavePostHook();
    }


    // Save Terms Hook

    public function addSaveTermHook()
    {
        \add_action('edited_term', [$this, 'saveTermHook'], 10, 3);
    }
    public function removeSaveTermHook()
    {
        \remove_action('edited_term', [$this, 'saveTermHook'], 10, 3);
    }

    public function saveTermHook($term_id, $tt_id, $taxonomy)
    {
        // remove action to prevent infinite loop
        $this->removeSaveTermHook();

        // turn off cache
        $original_state = config()->cache->enabled ?? false;
        config()->cache->enabled = false;

        // clear cache
        Cache::forget($taxonomy);
        Cache::forget('bond/terms');
        Cache::forget('global');

        // do action
        if (\has_action('Bond/save_term')) {
            \do_action('Bond/save_term', Cast::term($term_id));
        }
        if (\has_action('Bond/save_term/' . $taxonomy)) {
            \do_action('Bond/save_term/' . $taxonomy, Cast::term($term_id));
        }

        // TODO save_term doesn't get the delete right?

        // turn on posts cache
        config()->cache->enabled = $original_state;

        // re-add action
        $this->addSaveTermHook();
    }



    // Save Users Hook

    public function addSaveUserHook()
    {
        \add_action('profile_update', [$this, 'saveUserHook']);
        \add_action('user_register', [$this, 'saveUserHook']);
        \add_action('deleted_user', [$this, 'deletedUserHook']);
    }

    public function removeSaveUserHook()
    {
        \remove_action('profile_update', [$this, 'saveUserHook']);
        \remove_action('user_register', [$this, 'saveUserHook']);
        \remove_action('deleted_user', [$this, 'deletedUserHook']);
    }

    public function saveUserHook($user_id)
    {
        // remove action to prevent infinite loop
        $this->removeSaveUserHook();

        // turn off cache
        $original_state = config()->cache->enabled ?? false;
        config()->cache->enabled = false;

        // clear cache
        Cache::forget('global');

        // do action
        if (\has_action('Bond/save_user')) {
            \do_action('Bond/save_user', Cast::user($user_id));
        }
        $user = Cast::user($user_id);
        if (\has_action('Bond/save_user/' . $user->role)) {
            \do_action('Bond/save_user/' . $user->role, $user);
        }

        // turn on posts cache
        config()->cache->enabled = $original_state;

        // re-add action
        $this->addSaveUserHook();
    }

    public function deletedUserHook($user_id)
    {
        // clear cache
        Cache::forget('global');

        // do action
        \do_action('Bond/deleted_user', $user_id);

        // NOTE, this hook is correct to send only the ID, as it is already deleted from database
        // MAYBE offer the "delete" where it's emits just before deleting
    }
}
