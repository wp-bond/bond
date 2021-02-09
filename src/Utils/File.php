<?php

namespace Bond\Utils;

/**
 * Provides some helpers for Files. For use cases not covered here just use PHP functions directly.
 *
 * Some logic here are adapted from Laravel's Illuminate\Filesystem\Filesystem class, credit goes to Laravel LLC / Taylor Otwell, MIT licence.
 */
class File
{
    public static function url($file_id): ?string
    {
        $id = Cast::postId($file_id);
        return $id
            ? (string) \wp_get_attachment_url($id)
            : null;
    }

    public static function size($file_id): int
    {
        $local_file = \get_attached_file($file_id, true);

        if (empty($local_file) || !file_exists($local_file)) {
            return 0;
        }
        return filesize($local_file);
    }

    public static function sizeFormat(
        $file_id,
        string $format = 'mb',
        bool $with_unit = true
    ): string {

        $size = self::size($file_id);
        if (!$size) {
            return '';
        }
        $unit = '';

        switch (strtolower($format)) {
            case 'mb':
            case 'mega':
            case 'megabytes':
                $size = number_format($size / 1048576, 2);
                $unit = 'MB';
                break;

            case 'bytes':
            default:
                $unit = 'bytes';
                break;
        }

        return $size . ($with_unit ? ' ' . $unit : '');
    }

    public static function get(string $path): string
    {
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

    public static function put($path, $contents): bool
    {
        return file_put_contents($path, $contents, LOCK_EX) > 0;
    }

    /**
     * Write the contents of a file, replacing it atomically if it already exists.
     */
    public static function replace(string $path, string $content)
    {
        // If the path already exists and is a symlink, get the real path...
        clearstatcache(true, $path);

        $path = realpath($path) ?: $path;

        $tempPath = tempnam(dirname($path), basename($path));

        // Fix permissions of tempPath because `tempnam()` creates it with permissions set to 0600...
        chmod($tempPath, 0777 - umask());

        file_put_contents($tempPath, $content);

        rename($tempPath, $path);
    }
}
