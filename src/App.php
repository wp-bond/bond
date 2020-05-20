<?php

namespace Bond;

use Bond\Utils\Translate;
use Exception;
use League\Container\Container;
use League\Container\ReflectionContainer;
use Mobile_Detect;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Psr\Container\ContainerInterface;

class App extends Container
{
    /**
     * The current App
     */
    protected static ?ContainerInterface $instance;

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
        $this->deviceDetect();

        // register base bindings
        $this->registerBaseBindings();
    }

    public static function getInstance()
    {
        return static::$instance ??= new static;
    }

    public static function setInstance(ContainerInterface $app = null)
    {
        return static::$instance = $app;
    }

    protected function registerBaseBindings()
    {
        // register itself as the main App
        static::setInstance($this);
        $this->add('app', $this, true);

        // register default helpers
        $this->add('config', Config::class, true);
        $this->add('view', View::class, true);
        $this->add('meta', Meta::class, true);
        // $this->add('translation', Translate::class, true);

        // register aliases
        foreach ([
            App::class => 'app',
            View::class => 'view',
            Meta::class => 'meta',
            // Translate::class => 'translation',
        ] as $alias => $definition) {
            $this->add($alias, function () use ($definition) {
                return $this->get($definition);
            }, true);
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
            // initialize View, registers WP hooks
            $this->get('view');

            // initialize Meta, registers WP hooks
            $this->get('meta');
        }

        // Calls the boot/bootAdmin method on all classes at the App folder
        $this->bootApp();
    }

    protected function bootApp()
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

    public function basePath(): string
    {
        return $this->base_path;
    }

    public function setBasePath(string $path): self
    {
        $this->base_path = rtrim($path, '\/');
        return $this;
    }

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
            . DIRECTORY_SEPARATOR . 'cache';
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
        return '/' . \WP_CONTENT_FOLDER
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
}
