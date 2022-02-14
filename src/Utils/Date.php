<?php

namespace Bond\Utils;

use Carbon\Carbon;
use DateInterval;
use DateTimeInterface;

class Date
{
    // TODO allow more timezone customization? right yes?


    public static function time($date = true): int
    {
        $date = static::carbon($date);
        return $date ? $date->timestamp : 0;
    }

    public static function year($date = true): int
    {
        return (int) static::iso($date, 'Y');
    }


    public static function wp(
        $date = true,
        string $from_timezone = null,
        string $to_timezone = null
    ): string {

        $date = static::carbon($date, $from_timezone);

        if ($date) {
            if ($from_timezone && !$to_timezone) {
                $to_timezone = config()->app->timezone;
            }
            if ($to_timezone) {
                $date->setTimezone($to_timezone);
            }
            return $date->isoFormat('Y-MM-DD HH:mm:ss');
        }
        return '';
    }




    public static function iso(
        $date = true,
        $format = 'Y-MM-DD HH:mm:ss'
    ): string {
        $date = static::carbon($date);
        return $date ? $date->isoFormat($format) : '';
    }


    public static function carbon(
        $date = true,
        string $timezone = null
    ): ?Carbon {
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
            $timezone ?: config()->app->timezone
        );
    }


    public static function header(
        $date = true,
        string $timezone = null
    ): string {
        $date = static::carbon($date, $timezone);
        return $date ? $date->toRfc7231String() : '';
    }


    public static function mongoDb(
        $date = true,
        string $timezone = null
    ): ?\MongoDB\BSON\UTCDateTime {

        if ($date instanceof \MongoDB\BSON\UTCDateTime) {
            return $date;
        }

        $date = static::carbon($date, $timezone);

        return $date ? new \MongoDB\BSON\UTCDateTime($date) : null;
    }

    // determine a ttl in seconds
    public static function ttl(int|string|DateInterval|DateTimeInterface $ttl): int
    {
        if (is_numeric($ttl)) {
            return (int) $ttl;
        }

        if (is_string($ttl)) {
            $ttl = static::carbon($ttl);
        }

        if ($ttl instanceof DateInterval) {
            $ttl = static::carbon()->add($ttl);
        }

        if ($ttl instanceof DateTimeInterface) {
            $ttl = static::carbon()->diffInRealSeconds($ttl, false);
        }

        $ttl = (int) $ttl;
        if ($ttl < 0) {
            $ttl = 0;
        }
        return $ttl;
    }
}
