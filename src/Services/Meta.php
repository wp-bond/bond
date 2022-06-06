<?php

namespace Bond\Services;

use Bond\Settings\Language;
use Bond\Support\Fluent;
use Bond\Utils\Cast;
use Bond\Utils\Image;
use Bond\Utils\Link;
use Bond\Utils\Query;
use Bond\Utils\Str;

// The Meta class, basically a storage and meta tag printer

// TODO needs upgrade

class Meta extends Fluent implements ServiceInterface
{
    // settings
    protected string $title_separator = '-';
    protected string $search_title = 'Search';
    protected array $tags = [];

    // values
    public array $title = [];
    public ?string $description = '';
    public string $url = '';
    public array $images = []; // urls or ids
    public array $alternate = []; // associative by language code
    public string $image_size = 'large';

    // author
    public string $me = '';
    public string $author = '';

    //
    public Fluent $og;
    public Fluent $article;
    public Fluent $facebook;
    public Fluent $twitter;
    public Fluent $instagram;
    public Fluent $pinterest;

    // TODO migrate all to the new Fluents above
    public string $og_type = 'website';
    public string $og_title = '';
    public string $og_description = '';
    public string $article_section;

    // twitter
    public string $twitter_card = 'summary'; //summary_large_image
    public string $twitter_creator;
    public string $twitter_image_src;
    public string $twitter_image_width;
    public string $twitter_image_height;
    public string $twitter_player;
    public string $twitter_player_width;
    public string $twitter_player_height;

    public function __construct($data = null)
    {
        $this->og = new Fluent();
        $this->article = new Fluent();
        $this->facebook = new Fluent();
        $this->twitter = new Fluent();
        $this->instagram = new Fluent();
        $this->pinterest = new Fluent();
        parent::__construct($data);
    }

    public function config(
        ?bool $enabled = null,
        ...$args
    ) {
        $this->add($args);

        if (isset($enabled)) {
            if ($enabled) {
                $this->enable();
            } else {
                $this->disable();
            }
        }
    }

    public function enable()
    {
        // do not enable when it is on WP admin
        // nor when programatically loading WP
        if (app()->isFrontEnd()) {
            \add_action('wp', [$this, 'setDefaults'], 2);
            \add_action('wp_head', [$this, 'printAllTags'], 99);
        }
    }

    public function disable()
    {
        \remove_action('wp', [$this, 'setDefaults'], 2);
        \remove_action('wp_head', [$this, 'printAllTags'], 99);
    }


    private function separator()
    {
        return ' ' . $this->title_separator . ' ';
    }

