<?php

namespace Bond\Utils;

use Bond\Post;
use Bond\Posts;
use Bond\PostType;
use Bond\Support\Fluent;
use Bond\Term;
use Bond\Terms;
use stdClass;
use WP_Post;
use WP_Term;

class Cast
{
    protected static bool $loaded_classes = false;
    protected static array $posts = [];
    protected static array $post_types = [];
    protected static array $terms = [];

    protected static function loadClasses()
    {
        if (static::$loaded_classes) {
            return;
        }

        foreach (get_declared_classes() as $_class) {

            // posts
            if (is_subclass_of($_class, Post::class)) {
                $_post = new $_class();
                if (isset($_post->post_type)) {
                    static::$posts[$_post->post_type] = $_class;
                }
            }

            // post types
            if (is_subclass_of($_class, PostType::class)) {
                if (isset($_class::$post_type)) {
                    static::$post_types[$_class::$post_type] = $_class;
                }
            }

            // terms
            if (is_subclass_of($_class, Term::class)) {
                $_term = new $_class();
                if (isset($_term->taxonomy)) {
                    static::$terms[$_term->taxonomy] = $_class;
                }
            }
        }

        static::$loaded_classes = true;
    }



    public static function fluent($value)
    {
        if ($value instanceof stdClass) {
            return new Fluent($value);
        }

        // objects stay objects
        if (is_object($value)) {
            return $value;
        }

        if (is_array($value)) {

            if (Arr::isAssoc($value)) {
                return new Fluent($value);
            }

            // indexed arrays stay Array
            $_value = [];
            foreach ($value as $v) {
                $_value[] = self::fluent($v);
            }
            return $_value;
        }

        return $value;
    }


    public static function array($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if ($value instanceof \WP_REST_Request) {
            return $value->get_params();
        }
        if (is_object($value)) {
            if (method_exists($value, 'toArray')) {
                return $value->toArray();
            }
            if (method_exists($value, 'getArrayCopy')) {
                return $value->getArrayCopy();
            }
            return get_object_vars($value);
        }
        return (array) $value;
    }



    public static function post($post): ?Post
    {
        if (empty($post)) {
            return null;
        }
        if ($post instanceof Post) {
            return $post;
        }

        // load matches
        static::loadClasses();

        // allow pre-populated values, without querying the database
        if (
            $post instanceof WP_Post
            || (is_object($post) && isset($post->post_type) && $post->post_type)
        ) {
            $class = static::$posts[$post->post_type] ?? Post::class;
            return new $class($post);
        }

        if (is_array($post) && !empty($post['post_type'])) {
            $class = static::$posts[$post['post_type']] ?? Post::class;
            return new $class($post);
        }

        // query the database
        $post = self::wpPost($post);
        if ($post) {
            $class = static::$posts[$post->post_type] ?? Post::class;
            return new $class($post);
        }
        return null;
    }

    public static function posts($posts): Posts
    {
        $result = [];
        if (!empty($posts)) {
            foreach ($posts as $post) {
                if ($post = self::post($post)) {
                    $result[] = $post;
                }
            }
        }
        return new Posts($result);
    }

    public static function wpPost($post): ?WP_Post
    {
        if ($post instanceof WP_Post) {
            return $post;
        }

        $id = self::postId($post);
        return $id ? \get_post($id) : null;
    }

    public static function wpPosts($posts): array
    {
        $result = [];
        if (!empty($posts)) {
            foreach ($posts as $post) {
                if ($post = self::wpPost($post)) {
                    $result[] = $post;
                }
            }
        }
        return $result;
    }

    public static function postId($post): int
    {
        if (empty($post)) {
            return 0;
        }
        if (is_object($post)) {
            return (int) ($post->ID ?? $post->id ?? 0);
        }
        if (is_array($post)) {
            return (int) ($post['ID'] ?? $post['id'] ?? 0);
        }
        return (int) $post;
    }





    // Post Types

    public static function postTypeClass($name): ?string
    {
        if (empty($name)) {
            return null;
        }

        // load
        static::loadClasses();

        if ($name instanceof PostType) {
            $name = $name::$post_type;
        } elseif (is_object($name) && isset($name->post_type)) {
            $name = $name->post_type;
        } elseif (is_array($name) && !empty($name['post_type'])) {
            $name = $name['post_type'];
        }
        $name = (string) $name;

        // return only if exist
        return static::$post_types[$name] ?? null;
    }




    // Taxonomy

    public static function term($term): ?Term
    {
        if (empty($term)) {
            return null;
        }
        if ($term instanceof Term) {
            return $term;
        }

        // load matches
        static::loadClasses();

        // allow pre-populated values, without querying the database
        if (
            $term instanceof WP_Term
            || (is_object($term) && isset($term->taxonomy) && $term->taxonomy)
        ) {
            $class = static::$terms[$term->taxonomy] ?? Term::class;
            return new $class($term);
        }

        if (is_array($term) && !empty($term['taxonomy'])) {
            $class = static::$terms[$term['taxonomy']] ?? Term::class;
            return new $class($term);
        }

        // query the database
        $term = self::wpTerm($term);
        if ($term) {
            $class = static::$terms[$term->taxonomy] ?? Term::class;
            return new $class($term);
        }
        return null;
    }

    public static function terms($terms): Terms
    {
        $result = [];
        if (!empty($terms)) {
            foreach ($terms as $term) {
                if ($term = self::term($term)) {
                    $result[] = $term;
                }
            }
        }
        return new Terms($result);
    }

    public static function wpTerm($term): ?WP_Term
    {
        if ($term instanceof WP_Term) {
            return $term;
        }
        return Query::wpTerm($term);
    }

    public static function wpTerms($terms): array
    {
        $result = [];
        if (!empty($terms)) {
            foreach ($terms as $term) {
                if ($term = self::wpTerm($term)) {
                    $result[] = $term;
                }
            }
        }
        return $result;
    }

    public static function termId($term): int
    {
        if (empty($term)) {
            return 0;
        }
        if (is_object($term)) {
            return (int) ($term->term_id ?? $term->id ?? 0);
        }
        if (is_array($term)) {
            return (int) ($term['term_id'] ?? $term['id'] ?? 0);
        }
        return (int) $term;
    }
}
