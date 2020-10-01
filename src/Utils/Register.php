<?php

namespace Bond\Utils;

// https://developer.wordpress.org/reference/functions/register_post_type/#menu_position

class Register
{

    // copy&paste
    // Register::postType(self::$post_type, [
    //     'labels' => [
    //         'name' => t('Articles'),
    //         'singular_name' => t('Article'),
    //         'add_new' => t('Add article'),
    //         'add_new_item' => t('Add new article'),
    //         'edit_item' => t('Edit article'),
    //         'new_item' => t('New article'),
    //         'view_item' => t('View article'),
    //         'search_items' => t('Search article'),
    //         'not_found' => t('No article found'),
    //         'not_found_in_trash' => t('No article found in trash'),
    //     ],
    //     'menu_icon' => 'dashicons-admin-page',
    //     'menu_position' => 30,
    //     'taxonomies' => static::$taxonomies,
    // ]);

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

            $name = $params['name'];
            $singular_name = $params['singular_name'];
            $lower = Str::lower($singular_name);
            $x = 'register';

            $params['labels'] = [
                'name' => tx($name, $x),
                'singular_name' => tx($singular_name, $x),
                'add_new' => tx('Add', $x)
                    . ' ' . tx($lower, $x),
                'add_new_item' => tx('Add new', $x)
                    . ' ' . tx($lower, $x),
                'edit_item' => tx('Edit', $x)
                    . ' ' . tx($lower, $x),
                'new_item' => tx('New', $x)
                    . ' ' . tx($lower, $x),
                'view_item' => tx('View', $x)
                    . ' ' . tx($lower, $x),
                'search_items' => tx('Search', $x)
                    . ' ' . tx($lower, $x),
                'not_found' => tx('No ' . $lower, $x)
                    . ' ' . tx('found', $x),
                'not_found_in_trash' => tx('No ' . $lower, $x)
                    . ' ' . t('found in trash', $x),
            ];
        }

        \add_action('init', function () use ($post_type, $params) {
            \register_post_type($post_type, $params);
        });
    }


    // labels copy/paste
    // 'labels' => [
    //     'name' => t('Taxes'),
    //     'singular_name' => t('Tax'),
    //     'all_items' => t('All taxes'),
    //     'edit_item' => t('Edit tax'),
    //     'view_item' => t('View tax'),
    //     'update_item' => t('Update tax'),
    //     'add_new_item' => t('Add new tax'),
    //     'new_item_name' => t('New tax'),
    //     'parent_item' => t('Parent tax'),
    //     'parent_item_colon' => t('Parent tax:'),
    //     'search_items' => t('Search tax'),
    //     'add_or_remove_items' => t('Add or remove tax'),
    //     'choose_from_most_used' => t('Choose from the most used taxes'),
    //     'not_found' => t('No taxes found'),
    // ]
    //
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

        \add_action('init', function () use ($taxonomy, $params) {
            \register_taxonomy($taxonomy, null, $params);
        });
    }
}