    public function setDefaults()
    {
        global $post, $paged;

        // prepare
        $paged = (int) $paged;
        $post_type = false;

        if ($post) {
            $post_type = $post->post_type;
        }

        // set the defaults
        if (is_front_page()) {
            $this->title[] = app()->name();
            // $this->images[] = app()->url().'/apple-touch-icon-1200.png';

            // if (function_exists('get_field'))
            //     $this->description = get_field('field_meta_description', 'options');

            // if (!empty($post))
            //     $this->description = !empty($post->post_excerpt) ? $post->post_excerpt : $post->post_content;

            $this->url = app()->url() . Language::urlPrefix() . '/';
            return;
        }

        // get request uri splitting on the query string
        $uri = explode('?', $_SERVER['REQUEST_URI']);

        $this->url = app()->url() . $uri[0];

        if (is_404()) {
            $this->title[] = __('Page not found');
        } elseif (is_post_type_archive()) {
            $this->title[] = Query::postTypeName(is_array($post_type) ? $post_type[0] : $post_type);
        } elseif (is_search()) {
            global $s;
            $this->title[] = __($this->search_title) . (!empty($s) ? ': ' . $s : '');
            // $this->title[] = __($this->search_title) . (!empty($s) ? ': ' . $s : '');
        } elseif (is_author()) {
            global $authordata; // same global as WP

            if ($post && !$authordata) {
                $authordata = \get_userdata($post->post_author);
            }

            if ($authordata) {
                $this->title[] = $authordata->data->display_name;
            }

            $this->title[] = __('Author');
        } elseif (is_singular() && $post) {

            $p = Cast::post($post);

            $this->title[] = $p->title() ?: $p->post_title;

            if ($post_type === 'page') {
                $post_parent_id = (int) $post->post_parent;

                if ($post_parent_id) {
                    $p = Cast::post($post_parent_id);
                    if ($p) {
                        $this->title[] = $p->title() ?: $p->post_title;
                    }


                    // $this->title[] = get_the_title($post_parent_id);
                }
            } else {
                $this->title[] = Query::postTypeName($post_type);
            }

            // if (function_exists('get_field'))
            //     $this->description = get_field('field_post_meta_description', $post->ID);

            if (empty($this->description)) {
                $this->description = $p->content();
            }

            $this->url = \get_permalink($post->ID);
        }

        if (is_category() || is_tax() || is_tag()) {

            $term = \get_queried_object();
            if ($term) {
                $term = Cast::term($term);
            }
            if ($term) {
                $this->title[] = $term->name;
            }
        } elseif (is_date()) {
            global $monthnum, $year;

            if (!empty($monthnum) && $post) {
                $this->title[] = date('F Y', strtotime($post->post_date));
            } else {
                $this->title[] = $year;
            }
        }

        // append page number
        if ($paged > 1) {
            $this->title[] = __('Page') . ' ' . $paged;
        }

        // append site name
        $this->title[] = app()->name();

        //implement http://ogp.me/#type_article
    }


    public function printAllTags()
    {
        $this->printMultilanguageTags();

        $this->printTitleTags();
        $this->printDescriptionTags();

        $this->printSettingsTags();
        $this->printAuthorTags();
        $this->printOgTags();

        $this->printFacebookTags();
        $this->printTwitterTags();

        foreach ($this->tags as $tag) {
            echo $tag;
        }

        // Reference:
        // Facebook Validator: https://developers.facebook.com/tools/debug
        // Twitter Validator: https://dev.twitter.com/docs/cards/validation/validator
    }

    public function printMultilanguageTags()
    {
        foreach (Language::codes() as $code) {

            if ($code === Language::code()) {
                continue;
            }

            $url = false;

            if (!empty($this->alternate)) {
                $url = $this->alternate[$code] ?? '';
            } else {
                $url = Link::current($code);
                if ($url) {
                    $url = app()->url() . $url;
                }
            }

            if ($url) {
                echo '<link rel="alternate" hreflang="' . $code . '" href="' . $url . '">' . "\n";
            }
        }
    }

    public function printTitleTags()
    {
        // sanitize title and allow WP fallback
        $title = $this->getTitle();

        // allow alternative og title, not recommended but still
        $og_title = !empty($this->og_title) ? Str::clean($this->og_title) : $title;

        // print
        echo '<title>' . $title . '</title>' . "\n";
        echo '<meta property="og:title" content="' . $og_title . '">' . "\n";
        echo '<meta name="twitter:title" content="' . $og_title . '">' . "\n";
    }

    public function getTitle()
    {
        // sanitize title and allow WP fallback
        if (!empty($this->title)) {
            $title = is_array($this->title) ? implode($this->separator(), $this->title) : $this->title;
            $title = Str::clean($title);
        } else {
            $title = wp_title($this->title_separator, false, 'right') . app()->name();
        }

        return $title;
    }

    public function addTitle($title)
    {
        if (empty($title)) {
            return;
        }
        array_unshift($this->title, $title);
    }

    public function printDescriptionTags()
    {
        // don't print empty descriptions
        if (empty($this->description)) {
            return;
        }

        $description = Str::clean($this->description, 100);
        $og_description = !empty($this->og_description) ? Str::clean($this->og_description, 100) : $description;

        // $description = mb_strtolower($description);
        // $og_description = mb_strtolower($og_description);

        echo '<meta name="description" content="' . $description . '">' . "\n";
        echo '<meta property="og:description" content="' . $og_description . '">' . "\n";
        echo '<meta name="twitter:description" content="' . $og_description . '">' . "\n";
    }

