<?php

namespace Bond\Utils;

class File
{
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
}
