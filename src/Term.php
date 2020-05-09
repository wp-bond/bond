<?php

namespace Bond;

use Bond\Support\Fluent;
use Bond\Support\WithFields;
use Bond\Utils\Cache;
use Bond\Utils\Cast;
use Bond\Utils\Link;
use Bond\Utils\Obj;
use Bond\Settings\Languages;

class Term extends Fluent
{
    use WithFields;

    // TODO add WP_Term props and test
    // set null?
    public int $term_id;
    public string $taxonomy;


    public function __construct($term = null)
    {
        if (config('cache.terms')) {

            $id = Cast::termId($term);

            if ($id) {
                $values = Cache::json(
                    'bond/terms/' . $id,
                    config('cache.terms_ttl') ?? 60 * 10,

                    function () use ($id) {
                        if ($term = Cast::wpTerm($id)) {
                            $values = Obj::vars($term);
                            if ($fields = \get_fields($term)) {
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
            parent::__construct(Cast::wpTerm($term));
        }
    }

    protected function reloadFields()
    {
        if (isset($this->term_id) && isset($this->taxonomy)) {
            if (function_exists('\get_fields')) {
                $this->add(\get_fields($this->taxonomy . '_' . $this->term_id));
            }
            $this->has_loaded_fields = true;
        }
    }


    public function slug(string $language_code = null): string
    {
        if (Languages::isMultilanguage()) {
            return $this->get('slug', $language_code) ?: $this->slug;
        }
        return $this->slug;
    }

    public function link(string $language_code = null): string
    {
        return Link::forTerms($this, $language_code);
    }
}
