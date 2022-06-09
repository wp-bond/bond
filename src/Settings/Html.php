<?php

namespace Bond\Settings;

use Bond\Utils\Str;

class Html
{

    public static function resetBodyClasses()
    {
        \add_filter('body_class', function ($classes) {
            return [];
        });
    }

    // removes the <p> tags from the images and iframes
    public static function unwrapParagraphs()
    {
        \add_filter('the_content', [static::class, '_unwrapParagraphs'], 12);
        \add_filter('acf_the_content', [static::class, '_unwrapParagraphs'], 12);
    }
    public static function _unwrapParagraphs($content)
    {
        $content = preg_replace('/<p>\s*(<a .*>)?\s*(<img .* \/>)\s*(<\/a>)?\s*<\/p>/iU', '\1\2\3', $content);

        return preg_replace('/<p>\s*(<iframe .*>*.<\/iframe>)\s*<\/p>/iU', '\1', $content);
    }


    // use h6.image-caption on captions
    public static function h6Captions()
    {
        \add_filter('img_caption_shortcode', function ($output, $attr, $content) {
            $output .= $content;

            if (!empty($attr['caption']))
                $output .= '<h6 class="image-caption">' . $attr['caption'] . '</h6>';

            return $output;
        }, 10, 3);
    }



    public static function cleanupHead()
    {
        // WP version
        \remove_action('wp_head', 'wp_generator');

        // shortlinks
        \remove_action('wp_head', 'wp_shortlink_wp_head');

        // prev/next urls
        \remove_action('wp_head', 'adjacent_posts_rel_link_wp_head');

        // EditURI link
        \remove_action('wp_head', 'rsd_link');

        // windows live writer
        \remove_action('wp_head', 'wlwmanifest_link');

        // find more on /wp-includes/default-filters.php
    }


    // public static function removeGalleryShortcode()
    // {
    //     \remove_shortcode('gallery');
    //     \add_shortcode('gallery', function ($attr) {
    //         return '';
    //     });
    // }


    public static function disableEmojis()
    {
        \add_action('admin_init', function () {
            \remove_action('admin_print_scripts', 'print_emoji_detection_script');
            \remove_action('admin_print_styles', 'print_emoji_styles');
        });

        \add_action('init', function () {
            /*
			 * @credits
             * https://wordpress.org/plugins/disable-emojis/
             * https://wordpress.org/plugins/emoji-settings/
			 */
            \remove_action('wp_head', 'print_emoji_detection_script', 7);
            \remove_action('embed_head', 'print_emoji_detection_script');
            \remove_action('wp_print_styles', 'print_emoji_styles');
            \remove_filter('the_content_feed', 'wp_staticize_emoji');
            \remove_filter('comment_text_rss', 'wp_staticize_emoji');
            \remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

            // Remove the tinymce emoji plugin.
            \add_filter('tiny_mce_plugins', function ($plugins) {
                if (is_array($plugins)) {
                    return array_diff($plugins, ['wpemoji']);
                }
                return [];
            });

            // This removes the ascii to smiley convertion
            \remove_filter('the_content', 'convert_smilies');
            \remove_action('init', 'smilies_init', 5);

            // Remove DNS prefetch s.w.org (used for emojis, since WP 4.7)
            \add_filter('emoji_svg_url', '__return_false');

            // TODO Looks like this is not needed anymore, wait for confirmation
            // Remove emoji CDN hostname from DNS prefetching hints.
            // \add_filter('wp_resource_hints', function ($urls, $relation_type) {

            //     if ('dns-prefetch' == $relation_type) {

            //         // Strip out any URLs referencing the WordPress.org emoji location
            //         $emoji_svg_url_bit = 'https://s.w.org/images/core/emoji/';
            //         foreach ($urls as $key => $url) {
            //             if (strpos($url, $emoji_svg_url_bit) !== false) {
            //                 unset($urls[$key]);
            //             }
            //         }
            //     }

            //     return $urls;
            // }, 10, 2);
        });
    }

    public static function disableAdminBar()
    {
        // TODO, check, it's missing the CSS
        \add_filter('show_admin_bar', '__return_false');
    }


    public static function disableShortlink()
    {
        \add_filter('after_setup_theme', function () {
            // remove HTML meta tag
            // <link rel='shortlink' href='http://example.com/?p=25' />
            \remove_action('wp_head', 'wp_shortlink_wp_head', 10);

            // remove HTTP header
            // Link: <https://example.com/?p=25>; rel=shortlink
            \remove_action('template_redirect', 'wp_shortlink_header', 11);
        });
    }


    public static function disableWpEmbed()
    {
        \remove_action('wp_head', 'wp_oembed_add_discovery_links', 10);

        \add_action('wp', function () {
            \wp_deregister_script('wp-embed');
        });
    }

    public static function disableBlockLibrary()
    {
        \add_action('wp_enqueue_scripts', function () {
            \wp_dequeue_style('wp-block-library');
            \wp_dequeue_style('wp-block-library-theme');
            \wp_dequeue_style('global-styles'); // remove theme.json
        }, 100);
    }

    public static function disableJetpackIncludes()
    {
        \add_action('wp_enqueue_scripts', function () {
            \wp_dequeue_script('devicepx');
            \wp_deregister_style('dashicons');
        });
        \add_filter('jetpack_implode_frontend_css', '__return_false');
    }
}
