<?php

namespace Bond\Settings;

class Html
{

    public static function resetBodyClasses()
    {
        \add_filter('body_class', function ($classes) {
            $result = \view()->getOrder();

            // add lang
            $result[] = 'lang-' . Languages::shortCode();

            // devices
            if (app()->isMobile()) {
                $result[] = 'is-mobile';
            }
            if (app()->isTablet()) {
                $result[] = 'is-tablet';
            }
            if (app()->isDesktop()) {
                $result[] = 'is-desktop';
            }

            return $result;
        });
    }


    // add_theme_support( 'html5', array( 'comment-list', 'comment-form', 'search-form', 'gallery', 'caption' ) );


    public static function sanitizeParagraphs()
    {
        // filter the <p> tags from the images and iframes
        function filter_ptags_on_images($content)
        {
            $content = preg_replace('/<p>\s*(<a .*>)?\s*(<img .* \/>)\s*(<\/a>)?\s*<\/p>/iU', '\1\2\3', $content);
            return preg_replace('/<p>\s*(<iframe .*>*.<\/iframe>)\s*<\/p>/iU', '\1', $content);
        }
        \add_filter('the_content', 'filter_ptags_on_images', 12);
        \add_filter('acf_the_content', 'filter_ptags_on_images', 12);
    }


    public static function sanitizeCaptionShortcode()
    {
        \add_filter('img_caption_shortcode', function ($output, $attr, $content) {
            $output .= $content;

            if (!empty($attr['caption']))
                $output .= '<h6 class="image-caption">' . $attr['caption'] . '</h6>';

            return $output;
        }, 10, 3);
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
        \remove_filter('the_content', 'convert_smilies');

        \add_action('init', function () {
            \remove_action('wp_head', 'print_emoji_detection_script', 7);
            \remove_action('admin_print_scripts', 'print_emoji_detection_script');
            \remove_action('wp_print_styles', 'print_emoji_styles');
            \remove_action('admin_print_styles', 'print_emoji_styles');
            \remove_filter('the_content_feed', 'wp_staticize_emoji');
            \remove_filter('comment_text_rss', 'wp_staticize_emoji');
            \remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

            // Remove the tinymce emoji plugin.
            \add_filter('tiny_mce_plugins', function ($plugins) {
                if (is_array($plugins)) {
                    return array_diff($plugins, array('wpemoji'));
                }

                return array();
            });

            // Remove emoji CDN hostname from DNS prefetching hints.
            \add_filter('wp_resource_hints', function ($urls, $relation_type) {

                if ('dns-prefetch' == $relation_type) {

                    // Strip out any URLs referencing the WordPress.org emoji location
                    $emoji_svg_url_bit = 'https://s.w.org/images/core/emoji/';
                    foreach ($urls as $key => $url) {
                        if (strpos($url, $emoji_svg_url_bit) !== false) {
                            unset($urls[$key]);
                        }
                    }
                }

                return $urls;
            }, 10, 2);
        });
    }


    public static function disableRss()
    {
        \remove_action('do_feed_rdf', 'do_feed_rdf', 10, 1);
        \remove_action('do_feed_rss', 'do_feed_rss', 10, 1);
        \remove_action('do_feed_rss2', 'do_feed_rss2', 10, 1);
        \remove_action('do_feed_atom', 'do_feed_atom', 10, 1);
    }

    public static function enableRss()
    {
        self::disableRss();

        // output RSS to html
        \add_action('wp_head', function () {
            echo '<link rel="alternate" type="application/rss+xml" href="' . app()->url() . '/feed" title="' . app()->name() . ' RSS">' . "\n";
        });

        // load custom template
        add_action('do_feed_rss2', function () {
            view()->template('feed');
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
