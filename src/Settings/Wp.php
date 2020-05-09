<?php

namespace Bond\Settings;

use Bond\Utils\Query;
use Bond\Utils\Str;

// Ideas for later:
// Wp::disableComments, for now using plugin disable-comments

class Wp
{
    public static function isTheme()
    {
        return !defined('WP_USE_THEMES') || \WP_USE_THEMES;
    }

    public static function isAdminWithTheme()
    {
        return static::isTheme() && \is_admin();
    }


    // Images

    public static function sanitizeFilenames()
    {
        \add_filter('sanitize_file_name', [Str::class, 'filename']);
    }

    public static function setImageQuality(int $val)
    {
        // default is 82
        \add_filter('jpeg_quality', function () use ($val) {
            return $val;
        });
    }

    public static function addImageSizes(array $sizes)
    {
        // TODO add option to allow upscale
        // is important on some layouts to not break

        $option_sizes = [];

        foreach ($sizes as $size => $values) {

            // define constants for easier handling
            $constant_name = Str::constant($size);
            if (!defined($constant_name)) {
                define($constant_name, $size);
            }

            // these are set in the WP options, see below
            if (in_array($size, [
                'thumbnail',
                'medium',
                'medium_large',
                'large',
            ])) {
                $option_sizes[$size] = $values;
                continue;
            }

            // add custom sizes
            call_user_func_array('\add_image_size', array_merge((array) $size, $values));
        }

        // sizes that goes into WP settings
        if (count($option_sizes)) {
            \add_action('after_setup_theme', function () use ($option_sizes) {
                foreach ($option_sizes as $size => $values) {
                    \update_option($size . '_size_w', $values[0]);
                    \update_option($size . '_size_h', $values[1]);

                    if ($size === 'thumbnail') {
                        \update_option($size . '_crop', !empty($values[2]) ? $values[2] : false);
                    }
                }
            });
        }
    }





    // WP Settings

    // Protects WP redirect on multilanguage front pages
    public static function preventFrontPageRedirect()
    {
        \add_filter('redirect_canonical', function ($redirect_url, $requested_url) {
            return \is_front_page() ? $requested_url : $redirect_url;
        }, 10, 2);
    }

    public static function forceHttps()
    {
        \add_filter('script_loader_src', [Str::class, 'https'], 999999);
        \add_filter('style_loader_src', [Str::class, 'https'], 999999);
        \add_filter('site_url', [Str::class, 'https'], 999999);
        \add_filter('plugins_url', [Str::class, 'https'], 999999);
        \add_filter('home_url', [Str::class, 'https'], 999999);
        \add_filter('admin_url', [Str::class, 'https'], 999999);
        \add_filter('includes_url', [Str::class, 'https'], 999999);
        \add_filter('content_url', [Str::class, 'https'], 999999);
        \add_filter('set_url_scheme', [Str::class, 'https'], 999999);
        \add_filter('rest_url', [Str::class, 'https'], 999999);
    }

    public static function updateSettings()
    {
        if (!static::isAdminWithTheme()) {
            return;
        }

        \add_action('after_setup_theme', function () {
            // http://codex.wordpress.org/Option_Reference

            //general
            \update_option('blogname', config('app.name'));
            \update_option('blogdescription', '');
            \update_option('admin_email', config('app.developer_email'));

            \update_option('date_format', 'd/m/Y');
            \update_option('time_format', 'G:i');
            \update_option('timezone_string', config('app.timezone'));
            \update_option('start_of_week', 1); //monday

            \update_option('users_can_register', false);
            \update_option('show_avatars', false);

            // link structure
            // with trailing
            // \update_option('permalink_structure', '/%postname%/');

            // without trailing slash
            \update_option('permalink_structure', '/%postname%');

            // reading
            \update_option('show_on_front', 'page'); // page/posts
            \update_option('page_on_front', Query::id('home', 'page'));
            // \update_option('page_on_front', false);
            \update_option('page_for_posts', false);
            \update_option('posts_per_page', 12);
            \update_option('posts_per_rss', 12);


            // writing

            // misc
            \update_option('use_smilies', false);
            \update_option('image_default_link_type', 'none'); //default 'file'  maybe ''

            // discussion
            \update_option('default_ping_status', 'closed');
            \update_option('default_pingback_flag', false);
            \update_option('default_comment_status', 'closed');
            \update_option('comments_notify', true);
            \update_option('comment_moderation', true);
            \update_option('require_name_email', true);

            // media
            \update_option('uploads_use_yearmonth_folders', true);
            // good to balance the ammount of files per folder
            // some bad shared hosting get slow when browsing a folder with more than 1024 files

            \update_option('embed_autourls', true);
            // update_option('embed_size_w', 464); // 720 x 480 relative (464 x 312)
            // update_option('embed_size_h', 312); //


            // Note:
            // Large size images can be deactivate if needed, but not the Thumb, nor the Medium size!
            // They are loaded in the media and upload screens so if they don't exist they will fallback to larger ones,
            // and if all the right conditions are in place, your browser will crash.
            // (took me several hours to debug that, WP was loading several 10Mb images at the media upload screen)

            // Clean up widget settings that weren't set at installation
            // http://wordpress.stackexchange.com/questions/81785/remove-unnecessary-mysql-query
            \add_option('widget_pages', ['_multiwidget' => 1]);
            \add_option('widget_calendar', ['_multiwidget' => 1]);
            \add_option('widget_tag_cloud', ['_multiwidget' => 1]);
            \add_option('widget_nav_menu', ['_multiwidget' => 1]);
        });
    }
}
