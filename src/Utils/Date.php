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

        // MongoDB date
        if (is_a($date, 'MongoDB\BSON\UTCDateTime')) {
            $time = (int) ((string) $date);
            $time = round($time / 1000);
            return Carbon::createFromTimestampUTC($time);
        }
        if (!empty($date['milliseconds'])) {
            $time = (int) $date['milliseconds'];
            $time = round($time / 1000);
            return Carbon::createFromTimestampUTC($time);
        }

        return new Carbon(
            $date === true ? null : $date,
            config()->app->timezone
        );
    }
}
