<?php

namespace Bond\Services;

use Bond\Settings\Language;
use Bond\Utils\Arr;
use Bond\Utils\Cast;
use Bond\Utils\Query;

// TODO method to add extra links via config, maybe an addLink method too

class Sitemap implements ServiceInterface
{
    protected bool $enabled = false;
    private $wp_renderer = null;
    protected array $skip_archives = [];
    protected array $skip_singles = [];
    protected array $skip_pages = [];


    public function config(
        ?bool $enabled = null,
        ?bool $stylesheet = null,
        ?array $post_types = null,
        ?array $taxonomies = null,
        ?array $users = null,
        ?array $skip_archives = null,
        ?array $skip_singles = null,
        ?array $skip_pages = null,
    ) {
        if (isset($enabled)) {
            if ($enabled) {
                $this->enable();
            } else {
                $this->disable();
            }
        }

        if ($stylesheet === false) {
            $this->disableStylesheet();
        }

        if (isset($post_types)) {
            if (count($post_types)) {
                $this->postTypes($post_types);
            } else {
                $this->disablePosts();
            }
        }
        if (isset($taxonomies)) {
            if (count($taxonomies)) {
                $this->taxonomies($taxonomies);
            } else {
                $this->disableTaxonomies();
            }
        }
        if (isset($users)) {
            if (count($users)) {
                // TODO how to add only specific user to sitemap?
                // or even a method to allow all of specific roles, but still hide some users?
                // $this->users($users);
            } else {
                $this->disableUsers();
            }
        }

        if (isset($skip_archives)) {
            $this->skip_archives = $skip_archives;
        }
        if (isset($skip_singles)) {
            $this->skip_singles = $skip_singles;
        }
        if (isset($skip_pages)) {
            $this->skip_pages = $skip_pages;
        }
    }

    public function enable()
    {
        if (!$this->enabled) {
            $this->enabled = true;

            \add_filter('wp_sitemaps_posts_pre_url_list', [$this, 'postsUrls'], 10, 3);

            \add_action('wp_sitemaps_init', [$this, 'swapRenderer']);

            \add_filter('wp_sitemaps_enabled', '__return_true');
            \add_action('init', function () {
                wp_sitemaps_get_server();
            });
        }
    }

    public function disable(bool $including_wp_sitemaps = true)
    {
        if ($this->enabled) {
            $this->enabled = false;

            \remove_filter('wp_sitemaps_posts_pre_url_list', [$this, 'postsUrls'], 10, 3);

            \remove_action('wp_sitemaps_init', [$this, 'revertRenderer'], 11);

            if ($including_wp_sitemaps) {
                \add_filter('wp_sitemaps_enabled', '__return_false');
                \add_action('init', function () {
                    \remove_action('init', 'wp_sitemaps_get_server');
                }, 5);
            }
        }
    }

    public function disableStylesheet()
    {
        \add_filter('wp_sitemaps_stylesheet_url', '__return_empty_string');
        \add_filter('wp_sitemaps_stylesheet_index_url', '__return_empty_string');
    }

    public function postTypes(array $post_types)
    {
        \add_filter('wp_sitemaps_post_types', function ($all) use ($post_types) {
            return Arr::only($all, $post_types);
        });
    }

    public function taxonomies(array $taxonomies)
    {
        \add_filter('wp_sitemaps_taxonomies', function ($all) use ($taxonomies) {
            return Arr::only($all, $taxonomies);
        });
    }

    public function disablePosts()
    {
        $this->disableProvider('posts');
    }

    public function disableTaxonomies()
    {
        $this->disableProvider('taxonomies');
    }

    public function disableUsers()
    {
        $this->disableProvider('users');
    }

    public function disableProvider(string $provider_name)
    {
        \add_filter(
            'wp_sitemaps_add_provider',
            function ($provider, $name) use ($provider_name) {
                if ($name === $provider_name) {
                    return false;
                }
                return $provider;
            },
            10,
            2
        );
    }

    public function swapRenderer(\WP_Sitemaps $sitemaps)
    {
        $this->wp_renderer = $sitemaps->renderer;
        $sitemaps->renderer = new SitemapRenderer();
    }

    public function revertRenderer(\WP_Sitemaps $sitemaps)
    {
        if ($this->wp_renderer) {
            $sitemaps->renderer = $this->wp_renderer;
            $this->wp_renderer = null;
        }
    }

    public function postsUrls(?array $url_list, string $post_type, int $page_num): array
    {
        $posts = Query::all($post_type, [
            'posts_per_page' => \wp_sitemaps_get_max_urls('post'),
            'paged' => $page_num,
        ]);

        $url_list = [];

        // archives
        if (
            $page_num === 1
            && !in_array($post_type, $this->skip_archives)
        ) {
            $class = Cast::postTypeClass($post_type);
            if ($class) {
                // put together all alternate links
                $alternates = [];
                foreach (Language::codes() as $lang) {
                    $alternates[$lang] = app()->url() . $class::link($lang);
                }
                // add to url list
                foreach (Language::codes() as $lang) {
                    $url_list[] = [
                        'loc' => app()->url() . $class::link($lang),
                        'lastmod' => Query::lastModified($post_type),
                        // 'changefreq' => 'daily',
                        'priority' => 1,
                        'alternates' => count($alternates) > 1 ? $alternates : null,
                    ];
                }
            }
        }

        // posts
        if (!in_array($post_type, $this->skip_singles)) {
            foreach ($posts as $post) {

                // skip selected pages
                if (
                    $post_type === 'page'
                    && in_array($post->post_name, $this->skip_pages)
                ) {
                    continue;
                }

                // put together all alternate links
                $alternates = [];
                foreach (Language::codes() as $lang) {
                    if ($post->isDisabled($lang)) {
                        continue;
                    }
                    $alternates[$lang] = app()->url() . $post->link($lang);
                }

                // add to url list
                foreach (Language::codes() as $lang) {
                    if ($post->isDisabled($lang)) {
                        continue;
                    }
                    $url_list[] = [
                        'loc' => app()->url() . $post->link($lang),
                        'lastmod' => $post->post_modified,
                        // 'changefreq' => 'daily',
                        'alternates' => count($alternates) > 1 ? $alternates : null,
                    ];
                }
            }
        }

        return $url_list;
    }
}
