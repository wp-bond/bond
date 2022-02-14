<?php

namespace Bond\Services\Cache;

use Bond\Utils\Date;
use Bond\Utils\Filesystem;
use DateInterval;
use DateTimeInterface;
use Exception;
use Psr\SimpleCache\InvalidArgumentException;

// TODO performance test on getting the cache with .php extension and without, to maybe discover if OPcache gets used, and file reads happens no more on filesystem


class FileCache extends AbstractCache
{
    protected string $path = '';

    public function __construct()
    {
        // set a default path
        $this->path(app()->basePath() . DIRECTORY_SEPARATOR . '.cache');

        parent::__construct();
    }

    /** Cache storage path */
    public function path(?string $path = null): string
    {
        if ($path !== null) {
            $this->path = rtrim($path, DIRECTORY_SEPARATOR);

            // create directory if doesn't exist yet
            if (!file_exists($this->path)) {
                mkdir($this->path, 0775, true);
            }
        }
        return $this->path;
    }

    public function remember(
        string $key,
        $value,
        null|int|string|DateInterval|DateTimeInterface $ttl = null
    ) {

        if (!$this->enabled) {
            return $this->value($value);
        }

        $path = $this->keyPath($key, true);

        if (is_null($ttl)) {
            $ttl = $this->ttl;
        }

        if ($this->expired($path, $ttl)) {

            $value = $this->value($value);

            // store
            Filesystem::put($path, serialize($value));

            return $value;
        }

        // get cache data
        $data = Filesystem::get($path);
        return $data ? unserialize($data) : null;
    }


    public function get($key, $default = null)
    {
        if ($this->enabled) {
            try {
                $data = Filesystem::get($this->keyPath((string) $key));
                if ($data) {
                    return unserialize($data);
                }
            } catch (Exception $e) {
            }
        }
        return $this->value($default);
    }

    public function set($key, $value, $ttl = null): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $path = $this->keyPath((string) $key, true);
        $value = $this->value($value);

        // store
        Filesystem::put($path, serialize($value));
        return true;

        // ttl is not used on file cache
        // other providers may
    }

    public function delete($key): bool
    {
        // delete the file or directory
        Filesystem::delete($this->path . DIRECTORY_SEPARATOR . $key);

        // now let's find all files and folders prefixed with that
        // TODO ???

        return true;
    }

    public function has($key): bool
    {
        return is_file($this->keyPath((string) $key));
    }

    public function clear(): bool
    {
        return Filesystem::delete($this->path);
    }



    public function getMultiple($keys, $default = null)
    {
        if (!is_iterable($keys)) {
            throw new InvalidArgumentException();
        }

        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }
        return $values;
    }

    public function setMultiple($values, $ttl = null)
    {
        if (
            !is_iterable($values)
            || (is_array($values) && array_is_list($values))
        ) {
            throw new InvalidArgumentException();
        }

        $result = true;
        foreach ($values as $key => $value) {
            $res = $this->set($key, $value, $ttl);
            if (!$res) {
                $result = false;
            }
        }
        return $result;
    }

    public function deleteMultiple($keys)
    {
        if (!is_iterable($keys)) {
            throw new InvalidArgumentException();
        }

        $result = true;
        foreach ($keys as $key) {
            $res = $this->delete($key);
            if (!$res) {
                $result = false;
            }
        }
        return $result;
    }


    protected function keyPath(string $key, bool $create_folder = false): string
    {
        $key = trim($key, DIRECTORY_SEPARATOR);
        if (!$key) {
            return $this->path;
        }

        // TODO needs sanitization on cache path
        // we support folder like, we must cleanup and slug them all
        // we could explode on separators, and slug all parts

        // create folder if needed
        // TODO test if needed, or if the Filesystem::put can create the needed folder by itself, may be the best!
        if ($create_folder && $last_slash = strrpos($key, DIRECTORY_SEPARATOR)) {

            $dir = $this->path . DIRECTORY_SEPARATOR . substr($key, 0, $last_slash);

            if (!is_dir($dir)) {
                @unlink($dir);
                mkdir($dir, 0755, true);
            }
        }

        $path = $this->path . DIRECTORY_SEPARATOR . $key;

        // if path is a directory, append and extra character
        if (is_dir($path)) {
            $path .= '_';
        }

        return $path;
    }

    protected function expired(
        string $path,
        int|string|DateInterval|DateTimeInterface $ttl
    ): bool {
        // clear file status cache
        clearstatcache(true, $path);

        // nothing was cached yet
        // or the path is a directory
        if (!is_file($path)) {
            return true;
        }

        // convert ttl to seconds
        $seconds = Date::ttl($ttl);

        // user requested to not be cached
        if ($seconds === 0) {
            return true;
        }

        // user requested to be cached forever
        if ($seconds < 0) {
            return false;
        }

        // check if expired by the file modified time
        if (filemtime($path) < time() - $seconds) {
            return true;
        }

        return false;
    }
}