    public function printSettingsTags()
    {
        // page url
        if ($this->url) {
            echo '<meta property="og:url" content="' . $this->url . '">' . "\n";
        }
    }

    public function printAuthorTags()
    {
        if (!empty($this->author)) {
            echo '<link rel="author" href="' . $this->author . '">' . "\n";
        }
        if (!empty($this->me)) {
            echo '<link rel="me" href="' . $this->me . '">' . "\n";
        }
    }

    public function printOgTags()
    {
        echo '<meta property="og:type" content="' . $this->og_type . '">' . "\n";
        echo '<meta property="og:site_name" content="' . app()->name() . '">' . "\n";
        echo '<meta property="og:locale" content="' . Language::locale() . '">' . "\n";

        //og images
        if (!empty($this->images)) {

            foreach ($this->images as $image) {

                if (is_array($image)) {

                    echo '<meta property="og:image" content="'
                        . $image['url']
                        . '">' . "\n";
                    // og:image:secure_url

                    echo '<meta property="og:image:type" content="'
                        . $image['type']
                        . '">' . "\n";

                    echo '<meta property="og:image:width" content="'
                        . $image['width']
                        . '">' . "\n";

                    echo '<meta property="og:image:height" content="'
                        . $image['height']
                        . '">' . "\n";
                } else {
                    echo '<meta property="og:image" content="'
                        . $image
                        . '">' . "\n";
                }
            }
        }

        if ($this->og->type === 'article') {
            global $post;
            if (!empty($post)) {
                $time = strtotime($post->post_date);
                $modified = strtotime($post->post_modified);

                echo '<meta property="article:published_time" content="' . date('c', $time) . '">' . "\n";
                echo '<meta property="article:modified_time" content="' . date('c', $modified) . '">' . "\n";

                // https://developers.facebook.com/docs/reference/opengraph/object-type/article
            }

            if ($this->article->section) {
                echo '<meta property="article:section" content="' . $this->article->section . '">' . "\n";
            }

            $publisher = $this->article->publisher ?: $this->facebook->url ?: null;
            if ($publisher) {
                echo '<meta property="article:publisher" content="' . $publisher . '">' . "\n";
            }

            if ($this->facebook->pages) {
                echo '<meta property="fb:pages" content="' . $this->facebook->pages . '">' . "\n";
            }
        }
    }

    public function printFacebookTags()
    {
        if ($this->facebook->app_id) {
            echo '<meta property="fb:app_id" content="' . $this->facebook->app_id . '">' . "\n";
        }
        if ($this->facebook->admins) {
            echo '<meta property="fb:admins" content="' . $this->facebook->admins . '">' . "\n";
        }
    }

