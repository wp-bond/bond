<?php

namespace Bond\Tooling;

use Bond\Utils\Filesystem;

/**
 * Usage:
 *
 * with defaults:
 * echo vite();
 *
 * change settings as needed:
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
    protected string $base_url = '';
    protected string $base_path = '';
    protected bool $server_is_running;

    public function __construct()
    {
        $this->base_url = app()->themeDir();
        $this->base_path = app()->themePath();
    }

    public function __toString(): string
    {
        return $this->preloadAssets('woff2')
            . $this->jsTag()
            . $this->jsPreloadImports()
            . $this->cssTag();
    }

    public function inline(): string
    {
        return $this->inlineCss()
            . $this->inlineJs();
    }

    public function baseUrl(string $url): self
    {
        $this->base_url = $url;
        return $this;
    }

    public function basePath(string $path): self
    {
        $this->base_path = $path;
        return $this;
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

    public function jsUrl(): string
    {
        return $this->assetUrl($this->entry);
    }

    public function jsPath(): string
    {
        return $this->assetPath($this->entry);
    }

    public function cssUrls(): array
    {
        return $this->assetsUrls($this->entry, 'css');
    }

    public function cssPaths(): array
    {
        return $this->assetsPaths($this->entry, 'css');
    }

    public function assetUrl(string $entry): string
    {
        $path = $this->asset($entry);
        return $path
            ? $this->base_url . '/' . $this->out_dir . '/' . $path
            : '';
    }

    public function assetPath(string $entry): string
    {
        $path = $this->asset($entry);
        return $path
            ? $this->base_path . '/' . $this->out_dir . '/' . $path
            : '';
    }

    public function assetsUrls(string $entry, string $path = 'assets'): array
    {
        $paths = $this->assets($entry, $path);
        foreach ($paths as &$path) {
            $path = $this->base_url . '/' . $this->out_dir . '/' . $path;
        }
        return $paths;
    }

    public function assetsPaths(string $entry, string $path = 'assets'): array
    {
        $paths = $this->assets($entry, $path);
        foreach ($paths as &$path) {
            $path = $this->base_path . '/' . $this->out_dir . '/' . $path;
        }
        return $paths;
    }

    public function asset(string $entry): string
    {
        $manifest = $this->manifest();
        return !isset($manifest[$entry]) ? '' : $manifest[$entry]['file'];
    }

    public function assets(string $entry, string $path = 'assets'): array
    {
        $paths = [];
        $manifest = $this->manifest();

        if (!empty($manifest[$entry][$path])) {
            foreach ($manifest[$entry][$path] as $file) {
                $paths[] = $file;
            }
        }
        return $paths;
    }

    public function importsUrls(string $entry): array
    {
        $urls = [];
        $manifest = $this->manifest();

        if (!empty($manifest[$entry]['imports'])) {
            foreach ($manifest[$entry]['imports'] as $imports) {

                $urls[] = $this->base_url
                    . '/' . $this->out_dir
                    . '/' . $manifest[$imports]['file'];
            }
        }

        return $urls;
    }


    // Helper to output the script tag
    public function jsTag(): string
    {
        $url = $this->isRunning()
            ? $this->host() . '/' . $this->entry
            : $this->jsUrl();

        if (!$url) {
            return '';
        }

        return '<script type="module" crossorigin src="'
            . $url
            . '"></script>';
    }

    public function inlineJs(): string
    {
        $out = '';
        $path = $this->jsPath();
        if (file_exists($path)) {
            $content = file_get_contents($path);
            // don't output if empty
            // empty scripts may have a line break
            if ($content && $content !== "\n") {
                $out .= '<script>' . $content . '</script>';
            }
        }
        return $out;
    }

    public function jsPreloadImports(): string
    {
        if ($this->isRunning()) {
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
    public function cssTag(): string
    {
        // todo pass this decision up
        // not needed on dev, it's inject by Vite
        if ($this->isRunning()) {
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

    public function inlineCss(): string
    {
        $out = '';
        foreach ($this->cssPaths() as $path) {
            if (file_exists($path)) {
                $content = file_get_contents($path);
                if ($content) {
                    $out .= '<style>' . $content . '</style>';
                }
            }
        }
        return $out;
    }

    public function preloadAssets(string $type): string
    {
        if ($this->isRunning()) {
            return '';
        }

        $res = '';
        foreach ($this->assetsUrls($this->entry) as $url) {

            if (!str_ends_with($url, '.' . $type)) {
                continue;
            }
            if ($type === 'woff2') {
                $res .= '<link rel="preload" href="'
                    . $url
                    . '" as="font" type="font/woff2" crossorigin="anonymous">';
            }
        }
        return $res;
    }


    /** Checks wheter Vite server is running. Important, only checks on dev environment. */
    public function isRunning(): bool
    {
        return app()->isDevelopment() && $this->entryExists();
    }

    public function host(): string
    {
        return $this->hostname . ':' . $this->port;
    }

    public function manifest(): array
    {
        $content = Filesystem::get(
            $this->base_path . '/' . $this->out_dir  . '/manifest.json'
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
        if (isset($this->server_is_running)) {
            return $this->server_is_running;
        }
        $handle = curl_init($this->host() . '/' . $this->entry);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_NOBODY, true);

        curl_exec($handle);
        $error = curl_errno($handle);
        // dd(curl_getinfo($handle), $error, $this->host());
        curl_close($handle);

        return $this->server_is_running = !$error;
    }


    public function legacy(): string
    {
        if ($this->isRunning()) {
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
}
