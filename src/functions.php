<?php

use Bond\App;
use Bond\Meta;
use Bond\Tooling\Vite;
use Bond\Utils\Str;
use Bond\View;

if (!function_exists('app')) {
    /**
     * Get the main App container.
     */
    function app(): App
    {
        return App::getInstance();
    }
}

if (!function_exists('config')) {
    function config(?string $key = null)
    {
        return $key
            ? app()->get('config')->{$key}
            : app()->get('config');
    }
}

if (!function_exists('view')) {
    function view(): View
    {
        return app()->get('view');
    }
}

if (!function_exists('meta')) {
    function meta(): Meta
    {
        return app()->get('meta');
    }
}

// TODO maybe rename to env, just check Laravel code to see what's up, check if phpdotenv creates a env function too, that would conflict
if (!function_exists('c')) {
    function c(string $name, $default = null)
    {
        return defined($name)
            ? constant($name)
            : $default;
    }
}

if (!function_exists('t')) {

    function t($string, string $language_code = null): string
    {
        return app()->get('translation')
            ->get($string, $language_code);
    }
}

if (!function_exists('tx')) {

    function tx($string, string $context, string $language_code = null): string
    {
        return app()->get('translation')
            ->get($string, $language_code, $context);
    }
}

if (!function_exists('esc_json')) {
    function esc_json($data): string
    {
        return Str::escJson($data);
    }
}

if (!function_exists('vite')) {
    function vite(): Vite
    {
        return new Vite();
    }
}
