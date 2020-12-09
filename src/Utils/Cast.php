<?php

namespace Bond\Utils;

use Bond\Post;
use Bond\Posts;
use Bond\PostType;
use Bond\Support\Fluent;
use Bond\Taxonomy;
use Bond\Term;
use Bond\Terms;
use Bond\User;
use stdClass;
use WP_Post;
use WP_Term;
use WP_User;

class Cast
{
    protected static bool $loaded_classes = false;
    protected static array $posts = [];
    protected static array $post_types = [];
    protected static array $terms = [];
    protected static array $taxonomies = [];
    protected static array $users = [];


    // TODO consider moving to app container
    // would completelly change the API
    // would have to be non-static itself, to allow different mappings of post_type => concrete
    // would go out of Utils, into Support or Service or other
    // would have the api as cast()->post($post) instead of Cast::post()
    // or would be change to type()->post($post) or typeMap()->post()

    // would be a broad idea, to move Meta and others too
    // to have inject the app in their constructor
    // that way we trully can have several Meta, instead of hard-coded app() calls, that would fall into the default app anyway, invalidating the non-static usage after all

    // language, most control classes would move too
    // one app can have one language, the other can have another
    // in this case, we would spli Languages into Locale and Languages
    // where Locale is Settings still, and Languages can be one per app

    // feels strange on Links, but anyway, it's a way to have different links providers for each app
    // let's say we want to completly customize how to links are generated
    // link()->post($post) link()->search()

    // !!!! the worst issue is the Fluent using the Languages, which would it fallback? Maybe not a issue regarding cast() ??!?


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

                    // TODO only for pages?
                    if (
                        $_post->post_type === 'page'
                        && $_post->page_template
                    ) {
                        static::$posts[$_post->post_type . '_' . $_post->page_template] = $_class;
                    }
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

            // taxonomies
            if (is_subclass_of($_class, Taxonomy::class)) {
                if (isset($_class::$taxonomy)) {
                    static::$taxonomies[$_class::$taxonomy] = $_class;
                }
            }