    public function printTwitterTags()
    {
        echo '<meta name="twitter:card" content="' . $this->twitter_card . '">' . "\n";

        if ($this->twitter->user) {
            echo '<meta name="twitter:site" content="@' . $this->twitter->user . '">' . "\n";
        }

        echo '<meta name="twitter:domain" content="' . Str::domain(app()->url()) . '">' . "\n";

        if (!empty($this->twitter_creator)) {
            echo '<meta name="twitter:creator" content="@' . (strpos($this->twitter_creator, '@') === 0 ? substr($this->twitter_creator, 1) : $this->twitter_creator) . '">' . "\n";
        }

        if ($this->twitter_card === 'gallery') {
            if (!empty($this->images)) {
                $i = 0;
                foreach ($this->images as $image) {

                    if (is_array($image)) {
                        $url = $image['url'];
                    } else {
                        $url = $image;
                    }
                    echo '<meta name="twitter:image' . $i . ':src" content="' . $url . '">' . "\n";

                    if (++$i >= 4) {
                        break;
                    }
                }
            }
        } else {
            if (!empty($this->twitter_image_src)) {
                echo '<meta name="twitter:image:src" content="' . $this->twitter_image_src . '">' . "\n";
            } elseif (!empty($this->images)) {

                if (is_array($this->images[0])) {
                    $url = $this->images[0]['url'];
                } else {
                    $url = $this->images[0];
                }

                echo '<meta name="twitter:image:src" content="' . $url . '">' . "\n";
            }

            //this should be auto anyway no? or maybe just remove it at all
            if (!empty($this->twitter_image_width)) {
                echo '<meta name="twitter:image:width" content="' . $this->twitter_image_width . '">' . "\n";
            }

            if (!empty($this->twitter_image_height)) {
                echo '<meta name="twitter:image:height" content="' . $this->twitter_image_height . '">' . "\n";
            }
        }

        // if (!empty($this->twitter_player))
        //     echo '<meta name="twitter:player" content="'.Str::https($this->twitter_player).'">';
        // if (!empty($this->twitter_player_width))
        //     echo '<meta name="twitter:player:width" content="'.$this->twitter_player_width.'">';
        // if (!empty($this->twitter_player_height))
        //     echo '<meta name="twitter:player:height" content="'.$this->twitter_player_height.'">';
    }

    public function addPreload(
        ?string $href = null,
        ?string $as = null,
        ?string $type = null,
        ?string $crossorigin = null,
        ?string $imagesrcset = null,
        ?string $media = null,
        ?string $sizes = null,
    ) {

        $attrs = array_merge(
            ['rel' => 'preload'],
            array_filter(get_defined_vars())
        );

        $data = str_replace(
            "=",
            '="',
            urldecode(http_build_query($attrs, '', '" ', PHP_QUERY_RFC3986))
        ) . '"';

        $this->tags[] = ' <link ' . $data . '>';
    }

    public function addImages()
    {
        $lookup = [];
        foreach (func_get_args() as $arg) {
            if (!empty($arg)) {
                $lookup[] = $arg;
            }
        }

        foreach ($lookup as $item) {

            if (empty($item)) {
                continue;
            }

            $url = false;

            if (is_array($item)) {

                if (!empty($item['oembed'])) {
                    if (!empty($item['oembed']->poster_original_src)) {
                        $url = $item['oembed']->poster_original_src;
                    }
                } elseif (!empty($item['image'])) {
                    // considers the image is a number, could be upgrade to handle ACF objects too
                    $url = $this->getImageInfo((int) $item['image']);
                } elseif (!empty($item['image_group'])) {
                    //recurse
                    $this->addImages($item['image_group']);
                } elseif (!empty($item['id'])) {
                    $url = $this->getImageInfo((int) $item['id']);
                } else {

                    foreach ($item as $value) {

                        if (is_array($value)) {
                            $this->addImages($value);
                        } elseif (is_numeric($value)) {
                            $url = $this->getImageInfo((int) $value);
                        } else {
                            $url = esc_url($value);
                        }

                        if ($url) {
                            $this->images[] = $url;
                        }
                    }

                    // skip the add below
                    $url = false;
                }
            } elseif ((int) $item) {
                $url = $this->getImageInfo((int) $item);
            } else {
                $url = esc_url($item);
            }

            if ($url) {
                $this->images[] = $url;
            }
        }
    }

    // private function is_assoc(array $array)
    // {
    //     // Keys of the array
    //     $keys = array_keys($array);

    //     // If the array keys of the keys match the keys, then the array must
    //     // not be associative (e.g. the keys array looked like {0:0, 1:1...}).
    //     return array_keys($keys) !== $keys;
    // }


    public function getImageInfo($id)
    {
        $id = (int) $id;
        $img_src = Image::source($id, $this->image_size);

        if (!empty($img_src[0]) && !empty($img_src[1]) && !empty($img_src[2])) {

            return [
                'url' => $img_src[0],
                'width' => $img_src[1],
                'height' => $img_src[2],
                'type' => \get_post_mime_type($id),
            ];
        }

        return null;
    }
}
