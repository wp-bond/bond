<?php

namespace Bond\Utils;

use Bond\Settings\Languages;
use Bond\Post;
use Bond\Posts;
use Bond\Terms;
use Carbon\Carbon;
use WP_Post;
use WP_Query;
use WP_Term;
use WP_Term_Query;

class Query
{
    // Query post by id not needed, use Cast::post
    // Query term by id not needed, use Cast::term

    // TODO postOfPostType()
    // termByMeta()
    // termbySlug


    public static function postBySlug(
        string $slug,
        string $post_type = 'any',
        string $language_code = null,
        array $args = []
    ): ?Post {
        return Cast::post(static::wpPostBySlug(
            $slug,
            $post_type,
            $language_code,
            $args
        ));
    }

    public static function wpPostBySlug(
        string $slug,
        string $post_type = 'any',
        string $language_code = null,
        array $args = []
    ): ?WP_Post {

        if (Languages::isMultilanguage()) {
            $query_args = [
                'post_type' => $post_type,
                'posts_per_page' => 1,
                'post_status' => 'publish',
                'no_found_rows' => true,
                'suppress_filters' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'meta_query' => [
                    [
                        'key' => 'slug' . Languages::fieldsSuffix($language_code),
                        'value' => $slug,
                        'compare' => '==',
                    ],
                ],
            ];
            $query_args = array_merge($query_args, $args);
            $query = new WP_Query($query_args);

            if (!empty($query->posts)) {
                return $query->posts[0];
            }
        }

        return static::wpPostByName($slug, $post_type, $args);
    }


    public static function wpPostByName(
        string $name,
        string $post_type = 'any',
        array $args = []
    ): ?WP_Post {

        $query_args = [
            'post_type' => $post_type,
            'posts_per_page' => 1,
            'post_status' => 'publish',
            'name' => $name,
            'no_found_rows' => true,
            'suppress_filters' => true,
            // great explanation about the below cache options:
            // https://wordpress.stackexchange.com/a/215881/186332
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];
        $query_args = array_merge($query_args, $args);

        $query = new WP_Query($query_args);

        return !empty($query->posts) ? $query->posts[0] : null;
    }




    public static function count(
        $post_type,
        array $args = []
    ): int {

        // TODO maybe change to SQL query

        $query_args = [
            'post_type' => (array) $post_type,
            'posts_per_page' => 1,
            'post_status' => 'publish',
            'suppress_filters' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];
        $query_args = array_merge($query_args, $args);

        $query = new WP_Query($query_args);

        return (int) $query->found_posts;
    }




    public static function slug($post_id): string
    {
        $post = Cast::wpPost($post_id);
        return $post ? $post->post_name : '';
    }

    /**
     * Gets a post id directly from database.
     */
    public static function id(string $slug, string $post_type): int
    {
        global $wpdb;

        return (int) $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_name = '$slug' AND post_type = '$post_type'");
    }

