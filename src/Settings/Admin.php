<?php

namespace Bond\Settings;

use Bond\Services\View;

class Admin
{
    private static $tax_hide_fields = [];


    public static function setEditorImageSizes(array $sizes)
    {
        // only needed on admin
        if (!app()->isAdmin()) {
            return;
        }

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
        // only needed on admin
        if (!app()->isAdmin()) {
            return;
        }

        if (app()->isProduction() || !\current_user_can('update_core')) {
            \add_action('admin_head', function () {
                \remove_action('admin_notices', 'update_nag', 3);
            }, 1);
        }
    }


    // TODO, allow to customize all these Vite assets via config

    public static function addEditorCss()
    {
        // only needed on admin
        if (!app()->isAdmin()) {
            return;
        }

        \add_action('admin_enqueue_scripts', function () {
            // global $current_screen;
            // echo $current_screen->id;exit;

            $urls = vite()
                ->entry('wp/editor.js')
                ->outDir('dist-wp-editor')
                ->cssUrls();

            if (count($urls)) {
                $url = str_replace(app()->themeDir(), '', $urls[0]);
                \add_editor_style($url);
            }
        });
    }

    public static function addLoginCss()
    {
        \add_action('login_head', function () {
            echo vite()
                ->port(3001)
                ->entry('wp/admin.js')
                ->outDir('dist-wp-admin');
        });

        \add_filter('login_headerurl', function () {
            return '';
        });
    }


    public static function addColorScheme()
    {
        // don't even add the hook if not on admin
        if (!app()->isAdmin()) {
            return;
        }

        \add_action('admin_head', function () {
            $vite = vite()
                ->basePath(__DIR__ . '/../../resources/assets')
                ->port(2342)
                ->entry('admin-color-scheme.js')
                ->outDir('dist/admin-color-scheme');

            // allows HMR if Vite is running
            echo $vite->isRunning() ? $vite : $vite->inline();
        });
    }


    public static function addAdminCss()
    {
        // don't even add the hook if not on admin
        if (!app()->isAdmin()) {
            return;
        }

        \add_action('admin_head', function () {
            echo vite()
                ->port(3001) // TODO we can allow config later
                ->entry('wp/admin.js')
                ->outDir('dist-wp-admin');
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
        if (app()->isAdmin()) {
            \add_action('wp_before_admin_bar_render', function () {
                global $wp_admin_bar;
                $wp_admin_bar->remove_menu('new-post');
            });
        }
        if (app()->isAdmin()) {
            \add_action('admin_menu', function () {
                \remove_menu_page('edit.php');
            }, 999);
        }
    }


    public static function setFooterCredits(string $text)
    {
        // only needed on admin
        if (!app()->isAdmin()) {
            return;
        }

        \add_filter('admin_footer_text', function () use ($text) {
            echo $text;
        });
    }

    public static function removeWpVersion()
    {
        // only needed on admin
        if (!app()->isAdmin()) {
            return;
        }

        \add_filter('update_footer', function () {
            return ' ';
        }, 11);
    }


    public static function setSideMenuParams(string $post_type, array $params)
    {
        \add_action('admin_menu', function () use ($post_type, $params) {
            global $submenu;
            $link = 'edit.php?post_type=' . $post_type;

            if (isset($submenu[$link][5])) {
                $submenu[$link][5][2] = $link . '&' . http_build_query($params);
            }
        });
    }


    public static function replaceDashboard()
    {
        // only needed on admin
        if (!app()->isAdmin()) {
            return;
        }

        \add_action('wp_loaded', function () {
            if (isset($_GET['dashboard-html'])) {

                $view = new View();
                $view->addLookupFolder(app()->viewsPath());
                $view->template('dashboard');
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
        if (empty($post_types) || !app()->isAdmin()) {
            return;
        }

        $post_types = (array) $post_types;

        \add_action('acf/input/admin_head', function () use ($post_types) {
            global $current_screen;
            // dd($current_screen);

            if (!in_array($current_screen->id, $post_types)) {
                return;
            }

            if ($current_screen->id === 'page') {
                global $post;
                if ((int)$post->ID === (int)get_option('page_on_front')) {
                    return;
                }
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





    public static function setTaxonomyFields(string $taxonomy, array $fields)
    {
        static $already = null;
        if (!$already) {
            $already = [];
            \add_action(
                'admin_footer-edit-tags.php',
                [static::class, 'handleTaxonomyFields']
            );
        }

        if (!in_array($taxonomy, $already)) {
            \add_action(
                $taxonomy . '_edit_form',
                [static::class, 'handleTaxonomyFields']
            );
            $already[] = $taxonomy;
        }

        self::$tax_hide_fields[$taxonomy] = array_merge(
            self::$tax_hide_fields[$taxonomy] ?? [],
            $fields
        );
    }

    public static function handleTaxonomyFields()
    {
        global $current_screen, $taxonomy;

        // dd($taxonomy, $current_screen->id);

        $fields = self::$tax_hide_fields[$taxonomy] ?? null;
        if (empty($fields)) {
            return;
        }

        // TODO later we can support changing the labels too

        echo '<style>';

        if (!empty($fields['name_label_after'])) {
            echo 'label[for="tag-name"]:after { content: "' . $fields['name_label_after'] . '" }';
        }
        if (!empty($fields['slug_label_after'])) {
            echo 'label[for="tag-slug"]:after { content: "' . $fields['slug_label_after'] . '" }';
        }

        if (
            isset($fields['name_instructions'])
            && $fields['name_instructions'] === false
        ) {
            echo '.term-name-wrap p { display: none; }';
        }

        if (
            isset($fields['slug_instructions'])
            && $fields['slug_instructions'] === false
        ) {
            echo '.term-slug-wrap p { display: none; }';
        }

        if (
            isset($fields['description'])
            && $fields['description'] === false
        ) {
            echo '.term-description-wrap { display: none; }';
        }

        if (
            isset($fields['parent'])
            && $fields['parent'] === false
        ) {
            echo '.term-parent-wrap { display: none; }';
        }

        echo '</style>';
    }
}
