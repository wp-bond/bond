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

    // TODO add WP_Post props and test
    // set null?
    public int $ID;
    public string $post_type;


    // TODO maybe load the fields at construtor too?
    // do some tests as a !empty($this->somedata) would always be empty as it doesn't reach the __get autoload


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
                            if ($fields = \get_fields($id)) {
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
