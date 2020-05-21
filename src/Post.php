<?php

namespace Bond;

use Bond\Utils\Link;
use Bond\Support\Fluent;
use Bond\Support\WithFields;
use Bond\Utils\Cache;
use Bond\Utils\Cast;
use Bond\Utils\Obj;
use Bond\Utils\Query;
use Bond\Settings\Languages;

class Post extends Fluent
{
    use WithFields;

    public int $ID;
    public string $post_type;

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

    public function __construct($post = null)
    {
        if (config('cache.posts')) {

            $id = Cast::postId($post);

            if ($id) {
                $values = Cache::json(
                    'bond/posts/' . $id,
                    config('cache.posts_ttl') ?? 60 * 10,

                    function () use ($id) {
                        if ($post = Cast::wpPost($id)) {
                            $values = Obj::vars($post);

                            if (app()->hasAcf() && $fields = \get_fields($id)) {
                                $values += $fields;
                            }
                            return $values;
                        }
                        return [];
                    }
                );
                $this->has_loaded_fields = true;
                parent::__construct($values);
            }
        } else {
            parent::__construct(Cast::wpPost($post));
        }
    }

    public function add($values): self
    {
        $values = array_diff_key($values, array_flip($this->exclude));
        return parent::add($values);
    }


    public function terms(string $taxonomy = null, array $args = []): Terms
    {
        return Query::postTerms($this->ID, $taxonomy, $args);
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
}
