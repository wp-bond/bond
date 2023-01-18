<?php

namespace Bond\Utils;

/**
 * Provides some helpers for remote files.
 */
class Http
{
    public static function getData(string $url): string|bool
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }

    public static function contentLength(string $url): ?int
    {
        return cache()->remember(
            'bond/http/content-length-' . md5($url),
            function () use ($url) {

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, true);
                curl_setopt($ch, CURLOPT_NOBODY, true);
                curl_exec($ch);
                $size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
                curl_close($ch);

                return $size ?: null;
            },
            -1
        );
    }
}
