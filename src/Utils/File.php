<?php

namespace Bond\Utils;

/**
 * Provides some helpers for WP Files.
 */
class File
{
    public static function url($file_id): string
    {
        $id = Cast::postId($file_id);
        return $id
            ? (string) \wp_get_attachment_url($id)
            : '';
    }

    public static function meta($id): ?array
    {
        $id = Cast::postId($id);
        if (!$id) {
            return null;
        }
        return \wp_get_attachment_metadata($id) ?: null;
    }

    public static function extension($file_id): string
    {
        $local_file = \get_attached_file($file_id, true);

        return $local_file
            ? strtolower(pathinfo($local_file, PATHINFO_EXTENSION))
            : '';
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
        bool $with_unit = true,
        int $decimals = 2
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
                $size = number_format($size / 1048576, $decimals);
                $unit = 'MB';
                break;

            case 'bytes':
            default:
                $unit = 'bytes';
                break;
        }

        return $size . ($with_unit ? ' ' . $unit : '');
    }
}