            // users
            if (is_subclass_of($_class, User::class)) {
                $_user = new $_class();
                if (isset($_user->role)) {
                    static::$users[$_user->role] = $_class;
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
        if (is_null($value)) {
            return [];
        }
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

    public static function arrayRecursive($object, string $only = null): array
    {
        if (is_null($object)) {
            return [];
        }
        if (!is_object($object) && !is_array($object)) {
            return [];
        }

        $result = [];
        foreach ($object as $key => $value) {

            if (is_array($value)) {
                $result[$key] = static::arrayRecursive($value, $only);
            } elseif ($only) {
                if (is_a($value, $only)) {
                    $result[$key] = static::arrayRecursive($value, $only);
                } else {
                    $result[$key] = $value;
                }
            } elseif (is_object($value)) {
                if (method_exists($value, 'toArray')) {
                    $result[$key] = static::arrayRecursive($value->toArray(), $only);
                } elseif (method_exists($value, 'getArrayCopy')) {
                    $result[$key] = static::arrayRecursive($value->getArrayCopy(), $only);
                } else {
                    $result[$key] = static::arrayRecursive(get_object_vars($value), $only);
                }
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }



    // TODO testing, implement to term too later

    // TODO maybe use class at the post_type param
    // IMPORTANT use class as param may be better, it would allow the dev to really request the post as he wants, instead of depending it was registered or not

    // or just allow both!

    // or post_type is fine, as it's a shortcut, if it's implemented in many places, it's just better to not have to change everywhere, and just change the casting map

    // TODO maybe remove the convertion of post types
    // if needed users do it directly
    public static function post($post, string $post_type = null): ?Post
    {
        if (empty($post)) {
            return null;
        }

        // load matches
        static::loadClasses();


        // short-circuit if already Post
        // just ensure its what was request
        if ($post instanceof Post) {
            return static::maybeConvert($post, $post_type);
        }

        //
        if (config('cache.enabled')) {

            // 1
            if (is_numeric($post)) {

                $id = (int) $post;

                $post = Cache::php(
                    'bond/posts/' . $id,
                    config('cache.ttl') ?? 60 * 10,

                    function () use ($id) {

                        $post = Cast::wpPost($id);
                        if (!$post) {
                            return null;
                        }

                        $class = static::matchPostClass($post);

                        return Cache::putPhp(
                            'bond/posts/' . $post->post_type . '/' . $post->post_name,
                            new $class($post)
                        );
                    }
                );
                return static::maybeConvert($post, $post_type);
            }

            // 2
            if (is_string($post)) {

                if (!$post_type) {
                    return null;
                }

                $slug = $post;

                return Cache::php(
                    'bond/posts/' . $post_type . '/' . $slug,
                    config('cache.ttl') ?? 60 * 10,

                    function () use ($slug, $post_type) {

                        $post = Query::wpPostBySlug(
                            $slug,
                            $post_type
                        );
                        if (!$post) {
                            return null;
                        }

                        $class = static::matchPostClass($post);

                        return Cache::putPhp(
                            'bond/posts/' . $post->ID,
                            new $class($post)
                        );
                    }
                );
            }

            // 3a - has a post ID
            $id = self::postId($post);
            if ($id) {
                $post = self::wpPost($id);
            }

            // 3b - is WP_Post
            if ($post instanceof WP_Post) {

                $post = Cache::php(
                    'bond/posts/' . $post->ID,
                    config('cache.ttl') ?? 60 * 10,

                    function () use ($post) {

                        $class = static::matchPostClass($post);

                        return Cache::putPhp(
                            'bond/posts/' . $post->post_type . '/' . $post->post_name,
                            new $class($post)
                        );
                    }
                );
                return static::maybeConvert($post, $post_type);
            }
        } else {
            // without cache

            // 1 - get WP_Post by ID
            // 2 - or get WP_Post by slug

            if (is_numeric($post)) {
                $post = Cast::wpPost($post);
                if (!$post) {
                    return null;
                }
            } elseif (is_string($post)) {
                if (!$post_type) {
                    return null;
                }
                $post = Query::wpPostBySlug(
                    $post,
                    $post_type
                );
                if (!$post) {
                    return null;
                }
            } else {
                $id = self::postId($post);
                if ($id) {
                    $post = self::wpPost($id);
                }
            }
        }

        // Finally
        // if WP_Post, object or array

        // try to find post type
        if (!$post_type) {
            if (
                is_object($post)
                && isset($post->post_type)
                && $post->post_type
            ) {
                $post_type = $post->post_type;
                //
            } elseif (!empty($post['post_type'])) {
                $post_type = $post['post_type'];
            }
            // else it's fine, will fallback to Post
        }

        // create the Post
        if ($post instanceof \WP_Post) {
            $class = static::matchPostClass($post);
            return new $class($post);
        }

        $class = static::$posts[$post_type] ?? Post::class;
        return new $class($post);
    }

    protected static function matchPostClass(\WP_Post $post): string
    {
        if (
            $post->post_type === 'page'
            && $template = Query::pageTemplateName($post->ID)
        ) {
            return static::$posts[$post->post_type . '_' . $template]
                ?? static::$posts[$post->post_type]
                ?? Post::class;
        }
        return static::$posts[$post->post_type] ?? Post::class;
    }

    protected static function maybeConvert(?Post $post, ?string $post_type): ?Post
    {
        if (
            $post
            && $post_type
            && $post_type !== $post->post_type
        ) {
            $class = static::$posts[$post_type] ?? Post::class;
            return new $class($post);
        }
        return $post;
    }


    public static function posts($posts, string $post_type = null): Posts
    {
        $result = [];
        if (!empty($posts)) {
            foreach ($posts as $post) {
                if ($post = self::post($post, $post_type)) {
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
            return (int) ($post->ID ?? 0);
        }
        if (is_array($post)) {
            return (int) ($post['ID'] ?? 0);
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


    // Taxonomies

    public static function taxonomyClass($name): ?string
    {
        if (empty($name)) {
            return null;
        }

        // load
        static::loadClasses();

        if ($name instanceof Taxonomy) {
            $name = $name::$taxonomy;
        } elseif (is_object($name) && isset($name->taxonomy)) {
            $name = $name->taxonomy;
        } elseif (is_array($name) && !empty($name['taxonomy'])) {
            $name = $name['taxonomy'];
        }
        $name = (string) $name;

        // return only if exist
        return static::$taxonomies[$name] ?? null;
    }


    // Terms

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
            return (int) ($term->term_id ?? 0);
        }
        if (is_array($term)) {
            return (int) ($term['term_id'] ?? 0);
        }
        return (int) $term;
    }


    // Users

    // we do not cache users data
    public static function user($user): ?User
    {
        if (empty($user)) {
            return null;
        }

        // load matches
        static::loadClasses();

        // short-circuit if already User
        if ($user instanceof User) {
            return $user;
        }

        // get WP_User if string or numeric
        if (is_numeric($user) || is_string($user)) {
            $user = Cast::wpUser($user);
            if (!$user) {
                return null;
            }
        }

        // try to find role
        $role = null;
        if ($user instanceof WP_User && !empty($user->roles)) {
            $role = $user->roles[0];
        } elseif (is_object($user) && !empty($user->role)) {
            $role = $user->role;
        } elseif (is_array($user) && !empty($user['role'])) {
            $role = $user['role'];
        }

        // create the User
        $class = static::$users[$role] ?? User::class;
        return new $class($user);
    }

    public static function wpUser($user): ?WP_User
    {
        if (empty($user)) {
            return null;
        }
        if ($user instanceof WP_User) {
            return $user;
        }

        $id = self::userId($user);
        if ($id) {
            $user = \get_user_by('id', $id);
        } else {
            $lookup = (string) $user;
            $user = \get_user_by('slug', $lookup);
            if (!$user) {
                $user = \get_user_by('email', $lookup);
            }
            if (!$user) {
                $user = \get_user_by('login', $lookup);
            }
        }
        return $user instanceof WP_User ? $user : null;
    }

    public static function wpUsers($users): array
    {
        $result = [];
        if (!empty($users)) {
            foreach ($users as $user) {
                if ($user = self::wpUser($user)) {
                    $result[] = $user;
                }
            }
        }
        return $result;
    }

    public static function userId($user): int
    {
        if (empty($user)) {
            return 0;
        }
        if (is_object($user)) {
            return (int) ($user->ID ?? 0);
        }
        if (is_array($user)) {
            return (int) ($user['ID'] ?? 0);
        }
        return (int) $user;
    }
}