    /**
     *
     *
     * @param string|array $post_types
     * @param string $order_by
     * @param string $order
     * @param int $limit
     * @return array of int
     */
    public static function ids(
        $post_types,
        $order_by = 'post_date',
        $order = 'DESC',
        $limit = 0
    ): array {
        global $wpdb;
        $limit = (int) $limit;

        $post_types = "'" . implode("', '", (array) $post_types) . "'";

        return array_map('intval', $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE post_type IN ({$post_types}) AND post_status = 'publish' ORDER BY $order_by $order" . ($limit ? " LIMIT $limit" : '')));
    }

    /**
     * @param string $page_path
     * @param string $post_type
     * @return int
     */
    public static function idByPath($page_path, $post_type = 'page'): int
    {
        $page = \get_page_by_path(basename(\untrailingslashit($page_path)), 'OBJECT', $post_type);

        if ($page) {
            return (int) $page->ID;
        }

        return 0;
    }

    /**
     * @param string|array $post_types
     * @return int
     */
    public static function mostRecentPostId($post_types): int
    {
        global $wpdb;

        $post_types = "'" . implode("', '", (array) $post_types) . "'";

        return (int) $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_type IN ({$post_types}) AND post_status = 'publish' ORDER BY post_date DESC LIMIT 1");
    }

    /**
     * @param string|array $post_types
     * @return string
     */
    public static function mostRecentPostName($post_types): string
    {
        global $wpdb;

        $post_types = "'" . implode("', '", (array) $post_types) . "'";

        return $wpdb->get_var("SELECT post_name FROM $wpdb->posts WHERE post_type IN ({$post_types}) AND post_status = 'publish' ORDER BY post_date DESC LIMIT 1");
    }





    /**
     * @param int $post_id
     * @return int
     */
    public static function parentId($post_id): int
    {
        global $wpdb;

        $post_id = Cast::postId($post_id);

        return (int) $wpdb->get_var("SELECT post_parent FROM $wpdb->posts WHERE ID = '$post_id'");
    }



    /**
     * Striped out version of WP get_lastpostmodified to query non-public post types.
     *
     * @param string|array $post_types
     * @return string
     */
    public static function lastModified($post_types): Carbon
    {
        global $wpdb;

        $post_types = "'" . implode("', '", (array) $post_types) . "'";
        $add_seconds_server = date('Z');

        $date = $wpdb->get_var("SELECT DATE_ADD(post_modified_gmt, INTERVAL '$add_seconds_server' SECOND) FROM $wpdb->posts WHERE post_status = 'publish' AND post_type IN ({$post_types}) ORDER BY post_modified_gmt DESC LIMIT 1");

        return new Carbon($date, 'GMT');
    }

    /**
     * Striped out version of WP get_lastpostmodified to query non-public post types.
     *
     * @param string|array $post_types
     * @return int
     */
    public static function lastModifiedTime($post_types): int
    {
        return self::lastModified($post_types)->timestamp;
    }



    public static function pageChildren($page_id, array $args = []): ?Posts
    {
        if (empty($page_id)) {
            return null;
        }

        $query_args = [
            'post_parent__in' => (array) $page_id,
            'post_type' => 'page',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'order' => 'ASC',
            'orderby' => 'menu_order title',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];
        $query_args = array_merge($query_args, $args);

        $query = new WP_Query($query_args);

        return Cast::posts($query->posts);
    }

    public static function firstPageChild($page_id, array $args = []): ?Post
    {
        if (empty($page_id)) {
            return null;
        }

        $query_args = [
            'post_parent__in' => (array) $page_id,
            'post_type' => 'page',
            'posts_per_page' => 1,
            'post_status' => 'publish',
            'order' => 'ASC',
            'orderby' => 'menu_order title',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];
        $query_args = array_merge($query_args, $args);

        $query = new WP_Query($query_args);

        return !empty($query->posts) ? Cast::post($query->posts[0]) : null;
    }


    public static function pageTemplateName($post_id): string
    {
        $post_id = Cast::postId($post_id);
        $name = \get_post_meta($post_id, '_wp_page_template', true);
        $name = preg_replace('/\.php$/', '', $name);

        return $name !== 'default' ? $name : '';
    }







    /**
     * @param int $post_id
     * @return int
     */
    public static function attachedImage($post_id): int
    {
        $ids = self::attachedImages($post_id, 1);
        return !empty($ids) ? $ids[0] : 0;
    }

    /**
     *
     * Note: if you will be using the ids to load the attachment posts consider using WP's get_attached_media because it will load the posts, and save on wp_cache
     *
     * @param int $post_id
     * @param int $limit
     * @return array of int
     */
    public static function attachedImages($post_id, $limit = 0): array
    {
        global $wpdb;
        $limit = (int) $limit;

        $result = $wpdb->get_results("SELECT ID FROM $wpdb->posts WHERE post_parent = '$post_id' AND post_type = 'attachment' AND post_status = 'inherit' AND post_mime_type LIKE 'image/%' ORDER BY menu_order ASC, ID DESC" . ($limit ? " LIMIT $limit" : ''));

        $ids = [];
        foreach ($result as $value) {
            $ids[] = (int) $value->ID;
        }
        return $ids;
    }





    // Taxonomy

    public static function wpTerm(
        $id
    ): ?WP_Term {

        $id = Cast::termId($id);
        if (!$id) {
            return null;
        }

        $term = \get_term($id);
        return $term instanceof WP_Term ? $term : null;
    }


    // TODO upgrade for multilanguage
    public static function wpTermBySlug(
        string $slug,
        string $taxonomy
    ): ?WP_Term {

        if (empty($slug)) {
            return null;
        }

        $term = \get_term_by('slug', $slug, $taxonomy);
        return $term instanceof WP_Term ? $term : null;
    }


    // public static function wpTerm(
    //     $id,
    //     array $args = []
    // ): ?WP_Term {

    //     $id = Cast::termId($id);
    //     if (!$id) {
    //         return null;
    //     }

    //     $query = new WP_Term_Query(array_merge([
    //         'term_taxonomy_id' => (int) $id,
    //         'number' => 1,
    //         'hide_empty' => false,
    //         'update_term_meta_cache' => false,
    //     ], $args));

    //     return !empty($query->terms) ? $query->terms[0] : null;
    // }


    // public static function wpTermBySlug(
    //     string $slug,
    //     string $taxonomy,
    //     array $args = []
    // ): ?WP_Term {

    //     if (empty($slug)) {
    //         return null;
    //     }

    //     $query = new WP_Term_Query(array_merge([
    //         'taxonomy' => $taxonomy,
    //         'slug' => $slug,
    //         'number' => 1,
    //         'hide_empty' => false,
    //         'update_term_meta_cache' => false,
    //     ], $args));

    //     return !empty($query->terms) ? $query->terms[0] : null;
    // }

    public static function wpTerms($taxonomy, array $args = []): array
    {
        $query = new WP_Term_Query(array_merge([
            'taxonomy' => $taxonomy,
            'update_term_meta_cache' => false,
        ], $args));

        return $query->terms;
    }


    // review this again, may have better ways to get this in newer WP
    public static function wpTermsOfPostType(
        $taxonomy,
        $post_type,
        array $args = []
    ): array {

        \add_filter('terms_clauses', [static::class, 'wpTermsOfPostTypeHelper'], 10, 3);

        $args['post_types'] = (array) $post_type;
        $terms = \get_terms($taxonomy, $args);

        \remove_filter('terms_clauses', [static::class, 'wpTermsOfPostTypeHelper'], 10, 3);

        return empty($terms) || \is_wp_error($terms) ? [] : $terms;
    }

    public static function wpTermsOfPostTypeHelper($pieces, $tax, $args)
    {
        global $wpdb;

        // Don't use db count
        $pieces['fields'] .= ', COUNT(*) ';

        // Join extra tables to restrict by post type.
        $pieces['join'] .= " INNER JOIN $wpdb->term_relationships AS r ON r.term_taxonomy_id = tt.term_taxonomy_id INNER JOIN $wpdb->posts AS p ON p.ID = r.object_id ";

        // Restrict by post type and Group by term_id for counting.
        $pieces['where'] .= $wpdb->prepare(
            ' AND p.post_type IN(%s) GROUP BY t.term_id',
            implode(',', $args['post_types'])
        );

        return $pieces;
    }



    // public static function id($value, $taxonomy, $field = 'slug'): int
    // {
    //     $term = \get_term_by($field, $value, $taxonomy);

    //     return $term ? (int) $term->term_id : 0;
    // }


    // public static function ids($taxonomy, array $args = []): array
    // {
    //     $result = [];

    //     foreach (self::all($taxonomy, $args) as $term) {
    //         $result[] = (int) $term->term_id;
    //     }
    //     return $result;
    // }


    public static function postTerms($post_id, string $taxonomy = null, array $args = []): Terms
    {
        $id = Cast::postId($post_id);
        if (!$id) {
            return new Terms();
        }

        $query = new WP_Term_Query(array_merge([
            'taxonomy' => $taxonomy,
            'object_ids' => $id,
            // 'hide_empty' => false, // just check, but may be activated if helps performance
            'update_term_meta_cache' => false,
        ], $args));

        return Cast::terms($query->terms);
    }







    /**
     * Note: Beware when trying to read the post type name before it is registered, usually at the init action.
     */
    public static function postTypeName($post_type, bool $singular = false): string
    {
        if (empty($post_type)) {
            return '';
        }
        if (is_array($post_type)) {
            $post_type = $post_type[0];
        }

        $post_type_object = \get_post_type_object($post_type);

        if ($singular && !empty($post_type_object->labels->singular_name)) {
            return $post_type_object->labels->singular_name;
        }

        if (!empty($post_type_object->labels->name)) {
            return $post_type_object->labels->name;
        }

        return Str::title($post_type);
    }

    /**
     * Note: Beware when trying to read the name before it is registered, usually at the init action.
     */
    public static function taxonomyName($taxonomy, bool $singular = false): string
    {
        if (empty($taxonomy)) {
            return '';
        }

        $tax = \get_taxonomy($taxonomy);

        if ($tax) {

            if ($singular && !empty($tax->labels->singular_name)) {
                return $tax->labels->singular_name;
            }

            if (!empty($tax->labels->name)) {
                return $tax->labels->name;
            }
        }

        // fallback
        return ucfirst($taxonomy);
    }
}
