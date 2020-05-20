<?php

namespace Bond\Utils;

use Closure;
use DateInterval;
use DateTimeInterface;
use DirectoryIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class Cache
{


    public static function php(
        string $key,
        $ttl,
        Closure $callback
    ) {
        $path = self::getPath($key, 'php');

        if (self::fileHasExpired($path, $ttl)) {

            // get source data
            $data = $callback();

            // store
            file_put_contents($path, serialize($data));

            return $data;
        }

        // get cache data
        if (is_readable($path)) {
            return unserialize(file_get_contents($path));
        }

        return null;
    }


    public static function json(
        string $key,
        $ttl,
        Closure $callback
    ) {
        $path = self::getPath($key, 'json');

        if (self::fileHasExpired($path, $ttl)) {

            // get source data
            $data = $callback();

            // store
            file_put_contents($path, json_encode($data));

            return $data;
        }

        // get cache data
        if (is_readable($path)) {
            return json_decode(file_get_contents($path), true);
        }

        return null;
    }




    private static function getPath($key, $extension): string
    {
        $file_path = trim($key, '/') . '.' . $extension;
        $cache_path = app()->cachePath();

        // create folder if needed
        $dir = '';
        if ($last_slash = strrpos($file_path, '/')) {
            $dir = substr($file_path, 0, $last_slash);
        }
        if (!file_exists($cache_path . '/' . $dir)) {
            mkdir($cache_path . '/' . $dir, 0755, true);
        }

        return $cache_path . '/' . $file_path;
    }


    private static function fileHasExpired($path, $ttl): bool
    {
        // nothing was cached yet
        if (!file_exists($path)) {
            return true;
        }

        // user request
        if (!empty($_GET) && isset($_GET['nocache'])) {
            return true;
        }

        // ttl
        $seconds = self::seconds($ttl);

        if ($seconds === 0) {
            return true;
        }
        if ($seconds < 0) {
            return false;
        }

        // get the file modified time
        if (filemtime($path) < time() - $seconds) {
            return true;
        }

        return false;
    }


    private static function seconds($ttl): int
    {
        if (is_null($ttl)) {
            return 0;
        }
        if (is_numeric($ttl)) {
            return (int) $ttl;
        }

        if ($ttl instanceof DateInterval) {
            $ttl = Date::carbon()->add($ttl);
        }

        if ($ttl instanceof DateTimeInterface) {
            $ttl = Date::carbon()->diffInRealSeconds($ttl, false);
        }

        $ttl = (int) $ttl;

        return $ttl > 0 ? $ttl : 0;
    }



    public static function forget($key)
    {
        if (empty($key)) {
            return;
        }
        $key = trim($key, '/');
        $path = app()->cachePath() . '/' . $key;
        $name = null;

        if (!file_exists($path) || !is_dir($path)) {

            // go down 1 level, storing the file name
            if ($last_slash = strrpos($path, '/')) {
                $name = substr($path, $last_slash + 1);
                $path = substr($path, 0, $last_slash);
            }

            // if doesn't exist return
            if (!file_exists($path) || !is_dir($path)) {
                return;
            }
        }

        // if requested a file, unlink only the file
        if ($name) {
            $dir = new DirectoryIterator($path);
            foreach ($dir as $file) {
                if ($file->isFile()) {

                    $file_name = $file->getBasename('.' . $file->getExtension());

                    if ($name === $file_name) {
                        unlink($file->getPathname());
                    }
                }
            }
            return;
        }

        // otherwise delete everything
        $dir = new RecursiveDirectoryIterator(
            $path,
            RecursiveDirectoryIterator::SKIP_DOTS
        );
        $iterator = new RecursiveIteratorIterator(
            $dir,
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
    }
}
