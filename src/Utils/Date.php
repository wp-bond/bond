<?php

namespace Bond\Utils;

use Carbon\Carbon;

class Date
{
    // allow timezone customization?


    public static function time($date = true): int
    {
        $date = static::carbon($date);
        return $date ? $date->timestamp : 0;
    }

    public static function iso($date = true, $format = 'Y-MM-DD HH:mm:ss'): string
    {
        $date = static::carbon($date);
        return $date ? $date->isoFormat($format) : '';
    }

    public static function carbon($date = true): ?Carbon
    {
        if (empty($date)) {
            return null;
        }
        if ($date instanceof Carbon) {
            return $date;
        }
        return new Carbon(
            $date === true ? null : $date,
            config()->app->timezone
        );
    }
}
