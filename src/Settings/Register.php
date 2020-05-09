<?php

namespace Bond\Settings;

class Register
{


    public static function postType($post_type, array $params = [])
    {
        $base_args = [
            'public' => true,
            'menu_position' => null,
            'menu_icon' => 'dashicons-images-alt2',
            'supports' => [
                'title',
                // 'editor',
                // 'author',
                // 'thumbnail',
                // 'excerpt',
                // 'custom-fields',
                'revisions',
                // 'page-attributes',
            ],
            'taxonomies' => [],
            'has_archive' => true,
            // 'publicly_queryable' => true,
            'query_var' => true,
            'rewrite' => false,
        ];
        $params = array_merge($base_args, $params);

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
