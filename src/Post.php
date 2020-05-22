<?php

namespace Bond;

use Bond\Utils\Link;
use Bond\Support\Fluent;
use Bond\Utils\Cache;
use Bond\Utils\Cast;
use Bond\Utils\Query;
use Bond\Settings\Languages;

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
        if ($id = Cast::postId($values)) {

            $has_initted = false;

            $res = Cache::json(
                'bond/posts/' . $id,
                config('cache.posts_ttl') ?? 60 * 10,

                function () use ($values, &$has_initted) {
                    $this->init($values);
                    $has_initted = true;
                    return $this->toArray();
                }
            );
            if (!$has_initted) {
                $this->add($res);
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

        // add the WP post
        $this->add(Cast::wpPost($values));

        // Load fields
        $this->loadFields();
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
