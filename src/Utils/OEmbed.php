<?php

namespace Bond\Utils;

class OEmbed
{
    private static array $tempArgs = [];

    public static function html($url, array $args = null): string
    {
        $data = static::data($url, $args);
        return $data ? $data['html'] : '';
    }

    /**
     * Get the provider response data
     */
    public static function data($url, array $args = null): ?array
    {
        if (empty($url)) {
            return null;
        }

        return cache()->remember(
            'bond/oembed/' . md5($url . Str::kebab($args)),
            function () use ($url, $args) {
                // seens to not need anymore
                // require_once ABSPATH . WPINC . '/class-wp-oembed.php';

                add_filter('oembed_fetch_url', [self::class, 'addArgs']);
                if (!empty($args)) {
                    self::$tempArgs = $args;
                }

                $res = \_wp_oembed_get_object()->get_data($url, $args);

                self::$tempArgs = [];

                return $res ? get_object_vars($res) : null;
            }
        );
    }

    public static function addArgs(string $provider): string
    {
        return empty(self::$tempArgs)
            ? $provider
            : add_query_arg(self::$tempArgs, $provider);
    }

    public static function downloadImage(string $url, int $post_id = 0): int
    {
        if (!$url) {
            return 0;
        }

        // get oembed data
        $oembed = self::data($url, ['width' => 99999]);

        // check
        if (empty($oembed['thumbnail_url'])) {
            return 0;
        }

        // get largest image
        if (
            !empty($oembed['provider_name'])
            && $oembed['provider_name'] === 'YouTube'
        ) {
            // TODO should use largestThumb only? and upgrade that
            $thumb_url = self::largestYoutubeThumb($oembed['thumbnail_url']);
        } else {
            $thumb_url = $oembed['thumbnail_url'];
        }

        // download image
        $thumb_id = \media_sideload_image(
            $thumb_url,
            $post_id,
            (!empty($oembed['title']) ? $oembed['title'] : null),
            'id'
        );

        return (int) $thumb_id;
    }




    // input should be a oembed url, like https://www.youtube.com/watch?v=ID_HERE or http://vimeo.com/ID_HERE

    public static function largestThumb($url): string
    {
        // for youtube we can get the image by forming the url with the video id
        if (strpos($url, 'youtu') !== false) {
            if ($video_id = self::youtubeVideoId($url)) {
                $image_url = 'http://i.ytimg.com/vi/' . $video_id . '/maxresdefault.jpg';

                if (self::remoteFileExists($image_url)) {
                    return $image_url;
                }
            }
        }

        // for all others, let's get oembed data
        $oembed_data = self::data($url, ['width' => 99999]);

        if (!empty($oembed_data['thumbnail_url'])) {
            return $oembed_data['thumbnail_url'];
        }

        return '';
    }

    // helper to convert a youtube thumb url to a higher res version
    // input should be already a youtube thumb url, like http://i.ytimg.com/vi/ID_HERE/default.jpg

    public static function largestYoutubeThumb($youtube_thumb_url)
    {
        $youtube_thumb_url = substr($youtube_thumb_url, 0, strrpos($youtube_thumb_url, '/'));

        $url = $youtube_thumb_url . '/maxresdefault.jpg';
        if (self::remoteFileExists($url)) {
            return $url;
        }

        $url = $youtube_thumb_url . '/sddefault.jpg';
        if (self::remoteFileExists($url)) {
            return $url;
        }

        $url = $youtube_thumb_url . '/hqdefault.jpg';
        if (self::remoteFileExists($url)) {
            return $url;
        }

        $url = $youtube_thumb_url . '/mqdefault.jpg';
        if (self::remoteFileExists($url)) {
            return $url;
        }

        return $youtube_thumb_url . '/default.jpg';
    }

    public static function youtubeVideoId($youtube_url)
    {
        $video_id = false;

        if (preg_match('/youtube\.com\/watch\?v=([^\&\?\/]+)/', $youtube_url, $matches)) {
            $video_id = $matches[1];
        } elseif (preg_match('/youtube\.com\/embed\/([^\&\?\/]+)/', $youtube_url, $matches)) {
            $video_id = $matches[1];
        } elseif (preg_match('/youtube\.com\/v\/([^\&\?\/]+)/', $youtube_url, $matches)) {
            $video_id = $matches[1];
        } elseif (preg_match('/youtu\.be\/([^\&\?\/]+)/', $youtube_url, $matches)) {
            $video_id = $matches[1];
        }

        return $video_id;
    }




    // quick way to check if a remote file exists
    // uses cURL with a HEAD request

    private static function remoteFileExists($url, $timeout = 3)
    {
        $exist = false;

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_NOBODY => true,
            CURLOPT_FOLLOWLOCATION => true,
            // CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => ceil($timeout / 2),
            CURLOPT_DNS_CACHE_TIMEOUT => 1200, //20 minutes
        ];

        //init
        $ch = curl_init();
        curl_setopt_array($ch, $options);

        // execute curl
        if (curl_exec($ch)) {
            $status_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($status_code === 200) //could be changed to allow 3xx?
            {
                $exist = true;
            }
        }
        curl_close($ch);

        return $exist;
    }
}
