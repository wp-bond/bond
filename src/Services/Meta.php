<?php

namespace Bond\Services;

use Bond\Settings\Wp;
use Bond\Settings\Languages;
use Bond\Utils\Cast;
use Bond\Utils\Image;
use Bond\Utils\Link;
use Bond\Utils\Query;
use Bond\Utils\Str;

// The Meta class, basically a storage and meta tag printer

// TODO needs upgrade

class Meta
{
    // common
    public $title_separator = '-';
    public $title = [];
    public $description = '';
    public $url = '';
    public $images = []; // urls or ids
    public $alternate = []; // associative by language code

    // author
    public $author;

    // og
    public $og_type = 'website';
    public $og_title = '';
    public $og_description = '';
    public $article_section;

    // twitter
    public $twitter_card = 'summary'; //summary_large_image
    public $twitter_creator;
    public $twitter_image_src;
    public $twitter_image_width;
    public $twitter_image_height;
    public $twitter_player;
    public $twitter_player_width;
    public $twitter_player_height;


    public function __construct()
    {
        // required image size
        Wp::addImageSizes([
            'meta' => [2400, 2400],
        ]);

        // do not initialize when it is on WP admin
        // nor when programatically loading WP
        if (!Wp::isAdminWithTheme()) {
            $this->init();
        }
    }

    protected function init()
    {
        \add_action('wp', [$this, 'setDefaults'], 2);
        \add_action('wp_head', [$this, 'printAllTags'], 99);
    }

    public function separator()
    {
        return ' ' . $this->title_separator . ' ';
    }

    public function _separator()
    {
        return ' ' . $this->title_separator;
    }

    public function separator_()
    {
        return $this->title_separator . ' ';
    }

    public function setDefaults()
    {
        global $post, $paged;

        // store reference
        $post = $post ? Cast::post($post) : null;

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

            $this->url = app()->url() . Languages::urlPrefix() . '/';
            return;
        }

        // get request uri splitting on the query string
        $uri = explode('?', $_SERVER['REQUEST_URI']);

        $this->url = app()->url() . $uri[0];

        if (is_404()) {
            $this->title[] = __('Page not found');
        } elseif (is_post_type_archive()) {
            $this->title[] = Query::postTypeName($post_type);
        } elseif (is_search()) {
            global $s;
            $this->title[] = __('Search') . (!empty($s) ? ': ' . $s : '');
            // $this->title[] = __('Search') . (!empty($s) ? ': ' . $s : '');
        } elseif (is_author()) {
            global $authordata; // same global as WP

            if ($post && !$authordata) {
                $authordata = \get_userdata($post->post_author);
            }

            if ($authordata) {
                $this->title[] = $authordata->data->display_name;
            }

            $this->title[] = __('Author');
        } elseif (is_singular()) {
            $this->title[] = $post->title ?: $post->post_title;

            if ($post_type === 'page') {
                $post_parent_id = (int) $post->post_parent;

                if ($post_parent_id) {
                    $p = Cast::post($post_parent_id);
                    if ($p) {
                        $this->title[] = $p->title ?: $p->post_title;
                    }


                    // $this->title[] = get_the_title($post_parent_id);
                }
            } else {
                $this->title[] = Query::postTypeName($post_type);
            }

            // if (function_exists('get_field'))
            //     $this->description = get_field('field_post_meta_description', $post->ID);

            if (empty($this->description)) {
                $this->description = !empty($post->post_excerpt) ? $post->post_excerpt : $post->post_content;
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

            if (!empty($monthnum)) {
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

        $this->printJsVars();

        // Reference:
        // Facebook Validator: https://developers.facebook.com/tools/debug
        // Twitter Validator: https://dev.twitter.com/docs/cards/validation/validator
    }

    public function printMultilanguageTags()
    {
        global $post, $post_type;

        if (is_array($post_type)) {
            $post_type = $post_type[0];
        }

        if ($post) {
            $post = Cast::post($post);
        }

        foreach (Languages::codes() as $code) {

            if ($code === Languages::code()) {
                continue;
            }

            $url = false;

            if (!empty($this->alternate)) {
                $url = $this->alternate[$code] ?? '';
            } else {
                if (is_front_page()) {
                    $url = app()->url();
                    //
                } elseif (is_archive()) {
                    $url = app()->url()
                        . Link::postType($post_type, $code);
                    //
                } elseif ($post && \is_singular()) {
                    $url = app()->url()
                        . $post->link($code);
                }
            }

            if ($url) {
                echo '<link rel="alternate" hreflang="' . $code . '" href="' . $url . '">' . "\n";
            }
        }
    }

    public function printJsVars()
    {
        echo "\n" . '<script>';
        echo 'window.IS_PRODUCTION=Boolean("' . app()->isProduction() . '");';
        echo 'window.LANGUAGE_CODE="' . Languages::code() . '";';
        echo 'window.LANG="' . Languages::shortCode() . '";';
        echo 'window.IS_MOBILE=Boolean("' . app()->isMobile() . '");';
        echo 'window.IS_TABLET=Boolean("' . app()->isTablet() . '");';
        echo 'window.IS_DESKTOP=Boolean("' . app()->isDesktop() . '");';
        echo '</script>' . "\n";
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
    }

    public function printOgTags()
    {
        echo '<meta property="og:type" content="' . $this->og_type . '">' . "\n";
        echo '<meta property="og:site_name" content="' . app()->name() . '">' . "\n";
        echo '<meta property="og:locale" content="' . Languages::locale() . '">' . "\n";

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

        if ($this->og_type === 'article') {
            global $post;
            if (!empty($post)) {
                $time = strtotime($post->post_date);
                $modified = strtotime($post->post_modified);

                echo '<meta property="article:published_time" content="' . date('c', $time) . '">' . "\n";
                echo '<meta property="article:modified_time" content="' . date('c', $modified) . '">' . "\n";

                // https://developers.facebook.com/docs/reference/opengraph/object-type/article
            }

            if (!empty($this->article_section)) {
                echo '<meta property="article:section" content="' . $this->article_section . '">' . "\n";
            }

            if (config('services.facebook.url')) {
                echo '<meta property="article:publisher" content="' . config('services.facebook.url') . '">' . "\n";
            }

            if (config('services.facebook.pages')) {
                echo '<meta property="fb:pages" content="' . config('services.facebook.pages') . '">' . "\n";
            }
        }
    }

    public function printFacebookTags()
    {
        if (config('services.facebook.app_id')) {
            echo '<meta property="fb:app_id" content="' . config('services.facebook.app_id') . '">' . "\n";
        }

        if (config('services.facebook.admins')) {
            echo '<meta property="fb:admins" content="' . config('services.facebook.admins') . '">' . "\n";
        }
    }

    public function printTwitterTags()
    {
        echo '<meta name="twitter:card" content="' . $this->twitter_card . '">' . "\n";

        if (config('services.twitter.user')) {
            echo '<meta name="twitter:site" content="@' . config('services.twitter.user') . '">' . "\n";
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
        $img_src = Image::source($id, 'meta');

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
