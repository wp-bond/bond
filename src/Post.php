<?php

namespace Bond;

use Bond\Utils\Link;
use Bond\Support\Fluent;
use Bond\Utils\Cache;
use Bond\Utils\Cast;
use Bond\Utils\Query;
use Bond\Settings\Languages;
use WP_Post;

class Post extends Fluent
{
    public int $ID;
    public string $post_type;

    // set properties that should not be added here
    protected array $exclude = [
        // 'post_title',
        // 'post_name',
        // 'post_mime_type',
        // 'post_author',
        // 'post_parent',
        // 'post_date',
        // 'post_date_gmt',
        // 'post_modified',
        // 'post_modified_gmt',
        // 'post_content',
        'post_content_filtered',
        // 'post_excerpt',
        // 'menu_order',
        'ping_status',
        'to_ping',
        'pinged',
        'guid',
        'comment_status',
        'comment_count',
        'post_password', // always!
        'filter',
    ];

    public function __construct($values = null, bool $skip_cache = false)
    {
        if (!$skip_cache && config('cache.posts')) {
            $this->initFromCache($values);
        } else {
            $this->init($values);
        }
    }

    protected function initFromCache($values)
    {
        // try to know the ID
        // if not, just continue without cache

        if ($id = Cast::postId($values)) {

            $has_initted = false;

            $cached = Cache::json(
                'bond/posts/' . $id,
                config('cache.posts_ttl') ?? 60 * 10,

                function () use ($values, &$has_initted) {
                    $this->init($values);
                    $has_initted = true;
                    return $this->toArray();
                }
            );
            if (!$has_initted) {
                $this->add($cached);
            }
        } else {
            $this->init($values);
        }
    }

    protected function init($values)
    {
        if (empty($values)) {
            return;
        }

        // if is numeric we'll fetch the WP_Post and load fields
        // if is WP_Post we'll just add it and load fields
        if (is_numeric($values) || $values instanceof WP_Post) {
            $post = Cast::wpPost($values);
            if ($post) {
                $this->add($post);
                $this->loadFields();
            }
            return;
        }

        // if is string we'll try to find by slug and load fields
        if (is_string($values)) {
            if (isset($this->post_type)) {
                $post = Query::wpPostBySlug(
                    $values,
                    $this->post_type
                );
                if ($post) {
                    $this->add($post);
                    $this->loadFields();
                }
            }
            return;
        }

        // otherwise (object or array) are honored as the full value to added WITHOUT loading fields
        $this->add($values);
    }

    public function loadFields()
    {
        $this->add($this->getFields());
    }

    public function getFields(): ?array
    {
        if (isset($this->ID) && app()->hasAcf()) {
            return \get_fields($this->ID) ?: null;
        }
        return null;
    }

    public function slug(string $language_code = null): string
    {
        if (Languages::isMultilanguage()) {
            return $this->get('slug', $language_code) ?: $this->post_name;
        }
        return $this->post_name;
    }

    public function link(string $language_code = null): string
    {
        // if disabled honor external links, but do not fallback
        if ($this->isDisabled($language_code)) {
            return $this->get('external_link', $language_code) ?: '';
        }
        return Link::forPosts($this, $language_code);
    }

    public function redirectLink(string $language_code = null): string
    {
        // if disabled try external links, otherwise fallback to best bet
        if ($this->isDisabled($language_code)) {
            return $this->get('external_link', $language_code) ?: Link::fallback($this, $language_code);
        }
        return '';
    }

    public function isDisabled(string $language_code = null): bool
    {
        return !empty($this->get('is_disabled', $language_code));
    }

    public function terms(string $taxonomy = null, array $args = []): Terms
    {
        return Query::postTerms($this->ID, $taxonomy, $args);
    }
}
