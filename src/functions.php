<?php

use Bond\App\App;
use Bond\App\View;
use Bond\Services\Meta;
use Bond\Tooling\Vite;
use Bond\Utils\Str;

if (!function_exists('app')) {
    /**
     * Get the main App container.
     */
    function app(): App
    {
        return App::getInstance();
    }
}

// TODO maybe remove these top-level helpers?

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

    function t(
        $input,
        string $language_code = null,
        string $written_language = null
    ) {
        return app()->translation()
            ->get($input, $language_code, $written_language);
    }
}

if (!function_exists('tx')) {

    function tx(
        $input,
        string $context,
        string $language_code = null,
        string $written_language = null
    ) {
        return app()->translation()
            ->get($input, $language_code, $written_language, $context);
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
