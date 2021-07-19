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
    protected static bool $has_loaded_classes = false;
    protected static array $posts = [];
    protected static array $post_types = [];
    protected static array $terms = [];
    protected static array $taxonomies = [];
    protected static array $users = [];

    // TODO MAYBE move cast into app() ? to have separate apps, handling differently the casting
    // at least consider that currently app is hard coded to load App namespace classes, whereas this Cast class is not
    // maybe move all as App?

    protected static function loadClasses()
    {
        if (static::$has_loaded_classes) {
            return;
        }
        static::$has_loaded_classes = true;

        foreach (get_declared_classes() as $_class) {

            // posts
            if (is_subclass_of($_class, Post::class)) {
                $_post = new $_class();

                if (isset($_post->post_type)) {
                    if (isset($_post->post_name)) {
                        static::$posts[$_post->post_type . '/' . $_post->post_name] = $_class;
                        //
                    } elseif (isset($_post->page_template)) {
                        static::$posts[$_post->post_type . ':' . $_post->page_template] = $_class;
                        //
                    } else {
                        static::$posts[$_post->post_type] = $_class;
                    }
                }
            } elseif (is_subclass_of($_class, PostType::class)) {
                if (isset($_class::$post_type)) {
                    static::$post_types[$_class::$post_type] = $_class;
                }
            } elseif (is_subclass_of($_class, Term::class)) {
                $_term = new $_class();
                if (isset($_term->taxonomy)) {
                    static::$terms[$_term->taxonomy] = $_class;
                }
            } elseif (is_subclass_of($_class, Taxonomy::class)) {
                if (isset($_class::$taxonomy)) {
                    static::$taxonomies[$_class::$taxonomy] = $_class;
                }
            } elseif (is_subclass_of($_class, User::class)) {
                $_user = new $_class();
                if (isset($_user->role)) {
                    static::$users[$_user->role] = $_class;
                }
            }
        }
    }


    // TODO move to fluent itself or Obj::fluent
    // create an internal variant as maybeFluent, which is this code
    // and a public which strictly returns Fluent / FluentList
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

            if (!array_is_list($value)) {
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





    // TODO testing, implement to term too later

    // TODO maybe use class at the post_type param
    // IMPORTANT use class as param may be better, it would allow the dev to really request the post as he wants, instead of depending it was registered or not

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
        if (cache()->enabled()) {

            // 1
            if (is_numeric($post)) {

                $id = (int) $post;

                $post = cache()->remember(
                    'bond/posts/' . $id,
                    function () use ($id) {

                        $post = Cast::wpPost($id);
                        if (!$post) {
                            return null;
                        }

                        $class = static::matchPostClass($post);
                        $p = new $class($post);

                        if ($post->post_name) {
                            cache()->set(
                                'bond/posts/' . $post->post_type . '/' . $post->post_name,
                                $p
                            );
                        }

                        return $p;
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

                return cache()->remember(
                    'bond/posts/' . $post_type . '/' . $slug,

                    function () use ($slug, $post_type) {

                        $post = Query::wpPostBySlug(
                            $slug,
                            $post_type
                        );
                        if (!$post) {
                            return null;
                        }

                        $class = static::matchPostClass($post);
                        $p = new $class($post);

                        cache()->set(
                            'bond/posts/' . $post->ID,
                            $p
                        );
                        return $p;
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

                $post = cache()->remember(
                    'bond/posts/' . $post->ID,

                    function () use ($post) {

                        $class = static::matchPostClass($post);
                        $p = new $class($post);

                        if ($post->post_name) {
                            cache()->set(
                                'bond/posts/' . $post->post_type . '/' . $post->post_name,
                                $p
                            );
                        }

                        return $p;
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
        $type = $post->post_type;

        if (isset(static::$posts[$type . '/' . $post->post_name])) {
            return static::$posts[$type . '/' . $post->post_name];
        }
        if ($template = Query::pageTemplate($post->ID)) {
            return static::$posts[$type . ':' . $template]
                ?? static::$posts[$type]
                ?? Post::class;
        }
        return static::$posts[$type] ?? Post::class;
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

        // TODO, maybe if cache is enabled we can convert a Post into WP_Post

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
