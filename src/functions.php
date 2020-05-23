<?php

use Bond\App;
use Bond\Meta;
use Bond\Utils\Translate;
use Bond\Utils\Str;
use Bond\View;

if (!function_exists('app')) {
    /**
     * Get the instance from the container, or the container itself.
     *
     * @param  string|null  $id Identifier of the entry to look for.
     * @return mixed|\Bond\App
     */
    function app($id = null)
    {
        return is_null($id)
            ? App::getInstance()
            : App::getInstance()->get($id);
    }
}

if (!function_exists('config')) {
    function config(?string $key = null)
    {
        return $key
            ? app('config')->{$key}
            : app('config');
    }
}

if (!function_exists('view')) {
    function view(): View
    {
        return app('view');
    }
}

if (!function_exists('meta')) {
    function meta(): Meta
    {
        return app('meta');
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
        return Translate::get($string, $language_code);
    }
}

if (!function_exists('tx')) {

    function tx($string, string $context, string $language_code = null): string
    {
        return Translate::get($string, $language_code, $context);
    }
}

if (!function_exists('esc_json')) {
    function esc_json($data): string
    {
        return Str::escJson($data);
    }
}


if (!function_exists('mix')) {

    /**
     * Get the path to a versioned Mix file.
     * SHOULD BE UPDATED LATER WHEN THERE IS CONFIG CONTAINER
     *
     * @param  string  $path
     * @return string
     *
     * @throws \Exception
     */
    function mix($path, $theme_url = true)
    {
        static $manifest;

        $path = '/' . ltrim($path, '/');

        if (file_exists(app()->themePath() . '/hot')) {
            return "//localhost:8080{$path}";
        }

        if (!$manifest) {
            if (!file_exists($manifestPath = app()->themePath() . '/mix-manifest.json')) {
                throw new Exception('The Mix manifest does not exist.');
            }

            $manifest = json_decode(file_get_contents($manifestPath), true);
        }

        if (!array_key_exists($path, $manifest)) {
            throw new Exception(
                "Unable to locate Mix file: {$path}. Please check your " .
                    'webpack.mix.js output paths and try again.'
            );
        }

        if ($theme_url) {
            return app()->themeDir() . $manifest[$path];
        }
        return $manifest[$path];
    }
}

if (!function_exists('mix_inline')) {
    function mix_inline($path)
    {
        $path = mix($path, false);
        $path = parse_url($path, PHP_URL_PATH);
        return file_get_contents(app()->themePath() . $path);
    }
}
