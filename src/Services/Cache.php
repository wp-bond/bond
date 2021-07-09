<?php

namespace Bond\Services;

use Bond\Utils\Date;
use Bond\Utils\File;
use Closure;
use DateInterval;
use DateTimeInterface;
use ErrorException;
use Exception;
use Psr\SimpleCache\CacheInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

// TODO needs sanitization on cache path
// we support folder like, we must cleanup and slug them all

// TODO the forever cache, maybe just set to +1 year, like that

// TODO performance test on getting the cache with .php extension and without, to maybe discover if OPcache gets used, and file reads happens no more on filesystem

// TODO if is CLI, disable cache and file storage, the server stores and root user

class Cache extends CacheInterface
{
    protected string $directory;


    public function __construct(string $directory)
    {
        $this->directory = rtrim($directory, '/');

        // create directory if doesn't exist yet
        if (!file_exists($directory)) {
            mkdir($directory, 0775, true);
        }
    }

    public function remember(
        string $key,
        $ttl,
        Closure $callback
    ) {
        $path = $this->path($key, true);

        if ($this->expired($path, $ttl)) {

            // get source data
            $data = $callback();

            // store
            File::put($path, serialize($data));

            return $data;
        }

        // get cache data
        $data = File::get($path);

        return $data ? unserialize($data) : null;
    }


    public function get(string $key, $default = null)
    {
        try {
            return File::get($this->path($key));
        } catch (Exception $e) {
        }
        return $default;
    }

    public function set(string $key, $value, $ttl = null): bool
    {
    }

    public function delete(string $key): bool
    {
    }

    public function clear(): bool
    {
        $dir = new RecursiveDirectoryIterator(
            $this->directory,
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
        return true;
    }

    public function getMultiple(iterable $keys, $default = null): array
    {
    }

    public function setMultiple(iterable $values, $ttl = null): bool
    {
    }

    public function deleteMultiple(iterable $keys): bool
    {
    }

    public function has(string $key): bool
    {
        return is_file($this->path($key));
    }

    protected function path(string $key, bool $create_folder = false)
    {
        $key = trim($key, '/');

        // create folder if needed
        if ($create_folder && $last_slash = strrpos($key, '/')) {

            $dir = $this->directory . '/' . substr($key, 0, $last_slash);

            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        return $this->directory . '/' . $key;
    }


    protected function expired(string $path, $ttl): bool
    {
        // nothing was cached yet
        // or the path is a directory
        if (!is_file($path)) {
            return true;
        }

        // convert ttl to seconds
        $seconds = $this->seconds($ttl);

        // user requested to not be cached
        if ($seconds === 0) {
            // delete the cache file
            @unlink($path);
            return true;
        }

        // user requested to be cached forever
        if ($seconds < 0) {
            return false;
        }

        // check if expired by the file modified time
        if (filemtime($path) < time() - $seconds) {
            // delete the cache file
            @unlink($path);
            return true;
        }

        return false;
    }


    protected function seconds($ttl): int
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
}
