<?php

namespace Bond\Settings;

use Bond\Utils\Cast;

class Admin
{
    private static $archive_columns = [];


    public static function enableTheming()
    {
        static::addLoginCss();

        if (\is_admin()) {
            static::addAdminCss();
            static::addEditorCss();
            static::disableAdminColorPicker();
            static::removeUpdateNag();
            static::addFooterCredits();
            static::manageArchiveColumns();
            static::replaceDashboard();
            if (config()->isProduction() || !\current_user_can('manage_options')) {
                static::removeAdministrationMenus();
            }
        }
    }


    public static function setEditorImageSizes(array $sizes)
    {
        // required for media upload ui
        if (!isset($sizes['thumbnail'])) {
            $sizes['thumbnail'] = 'Thumbnail';
        }

        \add_filter('image_size_names_choose', function () use ($sizes) {
            return $sizes;
        });
    }



    public static function removeUpdateNag()
    {
        if (config()->isProduction() || !\current_user_can('update_core')) {
            \add_action('admin_head', function () {
                \remove_action('admin_notices', 'update_nag', 3);
            }, 1);
        }
    }

    public static function addEditorCss()
    {
        \add_action('admin_enqueue_scripts', function () {
            // global $current_screen;
            // echo $current_screen->id;exit;

            // add_editor_style(trim(mix('/css/editor.css', false), '/')); // WP does not work yet with URL parameters

            \add_editor_style('css/editor.css');
        });
    }

    public static function addLoginCss()
    {
        \add_action('login_head', function () {
            echo '<link rel="stylesheet" href="' . mix('css/admin.css') . '">';
        });

        \add_filter('login_headerurl', function () {
            return '';
        });
    }

    public static function addAdminCss()
    {
        \add_action('admin_head', function () {
            echo '<link rel="stylesheet" href="' . mix('css/admin.css') . '">';
        });
    }

    public static function disableAdminColorPicker()
    {
        \remove_action('admin_color_scheme_picker', 'admin_color_scheme_picker');
    }



    public static function cleanupAdminBar()
    {
        \add_action('wp_before_admin_bar_render', function () {
            global $wp_admin_bar;
            // print_r($wp_admin_bar);exit;

            // $wp_admin_bar->remove_menu('wp-logo');
            $wp_admin_bar->remove_menu('about');
            $wp_admin_bar->remove_menu('wporg');
            $wp_admin_bar->remove_menu('documentation');
            $wp_admin_bar->remove_menu('support-forums');
            $wp_admin_bar->remove_menu('feedback');
            // $wp_admin_bar->remove_menu('view-site');

            // $wp_admin_bar->remove_menu('site-name');
            $wp_admin_bar->remove_menu('dashboard');
            $wp_admin_bar->remove_menu('appearance');
            $wp_admin_bar->remove_menu('themes');
            $wp_admin_bar->remove_menu('customize');

            // $wp_admin_bar->remove_menu('new-content');
            $wp_admin_bar->remove_menu('new-post');
            $wp_admin_bar->remove_menu('new-page');
            $wp_admin_bar->remove_menu('new-media');
            $wp_admin_bar->remove_menu('new-user');

            // $wp_admin_bar->remove_menu('edit');
            $wp_admin_bar->remove_menu('comments');
            $wp_admin_bar->remove_menu('search');
        });
    }


    public static function hidePosts()
    {
        if (\is_admin() || config('html.admin_bar') !== false) {
            \add_action('wp_before_admin_bar_render', function () {
                global $wp_admin_bar;
                $wp_admin_bar->remove_menu('new-post');
            });
        }
        if (\is_admin()) {
            \add_action('admin_menu', function () {
                \remove_menu_page('edit.php');
            }, 999);
        }
    }


    public static function addFooterCredits()
    {
        \add_filter('admin_footer_text', function () {
            echo config('admin.footer_text');
        });

        // remove WP version
        \add_filter('update_footer', function () {
            return ' ';
        }, 11);
    }

    public static function manageArchiveColumns()
    {
        \add_filter(
            'manage_pages_custom_column',
            [static::class, 'handleArchiveColumn'],
            10,
            2
        );
        \add_filter(
            'manage_posts_custom_column',
            [static::class, 'handleArchiveColumn'],
            10,
            2
        );
        \add_filter(
            'manage_media_custom_column',
            [static::class, 'handleArchiveColumn'],
            10,
            2
        );
        // add terms here too with another handlers that cast as term
    }

    public static function addArchiveColumn($name, callable $handler)
    {
        self::$archive_columns[$name] = $handler;
    }

    public static function handleArchiveColumn($name, $post_id)
    {
        $post = Cast::post($post_id);
        if (!$post) {
            return;
        }

        if (isset(self::$archive_columns[$name])) {
            echo self::$archive_columns[$name]($post);
        }
    }



    public static function replaceDashboard()
    {
        \add_action('wp_loaded', function () {
            if (isset($_GET['dashboard-html'])) {
                view()->template('dashboard');
                exit;
            }
        });

        \add_action('admin_head', function () {
            global $current_screen;
            if ($current_screen->id === 'dashboard') :
?>
                <script>
                    jQuery('html').css('visibility', 'hidden');

                    jQuery.get('<?= admin_url() ?>index.php?dashboard-html', function(data) {
                        jQuery(document).ready(function($) {
                            $('.wrap').last().after(data).remove();
                            $('html').css('visibility', 'visible');
                        });
                    });
                </script>
            <?php
            endif;
        });

        \add_action('wp_dashboard_setup', function () {
            \remove_action('welcome_panel', 'wp_welcome_panel');
            \remove_meta_box('dashboard_activity', 'dashboard', 'normal');
            \remove_meta_box('dashboard_right_now', 'dashboard', 'normal');
            \remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
            \remove_meta_box('dashboard_incoming_links', 'dashboard', 'normal');
            \remove_meta_box('dashboard_plugins', 'dashboard', 'normal');

            \remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
            \remove_meta_box('dashboard_recent_drafts', 'dashboard', 'side');
            \remove_meta_box('dashboard_primary', 'dashboard', 'side');
            \remove_meta_box('dashboard_secondary', 'dashboard', 'side');
        });
    }


    public static function removeAdministrationMenus()
    {
        \add_action('admin_menu', function () {

            \remove_menu_page('themes.php');
            \remove_menu_page('plugins.php');
            \remove_menu_page('tools.php');
            \remove_menu_page('options-general.php');

            // ACF
            \remove_menu_page('edit.php?post_type=acf-field-group');
        }, 999);
    }




    public static function hideTitle($post_types)
    {
        if (empty($post_types)) {
            return;
        }

        \add_action('acf/input/admin_head', function () use ($post_types) {
            global $current_screen;
            // dd($current_screen->id);

            if (is_array($post_types) && !in_array($current_screen->id, $post_types)) {
                return;
            }

            ?>
            <style>
                #post-body-content #titlediv {
                    display: none;
                }

                #post-body-content {
                    margin-top: -20px;
                }
            </style>
<?php

        });
    }
}
