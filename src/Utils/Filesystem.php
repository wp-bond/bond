<?php

namespace Bond\Utils;

use Exception;
use FilesystemIterator;

/**
 * Provides some helpers for filesystem handling. For use cases not covered here just use PHP functions directly.
 */
class Filesystem
{
    public static function get(string $path): string
    {
        // code from © Laravel LLC / Taylor Otwell, MIT licence.

        $contents = '';

        if (is_file($path)) {
            $handle = fopen($path, 'rb');

            if ($handle) {
                try {
                    if (flock($handle, LOCK_SH)) {
                        clearstatcache(true, $path);

                        $contents = fread($handle, filesize($path) ?: 1);

                        flock($handle, LOCK_UN);
                    }
                } finally {
                    fclose($handle);
                }
            }
        }
        return $contents;
    }

    public static function put(string $path, string $contents): bool
    {
        return file_put_contents($path, $contents, LOCK_EX) > 0;
    }

    /**
     * Write the contents of a file, replacing it atomically if it already exists.
     */
    public static function replace(string $path, string $content)
    {
        // code from © Laravel LLC / Taylor Otwell, MIT licence.

        // If the path already exists and is a symlink, get the real path...
        clearstatcache(true, $path);

        $path = realpath($path) ?: $path;

        $tempPath = tempnam(dirname($path), basename($path));

        // Fix permissions of tempPath because `tempnam()` creates it with permissions set to 0600...
        chmod($tempPath, 0777 - umask());

        file_put_contents($tempPath, $content);

        rename($tempPath, $path);
    }

    public static function delete(string $path): bool
    {
        if (is_dir($path)) {
            $items = new FilesystemIterator($path);

            foreach ($items as $item) {

                // skip hidden
                // if (strpos($item->getFilename(), '.') === 0) {
                //     continue;
                // }

                // recurse if found another directory
                // delete if found a file
                if ($item->isDir() && !$item->isLink()) {
                    static::delete($item->getPathname());
                    // @rmdir($item->getPathname());
                } else {
                    @unlink($item->getPathname());
                }
            }
            unset($items);

            // remove the directory itself
            // @rmdir($path);
            // TODO it is erroring out, shoudld be a race condition
            // see https://stackoverflow.com/questions/11513488/php-mkdir-not-working-after-rmdir

            // also should be an error with ::put LOCK_EX

        } else {
            @unlink($path);
        }

        return true;
    }
}
