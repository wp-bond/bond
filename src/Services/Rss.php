<?php

namespace Bond\Services;

use Bond\App\View;
use Bond\Settings\Language;
use Bond\Settings\Rewrite;
use Bond\Settings\Wp;
use Bond\Utils\Link;
use Bond\Utils\Query;

class Rss
{
    protected array $feeds = [];


    public function config(array $settings)
    {
        $enable = $settings['enabled'] ?? null;

        foreach ($settings['feeds'] ?? [] as $key => $feed) {
            $this->addFeed($key, $feed);
        }

        if ($enable) {
            $this->enable();
        }
        if ($enable === false) {
            $this->disable();
        }

        if ($settings['disable_wp_rss'] ?? null) {
            $this->disableWpRss();
        }
    }

    public function enable()
    {
        // add query filter
        \add_filter('pre_get_posts', [$this, 'queryFilter']);
        \add_filter('posts_where', [$this, 'whereFilter']);

        // output RSS links to html head
        \add_action('wp_head', [$this, 'outputLinkTags']);
    }

    public function disable()
    {
        // add query filter
        \remove_filter('pre_get_posts', [$this, 'queryFilter']);
        \remove_filter('posts_where', [$this, 'whereFilter']);

        // output RSS links to html head
        \remove_action('wp_head', [$this, 'outputLinkTags']);
    }

    public function disableWpRss()
    {
        // templates
        \remove_action('do_feed_rdf', 'do_feed_rdf', 10, 0);
        \remove_action('do_feed_rss', 'do_feed_rss', 10, 0);
        \remove_action('do_feed_rss2', 'do_feed_rss2', 10, 1);
        \remove_action('do_feed_atom', 'do_feed_atom', 10, 1);

        // hmtl head
        // links to the extra feeds such as category feeds
        \remove_action('wp_head', 'feed_links_extra', 3);
        // the links to the general feeds: Post and Comment Feed
        \remove_action('wp_head', 'feed_links', 2);

        // rewrite rules
        if (Wp::isAdmin()) {
            \add_filter('rewrite_rules_array', function (array $rules) {

                foreach ($rules as $regex => $match) {
                    if (strpos($regex, 'feed|') !== false) {
                        unset($rules[$regex]);
                    }
                }
                return $rules;
            });
        }
    }

    public function addFeed(string $key, array $options)
    {
        $defaults = [
            'title' => app()->name(),
            'url' => '/rss',
            'multilanguage' => false,
        ];
        $options = array_merge($defaults, $options);

        // add rewrite rule
        Rewrite::rss(
            $key,
            $options['url'],
            $options['multilanguage'],
        );

        // add template
        \add_action('do_feed_' . $key, function () use ($key, $options) {

            // create a View handler
            $view = new View();

            // add the lookup folders, first the theme, then Bond's default
            $view->addLookupFolder(app()->viewsPath());
            $view->addLookupFolder(dirname(dirname(__DIR__)) . '/resources/views');

            // set the lookup order
            $view->setOrder([
                $key,
            ]);

            // add our feed values
            $view->add($this->feedValues($options));

            // load the template
            $view->template('feed');

            // templating will look in this order:
            // templates/feed-{feed_key} (at theme view folder)
            // templates/feed-{feed_key} (at bond source)
            // templates/feed (at theme view folder)
            // templates/feed (at bond source)
        });

        // store
        $this->feeds[$key] = $options;
    }


    protected function feedValues(array $feed): array
    {
        if ($feed['multilanguage']) {
            $title = tx($feed['title'], 'rss');
            $description = tx($feed['description'] ?? '', 'rss');
            $url = $feed['public_url'][Language::code()]
                ?? app()->url() . Link::path($feed['url']);
        } else {
            $title = $feed['title'];
            $description = $feed['description'] ?? '';
            $url = $feed['public_url']
                ?? app()->url() . '/' . trim($feed['url'], '/');
        }

        $image = $feed['image'] ?? '';
        $copyright = $feed['copyright']
            ?? '© ' . date('Y') . ' ' . app()->name() . '. ' . tx('All rights reserved', 'rss', null, 'en') . '.';

        $language = Language::tag();
        $update_period = $feed['update_period'] ?? 'daily';
        $update_frequency = $feed['update_frequency'] ?? 1;
        $last_build_date = Query::lastModified($feed['post_types'] ?? null)
            ->toRssString();


        return compact(
            'title',
            'description',
            'url',
            'image',
            'copyright',
            'language',
            'update_period',
            'update_frequency',
            'last_build_date',
        );
    }


    public function outputLinkTags()
    {
        foreach ($this->feeds as $feed) {

            if ($feed['multilanguage']) {
                foreach (Language::codes() as $code) {

                    $url = $feed['public_url'][$code]
                        ?? app()->url() . Link::path($feed['url'], $code);

                    echo '<link rel="alternate" type="application/rss+xml" href="' . $url . '" title="' . tx($feed['title'], 'rss', $code) . '" hreflang="' . $code . '">' . "\n";
                }
            } else {
                $url = $feed['public_url']
                    ?? app()->url() . '/' . trim($feed['url'], '/');

                echo '<link rel="alternate" type="application/rss+xml" href="' . $url . '" title="' . $feed['title'] . '">' . "\n";
            }
        }
    }


    public function queryFilter($query)
    {
        if (!$query->is_feed) {
            return;
        }

        // get config
        $feed = $this->feeds[$query->get('feed')] ?? null;
        if (!$feed) {
            return;
        }

        // change settings
        $query->set('post_type', $feed['post_types'] ?? \get_post_types([
            'public' => true,
        ]));
        $query->set('posts_per_page', $feed['max'] ?? 100);
    }


    // excludes password protected posts
    public function whereFilter($where)
    {
        global $wpdb, $wp_query;

        if (!$wp_query->is_feed) {
            return $where;
        }

        $feed = $this->feeds[$wp_query->get('feed')] ?? null;
        if (!$feed) {
            return $where;
        }

        return $where .= " AND ({$wpdb->posts}.post_password = '') ";
    }
}
