<?php

namespace Bond\Tooling;

use Bond\Utils\File;

/**
 * Usage:
 * echo vite(); // with defaults (main.js)
 *
 * echo vite()
 *     ->entry('admin.js')
 *     ->port(3001)
 *     ->outDir('dist-wp-admin');
 *
 */
class Vite
{
    protected string $hostname = 'http://localhost';
    protected int $port = 3000;
    protected string $entry = 'main.js';
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

    public function entry(string $entry): self
    {
        $this->entry = $entry;
        return $this;
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

    public function outDir(string $dir): self
    {
        $this->out_dir = $dir;
        return $this;
    }

    public function __toString(): string
    {
        return $this->jsTag()
            . $this->jsPreloadImports()
            . $this->cssTag();
    }

    public function jsUrl(): string
    {
        return $this->assetUrl($this->entry);
    }

    public function cssUrls(): array
    {
        $urls = [];
        $manifest = $this->manifest();

        if (!empty($manifest[$this->entry]['css'])) {
            foreach ($manifest[$this->entry]['css'] as $file) {
                $urls[] = app()->themeDir()
                    . '/' . $this->out_dir
                    . '/' . $file;
            }
        }
        return $urls;
    }

    public function assetUrl(string $entry): string
    {
        $manifest = $this->manifest();

        if (!isset($manifest[$entry])) {
            return '';
        }

        return app()->themeDir()
            . '/' . $this->out_dir
            . '/' . ($manifest[$entry]['file']);
    }

    public function importsUrls(string $entry): array
    {
        $urls = [];
        $manifest = $this->manifest();

        if (!empty($manifest[$entry]['imports'])) {
            foreach ($manifest[$entry]['imports'] as $imports) {

                $urls[] = app()->themeDir()
                    . '/' . $this->out_dir
                    . '/' . $manifest[$imports]['file'];
            }
        }

        return $urls;
    }

    // Helper to output the script tag
    protected function jsTag(): string
    {
        $url = $this->isDev()
            ? $this->host() . '/' . $this->entry
            : $this->jsUrl();

        if (!$url) {
            return '';
        }

        return '<script type="module" crossorigin src="'
            . $url
            . '"></script>';
    }

    protected function jsPreloadImports(): string
    {
        if ($this->isDev()) {
            return '';
        }

        $res = '';
        foreach ($this->importsUrls($this->entry) as $url) {
            $res .= '<link rel="modulepreload" href="'
                . $url
                . '">';
        }
        return $res;
    }

    // Helper to output style tag
    protected function cssTag(): string
    {
        // not needed on dev, it's inject by Vite
        if ($this->isDev()) {
            return '';
        }

        $tags = '';
        foreach ($this->cssUrls() as $url) {
            $tags .= '<link rel="stylesheet" href="'
                . $url
                . '">';
        }
        return $tags;
    }

    public function legacy(): string
    {
        if ($this->isDev()) {
            return '';
        }

        $url = $this->assetUrl(str_replace(
            '.js',
            '-legacy.js',
            $this->entry
        ));

        $polyfill_url = $this->assetUrl('vite/legacy-polyfills');
        if (!$polyfill_url) {
            $polyfill_url = $this->assetUrl('../vite/legacy-polyfills');
        }

        if (!$url || !$polyfill_url) {
            return '';
        }

        $script = '<script nomodule>!function(){var e=document,t=e.createElement("script");if(!("noModule"in t)&&"onbeforeload"in t){var n=!1;e.addEventListener("beforeload",(function(e){if(e.target===t)n=!0;else if(!e.target.hasAttribute("nomodule")||!n)return;e.preventDefault()}),!0),t.type="module",t.src=".",e.head.appendChild(t),t.remove()}}();</script>';

        $script .= '<script nomodule src="' . $polyfill_url . '"></script>';

        $script .= '<script nomodule id="vite-legacy-entry" data-src="' . $url . '">System.import(document.getElementById(\'vite-legacy-entry\').getAttribute(\'data-src\'))</script>';

        return $script;
    }

    protected function isDev(): bool
    {
        return app()->isDevelopment() && $this->entryExists();
    }

    protected function host(): string
    {
        return $this->hostname . ':' . $this->port;
    }

    protected function manifest(): array
    {
        $content = File::get(
            app()->themePath()
                . '/' . $this->out_dir
                . '/manifest.json'
        );

        return $content
            ? json_decode($content, true)
            : [];
    }

    // This method is very useful for the local server
    // if we try to access it, and by any means, didn't started Vite yet
    // it will fallback to load the production files from manifest
    // so you still navigate your site as you intended
    protected function entryExists(): bool
    {
        static $exists = null;
        if ($exists !== null) {
            return $exists;
        }
        $handle = curl_init($this->host() . '/' . $this->entry);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_NOBODY, true);

        curl_exec($handle);
        $error = curl_errno($handle);
        // dd(curl_getinfo($handle), $error, $this->host());
        curl_close($handle);

        return $exists = !$error;
    }
}
