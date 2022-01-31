<?php

use Bond\App\App;
use Bond\Services\Cache\CacheInterface;
use Bond\Services\Meta;
use Bond\Services\View;
use Bond\Tooling\Vite;

if (!function_exists('app')) {
    /**
     * Get the current App container.
     */
    function app(): App
    {
        return App::current();
    }
}

if (!function_exists('config')) {
    function config(?string $key = null)
    {
        return $key
            ? app()->config()->{$key}
            : app()->config();
    }
}

if (!function_exists('view')) {
    function view(): View
    {
        return app()->view();
    }
}

if (!function_exists('meta')) {
    function meta(): Meta
    {
        return app()->meta();
    }
}

if (!function_exists('cache')) {
    function cache(): CacheInterface
    {
        return app()->cache();
    }
}

if (!function_exists('c')) {
    function c(string $name, $default = null)
    {
        return defined($name)
            ? constant($name)
            : $default;
    }
}

if (!function_exists('t')) {
    function t(
        $input,
        string $language = null,
        string $written_language = null
    ) {
        return app()->translation()
            ->get($input, $language, $written_language);
    }
}

if (!function_exists('tx')) {
    function tx(
        $input,
        string $context,
        string $language = null,
        string $written_language = null
    ) {
        return app()->translation()
            ->get($input, $language, $written_language, $context);
    }
}

if (!function_exists('vite')) {
    function vite(): Vite
    {
        return new Vite();
    }
}
