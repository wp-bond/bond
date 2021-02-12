<?php

namespace Bond\Utils;

// https://developer.wordpress.org/reference/functions/register_post_type/#menu_position

class Register
{

    public static function postType($post_type, array $params = [])
    {
        $base_args = [
            'public' => true,
            'menu_position' => null,
            'menu_icon' => '',
            'supports' => [
                'title',
                // 'editor',
                // 'author',
                // 'thumbnail',
                // 'excerpt',
                // 'custom-fields',
                'revisions',
                // 'page-attributes',
                // 'post-formats',
            ],
            'taxonomies' => [],
            'has_archive' => true,
            // 'publicly_queryable' => true,
            'query_var' => true,
            'rewrite' => false,
        ];
        $params = array_merge($base_args, $params);

        // auto labels
        if (!isset($params['labels']) && isset($params['name']) && isset($params['singular_name'])) {

            $x = 'register';

            $name = tx($params['name'], $x);
            $singular_name = tx($params['singular_name'], $x);

            // translate to english to append to labels
            $n = Str::lower(tx($params['singular_name'], $x, 'en'));

            $params['labels'] = [
                'name' => $name,
                'singular_name' => $singular_name,
                'add_new' => tx('Add ' . $n, $x, null, 'en'),
                'add_new_item' => tx('Add new ' . $n, $x, null, 'en'),
                'edit_item' => tx('Edit ' . $n, $x, null, 'en'),
                'new_item' => tx('New ' . $n, $x, null, 'en'),
                'view_item' => tx('View ' . $n, $x, null, 'en'),
                'search_items' => tx('Search ' . $n, $x, null, 'en'),
                'not_found' => tx('No ' . $n . ' found', $x, null, 'en'),
                'not_found_in_trash' => tx('No ' . $n . ' found in trash', $x, null, 'en'),
            ];
        }

        \add_action('init', function () use ($post_type, $params) {
            \register_post_type($post_type, $params);
        });
    }



    // Tip: post_tag and category can be overriden, just register again
    public static function taxonomy($taxonomy, array $params = [])
    {
        $base_args = [
            'public' => true,
            'hierarchical' => true,
            'show_admin_column' => true,
            'show_tagcloud' => false,
            'show_in_nav_menus' => false,
            'show_ui' => true,

            'rewrite' => false,
            'query_var' => true,
            'meta_box_cb' => false,
        ];
        $params = array_merge($base_args, $params);

        // auto labels
        if (!isset($params['labels']) && isset($params['name']) && isset($params['singular_name'])) {

            $x = 'register';

            $name = tx($params['name'], $x);
            $singular_name = tx($params['singular_name'], $x);

            // translate to english to append to labels
            $n = Str::lower(tx($params['name'], $x, 'en'));
            $ns = Str::lower(tx($params['singular_name'], $x, 'en'));

            $params['labels'] = [
                'name' => $name,
                'singular_name' => $singular_name,
                'all_items' => tx('All ' . $n, $x, null, 'en'),
                'add_new_item' => tx('Add new ' . $ns, $x, null, 'en'),
                'new_item_name' => tx('New ' . $ns, $x, null, 'en'),
                'edit_item' => tx('Edit ' . $ns, $x, null, 'en'),
                'new_item' => tx('New ' . $ns, $x, null, 'en'),
                'view_item' => tx('View ' . $ns, $x, null, 'en'),
                'update_item' => tx('Update ' . $ns, $x, null, 'en'),
                'search_items' => tx('Search ' . $n, $x, null, 'en'),
                'not_found' => tx('No ' . $ns . ' found', $x, null, 'en'),
                'not_found_in_trash' => tx('No ' . $ns . ' found in trash', $x, null, 'en'),
                'parent_item' => tx('Parent ' . $ns, $x, null, 'en'),
                'parent_item_colon' => tx('Parent ' . $ns, $x) . ':',
                'add_or_remove_items' => tx('Add or remove ' . $n, $x, null, 'en'),
                'choose_from_most_used' => tx('Choose from the most used ' . $n, $x, null, 'en'),
            ];
        }

        \add_action('init', function () use ($taxonomy, $params) {
            \register_taxonomy($taxonomy, null, $params);
        });
    }
}
