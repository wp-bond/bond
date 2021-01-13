<?php

namespace Bond\Tooling;

use Bond\Utils\File;

/**
 * Usage:
 * echo vite();
 *
 * echo vite()
 *     ->port(3001)
 *     ->entry('admin.js')
 *     ->outDir('dist-wp-admin');
 *
 */
class Vite
{
    protected string $hostname = 'http://localhost';
    protected int $port = 3000;
    protected string $entry = 'main.js';
    protected string $assets_dir = 'assets';
    protected string $out_dir = 'dist';

    public function __construct()
    {
        // if (!isset($this->hostname)) {
        //     $this->hostname = app()->url();
        // }

        // made no diffence yet, Vite still didn't create more than one server in the same port with diffent hostnames

        // if that is the case in the future, we may change here
        // so each project has its own vite client, and the port never changes
    }

    public function hostname(string $hostname): self
    {
        $this->hostname = $hostname;
        return $this;
    }

    public function port(int $port): self
    {
        $this->port = $port;
        return $this;
    }

    public function entry(string $entry): self
    {
        $this->entry = $entry;
        return $this;
    }

    public function assetsDir(string $dir): self
    {
        $this->assets_dir = $dir;
        return $this;
    }

    public function outDir(string $dir): self
    {
        $this->out_dir = $dir;
        return $this;
    }

    public function __toString(): string
    {
        return $this->client()
            . $this->jsTag()
            . $this->cssTag();
    }

    public function jsUrl(bool $relative = false): string
    {
        return $this->assetUrl($this->entry, $relative);
    }

    public function cssUrl(bool $relative = false): string
    {
        return $this->assetUrl(
            str_replace('.js', '.css', $this->entry),
            $relative
        );
    }

    public function assetUrl(string $filename, bool $relative = false)
    {
        //we only need the filename
        $filename = pathinfo($filename, PATHINFO_BASENAME);

        // locate hashed files in production
        $manifest = $this->manifest();

        return ($relative ? '' : app()->themeDir())
            . '/' . $this->out_dir
            . '/' . ($manifest[$filename]['file']);
    }

    // Helper to output the script tag
    protected function jsTag(): string
    {
        $url = $this->isDev()
            ? $this->host() . '/' . $this->entry
            : $this->jsUrl();

        return '<script type="module" src="'
            . $url
            . '"></script>';
    }

    // Helper to output style tag
    protected function cssTag(): string
    {
        // not needed on dev, it's inject by Vite
        if ($this->isDev()) {
            return '';
        }
        return '<link rel="stylesheet" href="'
            . $this->cssUrl()
            . '">';
    }

    public function legacy(): string
    {
        // TODO LATER
        // index-legacy is not at the manifest file yet!

        if ($this->isDev()) {
            return '';
        }
        return '';

        $script = '<script nomdoule>!function(){var e=document,t=e.createElement("script");if(!("noModule"in t)&&"onbeforeload"in t){var n=!1;e.addEventListener("beforeload",(function(e){if(e.target===t)n=!0;else if(!e.target.hasAttribute("nomodule")||!n)return;e.preventDefault()}),!0),t.type="module",t.src=".",e.head.appendChild(t),t.remove()}}();</script>';

        $script .= '<script nomodule src="' . $this->assetUrl('polyfills-legacy.js') . '"></script>';

        $script .= '<script nomodule id="vite-legacy-entry" data-src="' . $this->assetUrl('index-legacy.js') . '">System.import(document.getElementById(\'vite-legacy-entry\').getAttribute(\'data-src\'))</script>';

        return $script;
    }

    protected function isDev(): bool
    {
        return app()->isDevelopment() && $this->hostExists();
    }

    protected function host(): string
    {
        return $this->hostname . ':' . $this->port;
    }

    // Vite client to be loaded during development
    protected function client(): string
    {
        if ($this->isDev()) {
            return '<script type="module">import "' . $this->host() . '/@vite/client"</script>';
        }
        return '';
    }

    protected function manifest(): array
    {
        $content = File::get(app()->themePath()
            . '/' . $this->out_dir
            . '/manifest.json');

        return $content
            ? json_decode($content, true)
            : [];
    }

    // This method is very useful for the local server
    // if we try to access it, and by any means, didn't started Vite yet
    // it will fallback to load the production files from manifest
    // so you still navigate your site as you intended
    protected function hostExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }

        $handle = curl_init($this->host() . '/@vite/client');
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_NOBODY, true);

        curl_exec($handle);
        $error = curl_errno($handle);
        // dd(curl_getinfo($handle), $error, $this->host());
        curl_close($handle);

        return $exists = !$error;
    }
}
